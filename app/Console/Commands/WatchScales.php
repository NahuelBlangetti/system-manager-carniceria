<?php

namespace App\Console\Commands;

use App\Services\Scale\ScaleLink;
use App\Services\Scale\ScaleProtocol;
use App\Services\Scale\ScaleReading;
use App\Services\Scale\ScaleReadingStore;
use App\Services\Scale\StabilityTracker;
use Illuminate\Console\Command;

/**
 * Vigila las balanzas de forma continua y publica la última lectura de cada
 * una en el store compartido.
 *
 * Invierte el modelo anterior, donde cada petición HTTP abría su propia
 * conexión TCP a la balanza. Eso traía tres problemas: una balanza
 * desenchufada colgaba la petición durante todo el timeout, el debounce por
 * TTL de caché limitaba el refresco real a una vez por segundo, y cada
 * consulta se pagaba en conexiones TCP y queries.
 *
 * Con este daemon corriendo, el navegador nunca toca la balanza: lee lo que
 * el vigilante dejó publicado, que es una operación de caché. Y como acá se
 * ve el flujo completo de lecturas a alta frecuencia, la estabilidad del
 * peso se puede medir de verdad en vez de estimarla sobre datos repetidos.
 *
 * Pensado para correr bajo supervisor/systemd en la PC del local, con un
 * proceso por balanza. Las balanzas se leen en secuencia dentro del ciclo,
 * así que meter dos en un mismo proceso hace que el timeout de una congele
 * la lectura de la otra mientras dura; un proceso por balanza las aísla por
 * completo, igual que se hace con los workers de cola.
 *
 *   [program:scale-watch-main]
 *   command=php /var/www/html/.../artisan scale:watch main
 *   autostart=true
 *   autorestart=true
 *   stopsignal=TERM
 *
 *   [program:scale-watch-2]
 *   command=php /var/www/html/.../artisan scale:watch scale_2
 *   autostart=true
 *   autorestart=true
 *   stopsignal=TERM
 */
class WatchScales extends Command
{
    protected $signature = 'scale:watch
        {connections?* : Conexiones a vigilar (por defecto, todas las configuradas)}
        {--once : Hacer una sola pasada y salir (diagnóstico)}';

    protected $description = 'Vigila las balanzas en forma continua y publica el peso en el store compartido';

    private bool $shouldStop = false;

    public function handle(ScaleProtocol $protocol, ScaleReadingStore $store): int
    {
        $links = $this->buildLinks($protocol);

        if ($links === []) {
            return self::FAILURE;
        }

        $this->listenForSignals();
        $this->printHeader($links);

        $intervalUs = max(50, (int) config('scale.watch.interval_ms', 200)) * 1000;

        /** @var array<string, ScaleReading|null> $published */
        $published = [];

        do {
            $startedAt = microtime(true);

            foreach ($links as $link) {
                $reading = $link->read();

                if ($this->shouldPublish($reading, $published[$link->name()] ?? null)) {
                    $store->put($link->name(), $reading);
                    $published[$link->name()] = $reading;
                }

                // El latido va por balanza y se emite haya o no lectura: dice
                // "hay alguien vigilando esto", no "esto está funcionando".
                $store->heartbeat($link->name());

                $this->report($link->name(), $reading);
            }

            if ($this->option('once') || $this->shouldStop) {
                break;
            }

            // Se descuenta lo que tardó el ciclo para que el intervalo sea
            // el período real de muestreo y no un tiempo de espera fijo.
            $remaining = $intervalUs - (int) ((microtime(true) - $startedAt) * 1_000_000);

            if ($remaining > 0) {
                usleep($remaining);
            }
        } while (! $this->shouldStop);

        foreach ($links as $link) {
            $link->disconnect();

            // Se borra el latido al salir para que la interfaz sepa de
            // inmediato que ya no hay vigilancia, en vez de esperar a que
            // expire y mientras tanto mostrar un peso viejo.
            $store->forgetHeartbeat($link->name());
        }

        $this->newLine();
        $this->info('Vigilancia detenida.');

        return self::SUCCESS;
    }

    /** @return list<ScaleLink> */
    private function buildLinks(ScaleProtocol $protocol): array
    {
        $configured = config('scale.connections', []);
        $requested = $this->argument('connections') ?: array_keys($configured);

        $samples = max(2, (int) config('scale.stability.samples', 4));
        $divisions = max(1, (int) config('scale.stability.tolerance_divisions', 1));

        $links = [];

        foreach ($requested as $name) {
            $config = $configured[$name] ?? null;

            if (! $config) {
                $this->error("La conexión '{$name}' no existe en config/scale.php.");

                return [];
            }

            $links[] = new ScaleLink(
                $name,
                $config,
                $protocol,
                new StabilityTracker($samples, $divisions),
            );
        }

        return $links;
    }

    /**
     * Publica solo cuando el dato cambió, pero refresca igual antes de que
     * la lectura se vuelva rancia: los consumidores descartan lecturas
     * viejas por `read_at`, así que una balanza quieta en 0,00 kg todavía
     * necesita latir de vez en cuando para no parecer desconectada.
     */
    private function shouldPublish(ScaleReading $reading, ?ScaleReading $published): bool
    {
        if ($published === null) {
            return true;
        }

        if ($reading->success !== $published->success || $reading->stable !== $published->stable) {
            return true;
        }

        // Se comparan las cuentas crudas de la balanza y no el peso en kg:
        // es una igualdad exacta, sin tolerancias en punto flotante.
        if ($reading->success && $reading->raw !== $published->raw) {
            return true;
        }

        $refreshAfter = max(1, (int) config('scale.watch.stale_after_ms', 2000)) / 2000;

        return $published->age() >= $refreshAfter;
    }

    private function report(string $name, ScaleReading $reading): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        if (! $reading->success) {
            $this->line(sprintf('<fg=red>%-10s sin lectura — %s</>', $name, $reading->message));

            return;
        }

        $this->line(sprintf(
            '%-10s <fg=cyan>%8.3f %s</> %s',
            $name,
            $reading->weight,
            $reading->unit,
            $reading->stable ? '<fg=green>estable</>' : '<fg=yellow>inestable</>',
        ));
    }

    private function listenForSignals(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldStop = true;
            });
        }
    }

    /** @param  list<ScaleLink>  $links */
    private function printHeader(array $links): void
    {
        $names = implode(', ', array_map(fn (ScaleLink $l) => $l->name(), $links));

        $this->info(sprintf(
            'Vigilando %s cada %dms.',
            $names,
            (int) config('scale.watch.interval_ms', 200),
        ));

        $store = config('scale.store');

        if ($store === config('cache.default') && $store === 'database') {
            $this->warn('scale.store apunta al caché de base de datos: el sondeo continuo');
            $this->warn('va a escribir en la tabla `cache` varias veces por segundo.');
            $this->warn('Conviene SCALE_CACHE_STORE=file para sacarlo de MySQL.');
            $this->newLine();
        }

        // Las balanzas se leen en secuencia dentro del ciclo, así que una que
        // no responde se come su timeout y durante ese rato la otra no se
        // actualiza. Un proceso por balanza elimina el problema de raíz.
        if (count($links) > 1) {
            $this->warn('Vigilando varias balanzas en un solo proceso: si una deja de');
            $this->warn('responder, su timeout congela la lectura de las demás mientras dura.');
            $this->warn('En producción conviene un proceso por balanza:');

            foreach ($links as $link) {
                $this->warn("  php artisan scale:watch {$link->name()}");
            }
        }

        if (! $this->output->isVerbose()) {
            $this->comment('Usá -v para ver cada lectura. Cortá con Ctrl+C.');
        }

        $this->newLine();
    }
}
