<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Herramienta de diagnóstico: abre la conexión a la balanza y vuelca todo
 * lo que llega, sin interpretar nada.
 *
 * El caso de uso principal es averiguar si la balanza emite datos por el
 * puerto serie cuando el carnicero aprieta una tecla (típicamente la de
 * impresión). Si lo hace, esa tecla sirve como confirmación explícita del
 * pesaje y no hace falta inferir pesajes detectando mesetas de peso.
 *
 * Por eso el modo por defecto es pasivo: NO envía ENQ. Cualquier byte que
 * aparezca en pantalla fue enviado por iniciativa de la balanza.
 *
 * Uso:
 *   php artisan scale:sniff                 # escucha 'main' 60s, pasivo
 *   php artisan scale:sniff scale_2 --seconds=120
 *   php artisan scale:sniff --enq           # además sondea, para comparar
 */
class SniffScale extends Command
{
    protected $signature = 'scale:sniff
        {connection? : Nombre de la conexión en config/scale.php}
        {--seconds=60 : Cuántos segundos escuchar}
        {--enq : Enviar ENQ periódicamente en vez de solo escuchar}
        {--interval=1000 : Milisegundos entre ENQ, si se usa --enq}';

    protected $description = 'Vuelca los bytes crudos que envía la balanza (diagnóstico de protocolo)';

    /** Nombres de los bytes de control del protocolo Systel, para que el volcado se lea. */
    private const CONTROL_NAMES = [
        0x01 => 'SOH', 0x02 => 'STX', 0x03 => 'ETX', 0x04 => 'EOT',
        0x05 => 'ENQ', 0x06 => 'ACK', 0x07 => 'BEL', 0x08 => 'BS',
        0x09 => 'TAB', 0x0A => 'LF', 0x0D => 'CR', 0x11 => 'DC1',
        0x12 => 'DC2', 0x13 => 'DC3', 0x14 => 'DC4', 0x15 => 'NAK',
        0x16 => 'SYN', 0x17 => 'ETB', 0x1B => 'ESC',
    ];

    public function handle(): int
    {
        $name = $this->argument('connection') ?? config('scale.default');
        $config = config("scale.connections.{$name}");

        if (! $config) {
            $this->error("La conexión '{$name}' no existe en config/scale.php.");

            return self::FAILURE;
        }

        $host = $config['host'];
        $port = (int) $config['port'];

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, (float) $config['timeout']);

        if ($socket === false) {
            $this->error("No se pudo conectar a {$host}:{$port} — [{$errno}] {$errstr}");

            return self::FAILURE;
        }

        // Lecturas no bloqueantes: en modo pasivo el socket puede quedarse
        // callado indefinidamente y no queremos trabar el bucle esperándolo.
        stream_set_blocking($socket, false);

        $this->printHeader($name, $host, $port);

        $seconds = max(1, (int) $this->option('seconds'));
        $sendEnq = (bool) $this->option('enq');
        $enqInterval = max(50, (int) $this->option('interval')) / 1000;

        $start = microtime(true);
        $deadline = $start + $seconds;
        $nextEnq = $start;
        $totalBytes = 0;
        $chunks = 0;

        $stop = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$stop) {
                $stop = true;
            });
            pcntl_signal(SIGTERM, function () use (&$stop) {
                $stop = true;
            });
        }

        while (! $stop && microtime(true) < $deadline) {
            if ($sendEnq && microtime(true) >= $nextEnq) {
                @fwrite($socket, "\x05");
                $this->line(sprintf('<fg=yellow>[%7.3fs] --> ENQ</>', microtime(true) - $start));
                $nextEnq = microtime(true) + $enqInterval;
            }

            $read = [$socket];
            $write = $except = null;

            // 200ms de espera: suficiente para no quemar CPU y para que el
            // envío de ENQ no se retrase de forma perceptible.
            if (@stream_select($read, $write, $except, 0, 200_000) < 1) {
                if (feof($socket)) {
                    $this->warn('La balanza cerró la conexión.');
                    break;
                }

                continue;
            }

            $chunk = fread($socket, 4096);

            if ($chunk === false || $chunk === '') {
                if (feof($socket)) {
                    $this->warn('La balanza cerró la conexión.');
                    break;
                }

                continue;
            }

            $totalBytes += strlen($chunk);
            $chunks++;
            $this->dump(microtime(true) - $start, $chunk);
        }

        fclose($socket);

        $this->newLine();
        $this->info(sprintf(
            'Fin. %d bytes en %d bloque(s) durante %.1fs.',
            $totalBytes,
            $chunks,
            microtime(true) - $start
        ));

        if ($totalBytes === 0 && ! $sendEnq) {
            $this->newLine();
            $this->comment('No llegó ningún byte. Eso significa que la balanza no transmite');
            $this->comment('por iniciativa propia con la configuración actual. Probá:');
            $this->comment('  · apretar la tecla de impresión de la balanza mientras corre esto;');
            $this->comment('  · configurar el protocolo en la balanza (MENU > CONF, clave 1234,');
            $this->comment('    opción INPRE) en PRT5 / envío continuo y repetir;');
            $this->comment('  · volver a correr con --enq para confirmar que el enlace funciona.');
        }

        return self::SUCCESS;
    }

    private function printHeader(string $name, string $host, int $port): void
    {
        $this->info("Escuchando la balanza '{$name}' en {$host}:{$port}");

        if ($this->option('enq')) {
            $this->comment("Modo activo: se envía ENQ cada {$this->option('interval')}ms.");
        } else {
            $this->comment('Modo pasivo: no se envía nada. Todo lo que aparezca lo mandó la balanza.');
            $this->comment('Apretá las teclas de la balanza (impresión, tara, total) para ver qué emite.');
        }

        $this->comment('Cortá con Ctrl+C.');
        $this->newLine();
    }

    /** Vuelca un bloque en hex y en ASCII, nombrando los bytes de control. */
    private function dump(float $elapsed, string $chunk): void
    {
        $hex = [];
        $ascii = [];

        foreach (str_split($chunk) as $byte) {
            $code = ord($byte);
            $hex[] = sprintf('%02X', $code);

            if (isset(self::CONTROL_NAMES[$code])) {
                $ascii[] = '<'.self::CONTROL_NAMES[$code].'>';
            } elseif ($code < 0x20 || $code > 0x7E) {
                $ascii[] = sprintf('<%02X>', $code);
            } else {
                $ascii[] = $byte;
            }
        }

        $this->line(sprintf(
            '<fg=green>[%7.3fs] <-- %d byte(s)</>',
            $elapsed,
            strlen($chunk)
        ));
        $this->line('           hex   '.implode(' ', $hex));
        $this->line('           ascii '.implode('', $ascii));
    }
}
