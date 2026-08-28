<?php

namespace App\Services\Scale;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Pizarrón compartido entre el daemon `scale:watch` (que escribe) y las
 * peticiones HTTP (que leen).
 *
 * Usa un store de caché propio, configurable en `scale.store`, por defecto
 * distinto del de la aplicación: el daemon escribe varias veces por segundo
 * de forma indefinida y no tiene sentido que eso vaya contra la tabla
 * `cache` de MySQL, donde competiría con el resto del sistema.
 *
 * La frescura se juzga por el `read_at` que viaja dentro de la lectura, no
 * por el TTL del caché, porque el TTL de Laravel solo tiene granularidad de
 * segundos y acá se necesitan decenas de milisegundos.
 */
class ScaleReadingStore
{
    public function __construct(private readonly ?string $store = null) {}

    public function put(string $connection, ScaleReading $reading): void
    {
        $this->cache()->put(
            $this->readingKey($connection),
            $reading->toArray(),
            $this->ttlSeconds(),
        );
    }

    public function get(string $connection): ?ScaleReading
    {
        $data = $this->cache()->get($this->readingKey($connection));

        return is_array($data) ? ScaleReading::fromArray($data) : null;
    }

    /**
     * Última lectura de $connection, o null si no hay ninguna o si la que
     * hay ya está rancia (el daemon murió o perdió la balanza).
     */
    public function getFresh(string $connection): ?ScaleReading
    {
        $reading = $this->get($connection);

        if ($reading === null || $reading->age() > $this->staleAfterSeconds()) {
            return null;
        }

        return $reading;
    }

    /**
     * El latido es por balanza, no global, porque lo habitual es correr un
     * proceso por balanza: así una no puede quedar bloqueada esperando el
     * timeout de conexión de la otra. Con un latido global, vigilar solo
     * 'main' haría que 'scale_2' se considere vigilada y nunca se intente
     * leerla por el camino directo.
     */
    public function heartbeat(string $connection): void
    {
        $this->cache()->put($this->heartbeatKey($connection), microtime(true), $this->ttlSeconds());
    }

    public function forgetHeartbeat(string $connection): void
    {
        $this->cache()->forget($this->heartbeatKey($connection));
    }

    /** Segundos desde el último latido para esa balanza, o null si nunca latió. */
    public function heartbeatAge(string $connection): ?float
    {
        $last = $this->cache()->get($this->heartbeatKey($connection));

        return is_numeric($last) ? max(0.0, microtime(true) - (float) $last) : null;
    }

    /**
     * ¿Hay un vigilante activo para esta balanza? Se le da el doble de la
     * ventana de frescura como margen: un latido perdido por una pausa del
     * recolector o por una reconexión no debería reportarlo como caído.
     */
    public function watcherIsAlive(string $connection): bool
    {
        $age = $this->heartbeatAge($connection);

        return $age !== null && $age <= $this->staleAfterSeconds() * 2;
    }

    private function cache(): Repository
    {
        return Cache::store($this->store ?? config('scale.store'));
    }

    private function readingKey(string $connection): string
    {
        return "scale:reading:{$connection}";
    }

    private function heartbeatKey(string $connection): string
    {
        return "scale:watch:heartbeat:{$connection}";
    }

    private function staleAfterSeconds(): float
    {
        return max(1, (int) config('scale.watch.stale_after_ms', 2000)) / 1000;
    }

    /**
     * El TTL del caché solo actúa como red de contención para que no
     * queden lecturas viejas ocupando lugar; la decisión real de descartar
     * una lectura la toma getFresh() comparando microtime.
     */
    private function ttlSeconds(): int
    {
        return max(2, (int) ceil($this->staleAfterSeconds()) + 2);
    }
}
