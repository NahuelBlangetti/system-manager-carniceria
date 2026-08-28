<?php

namespace App\Services\Scale;

use Illuminate\Support\Facades\Log;

/**
 * Conexión persistente a una balanza, con reconexión y corte de circuito.
 *
 * El daemon mantiene el socket abierto entre lecturas en vez de abrir y
 * cerrar uno por consulta: a varias lecturas por segundo, el handshake TCP
 * contra el adaptador serial-a-TCP costaría más que la lectura misma.
 *
 * El corte de circuito es lo que hace que una balanza desenchufada no
 * arruine el resto. Conectarse por TCP a una IP donde no hay nada no falla
 * rápido: se come el timeout completo esperando que resuelva ARP. Sin este
 * mecanismo, cada ciclo del daemon pagaría ese timeout y la segunda balanza
 * quedaría rehén de la primera. Tras un fallo de conexión, el circuito
 * queda abierto un rato (creciente) y las lecturas fallan al instante.
 */
class ScaleLink
{
    /** @var resource|null */
    private $socket = null;

    /** microtime a partir del cual se permite volver a intentar conectar. */
    private float $retryAt = 0.0;

    private int $connectFailures = 0;

    private int $readFailures = 0;

    /**
     * Fallos de lectura consecutivos con el socket abierto antes de asumir
     * que la conexión quedó inservible y forzar una reconexión.
     */
    private const MAX_READ_FAILURES = 3;

    public function __construct(
        private readonly string $name,
        private readonly array $config,
        private readonly ScaleProtocol $protocol,
        private readonly StabilityTracker $stability,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket);
    }

    public function read(): ScaleReading
    {
        if (! $this->isConnected() && ! $this->connect()) {
            return $this->stability->record(
                ScaleReading::failure('No fue posible conectar con la balanza.')
            );
        }

        $reading = $this->protocol->readWeight(
            $this->socket,
            $this->readTimeout(),
            (float) $this->config['divisor'],
            (string) $this->config['unit'],
            $this->logContext(),
        );

        if ($reading->success) {
            $this->readFailures = 0;

            return $this->stability->record($reading);
        }

        $this->readFailures++;

        // Si la balanza cortó del otro lado, o si viene fallando seguido,
        // el socket no sirve más: se cierra para reconectar en el próximo
        // ciclo en vez de insistir sobre una conexión muerta.
        if (! is_resource($this->socket) || feof($this->socket) || $this->readFailures >= self::MAX_READ_FAILURES) {
            $this->disconnect();
            $this->scheduleRetry();
        }

        return $this->stability->record($reading);
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }

        $this->socket = null;
        $this->readFailures = 0;
        $this->stability->reset();
    }

    private function connect(): bool
    {
        if (microtime(true) < $this->retryAt) {
            return false;
        }

        $host = $this->config['host'];
        $port = (int) $this->config['port'];

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $this->connectTimeout(),
        );

        if ($socket === false) {
            $this->connectFailures++;
            $this->scheduleRetry();

            // Solo se loguea el primer fallo de cada racha: si la balanza
            // quedó desconectada toda la tarde, no tiene sentido escribir
            // una línea de log por cada intento.
            if ($this->connectFailures === 1) {
                Log::error('Balanza: error de conexión TCP', $this->logContext() + [
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]);
            }

            return false;
        }

        $this->socket = $socket;
        $this->readFailures = 0;

        $timeout = $this->readTimeout();
        stream_set_blocking($socket, true);
        stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1_000_000));

        if ($this->connectFailures > 0) {
            Log::info('Balanza: conexión restablecida', $this->logContext() + [
                'intentos_fallidos' => $this->connectFailures,
            ]);
        }

        $this->connectFailures = 0;
        $this->retryAt = 0.0;

        return true;
    }

    /** Backoff exponencial acotado, para no martillar una balanza ausente. */
    private function scheduleRetry(): void
    {
        $base = max(100, (int) config('scale.watch.retry_base_ms', 500));
        $max = max($base, (int) config('scale.watch.retry_max_ms', 5000));
        $delay = min($max, $base * (2 ** max(0, $this->connectFailures - 1)));

        $this->retryAt = microtime(true) + $delay / 1000;
    }

    /**
     * El daemon usa un timeout más corto que el del camino HTTP: prefiere
     * fallar rápido y reintentar en el ciclo siguiente antes que trabar el
     * bucle que también atiende a la otra balanza.
     */
    private function readTimeout(): float
    {
        return max(100, (int) config('scale.watch.read_timeout_ms', 800)) / 1000;
    }

    private function connectTimeout(): float
    {
        return max(100, (int) config('scale.watch.connect_timeout_ms', 1000)) / 1000;
    }

    private function logContext(): array
    {
        return [
            'connection' => $this->name,
            'host' => $this->config['host'],
            'port' => (int) $this->config['port'],
        ];
    }
}
