<?php

namespace App\Services;

use App\Services\Scale\ScaleProtocol;
use App\Services\Scale\ScaleReading;
use App\Services\Scale\ScaleReadingStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Punto de entrada para consultar el peso de una balanza.
 *
 * Tiene dos caminos, y prefiere el primero:
 *
 *  1. Si el daemon `scale:watch` está vivo, devuelve la última lectura que
 *     publicó. No se abre ninguna conexión TCP durante la petición, así que
 *     una balanza caída no puede colgar la respuesta.
 *
 *  2. Si el daemon no está corriendo, cae a leer la balanza directamente,
 *     igual que antes. Esto mantiene el sistema usable sin supervisor (en
 *     desarrollo, o si el vigilante se murió) al costo de pagar el timeout
 *     de la balanza dentro de la petición.
 *
 * El camino directo nunca se intenta mientras el vigilante está vivo: el
 * adaptador serial-a-TCP suele aceptar un solo cliente, y el daemon ya tiene
 * el socket tomado.
 */
class ScaleService
{
    public function __construct(
        private readonly ScaleProtocol $protocol,
        private readonly ScaleReadingStore $store,
    ) {}

    /**
     * Lectura en forma de array, tal como la consume el controlador.
     */
    public function readWeight(?string $connection = null): array
    {
        return $this->read($connection)->toArray();
    }

    /**
     * Lee varias balanzas de una sola vez.
     *
     * Con el daemon corriendo esto no abre ninguna conexión, así que pedir
     * las dos balanzas en un único request es notablemente más barato que
     * dos requests: una sola resolución de sesión y de autenticación.
     *
     * @param  list<string>  $connections
     * @return array<string, array>
     */
    public function readMany(array $connections): array
    {
        $readings = [];

        foreach ($connections as $connection) {
            $readings[$connection] = $this->read($connection)->toArray() + [
                // La interfaz necesita saberlo por balanza: con vigilante, la
                // estabilidad viene medida sobre el flujo completo de
                // lecturas; sin vigilante hay que estimarla en el cliente con
                // muchas menos muestras.
                'watched' => $this->watcherIsAlive($connection),
            ];
        }

        return $readings;
    }

    public function read(?string $connection = null): ScaleReading
    {
        $name = $connection ?? config('scale.default');
        $config = config("scale.connections.{$name}");

        if (! $config) {
            Log::error('ScaleService: conexión de balanza no configurada', ['connection' => $name]);

            return ScaleReading::failure('Balanza no configurada.');
        }

        if ($this->store->watcherIsAlive($name)) {
            return $this->readFromWatcher($name);
        }

        return $this->readDirect($name, $config);
    }

    public function watcherIsAlive(?string $connection = null): bool
    {
        return $this->store->watcherIsAlive($connection ?? config('scale.default'));
    }

    public function watcherAgeMs(?string $connection = null): ?int
    {
        $age = $this->store->heartbeatAge($connection ?? config('scale.default'));

        return $age === null ? null : (int) round($age * 1000);
    }

    private function readFromWatcher(string $name): ScaleReading
    {
        $fresh = $this->store->getFresh($name);

        if ($fresh !== null) {
            return $fresh;
        }

        // El vigilante está vivo pero no tiene una lectura fresca de esta
        // balanza. Si lo último que publicó fue un fallo, se propaga ese
        // mensaje porque explica el motivo real (no conecta, checksum
        // inválido, etc.) mucho mejor que un error genérico.
        $last = $this->store->get($name);

        if ($last !== null && ! $last->success) {
            return $last;
        }

        return ScaleReading::failure('La balanza no está respondiendo.');
    }

    /**
     * Camino sin daemon: abre la conexión, lee y cierra.
     *
     * Mantiene el debounce y el candado para que varias pestañas no abran
     * conexiones simultáneas contra la misma balanza. A diferencia de la
     * versión anterior, la ventana de debounce se mide contra el microtime
     * que viaja dentro de la lectura y no contra el TTL del caché, que solo
     * tiene granularidad de segundos: antes, un `min_interval_ms` de 300
     * terminaba refrescando el peso una vez por segundo.
     */
    private function readDirect(string $name, array $config): ScaleReading
    {
        $minIntervalMs = (int) ($config['min_interval_ms'] ?? 300);

        $cached = $this->recentReading($name, $minIntervalMs);

        if ($cached !== null) {
            return $cached;
        }

        $store = Cache::store(config('scale.store'))->getStore();

        // No todos los drivers de caché saben hacer candados atómicos. Si el
        // configurado no puede, se lee igual: perder el candado degrada la
        // protección contra lecturas simultáneas, pero no justifica dejar de
        // pesar.
        if (! $store instanceof LockProvider) {
            return $this->readAndPublish($name, $config);
        }

        try {
            return $store->lock("scale:lock:{$name}", 5)->block(2, function () use ($name, $config, $minIntervalMs) {
                // Re-chequeo dentro del candado: si otra petición ya leyó la
                // balanza mientras esperábamos, se reutiliza ese resultado.
                $cached = $this->recentReading($name, $minIntervalMs);

                if ($cached !== null) {
                    return $cached;
                }

                return $this->readAndPublish($name, $config);
            });
        } catch (LockTimeoutException) {
            Log::warning('ScaleService: no se pudo obtener el candado, la balanza está ocupada', [
                'connection' => $name,
            ]);

            return ScaleReading::failure('La balanza está ocupada, intentá nuevamente.');
        }
    }

    private function readAndPublish(string $name, array $config): ScaleReading
    {
        $reading = $this->readFromDevice($name, $config);

        if ($reading->success) {
            $this->store->put($name, $reading);
        }

        return $reading;
    }

    private function recentReading(string $name, int $minIntervalMs): ?ScaleReading
    {
        $cached = $this->store->get($name);

        if ($cached === null || ! $cached->success) {
            return null;
        }

        return $cached->age() * 1000 < $minIntervalMs ? $cached : null;
    }

    private function readFromDevice(string $name, array $config): ScaleReading
    {
        $host = $config['host'];
        $port = (int) $config['port'];
        $timeout = (float) $config['timeout'];

        $logContext = ['connection' => $name, 'host' => $host, 'port' => $port];
        $socket = null;

        try {
            $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);

            if ($socket === false) {
                Log::error('ScaleService: error de conexión TCP', $logContext + [
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]);

                return ScaleReading::failure('No fue posible conectar con la balanza.');
            }

            stream_set_timeout($socket, (int) ceil($timeout));

            return $this->protocol->readWeight(
                $socket,
                $timeout,
                (float) $config['divisor'],
                (string) $config['unit'],
                $logContext,
            );
        } catch (\Throwable $exception) {
            Log::error('ScaleService: excepción inesperada al leer la balanza', $logContext + [
                'message' => $exception->getMessage(),
            ]);

            return ScaleReading::failure('Error inesperado al comunicarse con la balanza.');
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }
}
