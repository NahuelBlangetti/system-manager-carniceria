<?php

namespace App\Services\Scale;

use Illuminate\Support\Facades\Log;

/**
 * Protocolo de comunicación con balanzas Systel (línea Clipse/Croma) sobre
 * un socket ya abierto. Confirmado empíricamente contra hardware real y
 * consistente con el manual de comunicación serial de Systel:
 *
 *   PC ---- ENQ (0x05) --------> Balanza
 *   PC <--- STX peso ETX CRC --- Balanza
 *   PC ---- ACK (0x06) --------> Balanza   (si el CRC es válido)
 *   PC ---- NACK (0x15) -------> Balanza   (si el CRC no coincide)
 *
 * CRC = XOR de todos los bytes entre STX y ETX, ambos inclusive.
 * Si la balanza todavía no tiene un peso listo puede responder solo
 * WACK (0x11), lo que indica que hay que reintentar el ENQ.
 *
 * Esta clase no abre ni cierra el socket a propósito: así la misma lógica
 * sirve tanto para una conexión de un solo uso (petición HTTP) como para la
 * conexión persistente del daemon `scale:watch`.
 *
 * Nota: la consulta de "peso + estabilidad" documentada por Systel
 * (comando 0x07) no se implementa acá porque no se pudo confirmar contra
 * esta unidad/firmware — la respuesta obtenida en pruebas no vino
 * enmarcada en STX/ETX y no siguió un patrón reconocible. La estabilidad
 * se deriva del historial de lecturas (ver StabilityTracker).
 */
class ScaleProtocol
{
    public const ENQ = "\x05";

    private const STX = "\x02";

    private const ETX = "\x03";

    private const ACK = "\x06";

    private const NACK = "\x15";

    private const WACK = "\x11";

    /** Tamaño máximo de trama aceptado, para no leer indefinidamente. */
    private const MAX_FRAME_SIZE = 256;

    /** Reintentos de ENQ ante una respuesta WACK ("esperá"). */
    private const MAX_WACK_RETRIES = 3;

    /**
     * Pide el peso y devuelve la lectura interpretada.
     *
     * @param  resource  $socket  Socket abierto y en modo bloqueante.
     * @param  array<string, mixed>  $logContext  Contexto para los logs (host, puerto, conexión).
     */
    public function readWeight(
        $socket,
        float $timeout,
        float $divisor,
        string $unit,
        array $logContext = [],
    ): ScaleReading {
        $buffer = $this->requestFrame($socket, $timeout, $logContext);

        if ($buffer === null) {
            // Ya se logueó el motivo puntual dentro de requestFrame().
            return ScaleReading::failure('La balanza no respondió.');
        }

        if (! str_contains($buffer, self::STX)) {
            Log::warning('Balanza: no quedó lista para pesar', $logContext);

            return ScaleReading::failure('La balanza no está lista, intentá nuevamente.');
        }

        return $this->parseFrame($buffer, $socket, $divisor, $unit, $logContext);
    }

    /**
     * Envía ENQ y lee la respuesta, reintentando si la balanza responde
     * WACK (0x11 = "esperá"). Devuelve null si nunca llegó ninguna
     * respuesta (falla de comunicación real, no un WACK).
     */
    private function requestFrame($socket, float $timeout, array $logContext): ?string
    {
        $deadline = microtime(true) + $timeout;
        $buffer = '';

        for ($attempt = 1; $attempt <= self::MAX_WACK_RETRIES; $attempt++) {
            if (@fwrite($socket, self::ENQ) === false) {
                Log::error('Balanza: error al enviar ENQ', $logContext);

                return null;
            }

            $buffer = $this->readFrame($socket, $deadline);

            if ($buffer === '') {
                Log::warning('Balanza: respuesta vacía', $logContext);

                return null;
            }

            if ($buffer !== self::WACK) {
                break;
            }

            Log::info('Balanza: pidió espera (WACK)', $logContext + ['intento' => $attempt]);

            if (microtime(true) >= $deadline) {
                break;
            }

            usleep(150_000);
        }

        return $buffer;
    }

    /**
     * Lee incrementalmente hasta encontrar ETX, agotar el timeout total
     * o superar el tamaño máximo de trama. No asume que un único fread()
     * traiga la trama completa.
     */
    private function readFrame($socket, float $deadline): string
    {
        $buffer = '';

        while (! str_contains($buffer, self::ETX)) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $chunk = fread($socket, 256);

            if ($chunk === false) {
                break;
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($socket);
                if ($meta['timed_out'] || feof($socket)) {
                    break;
                }

                continue;
            }

            $buffer .= $chunk;

            if ($buffer === self::WACK) {
                // Respuesta de "esperá" aislada: no tiene sentido seguir
                // leyendo en esta conexión, hay que reintentar el ENQ.
                break;
            }

            if (strlen($buffer) > self::MAX_FRAME_SIZE) {
                break;
            }
        }

        return $buffer;
    }

    private function parseFrame(
        string $buffer,
        $socket,
        float $divisor,
        string $unit,
        array $logContext,
    ): ScaleReading {
        $start = strpos($buffer, self::STX);
        $end = $start !== false ? strpos($buffer, self::ETX, $start + 1) : false;

        if ($start === false || $end === false) {
            Log::warning('Balanza: trama inválida (sin STX/ETX)', $logContext);

            return ScaleReading::failure('Respuesta de la balanza inválida.');
        }

        $crcIndex = $end + 1;

        if (! isset($buffer[$crcIndex])) {
            Log::warning('Balanza: trama sin byte de checksum', $logContext);

            return ScaleReading::failure('Respuesta de la balanza incompleta.');
        }

        $frameForCrc = substr($buffer, $start, $end - $start + 1); // STX..ETX inclusive
        $expectedCrc = $this->calculateXor($frameForCrc);
        $receivedCrc = ord($buffer[$crcIndex]);

        if ($expectedCrc !== $receivedCrc) {
            Log::warning('Balanza: checksum inválido', $logContext + [
                'esperado' => $expectedCrc,
                'recibido' => $receivedCrc,
            ]);

            $this->sendControlByte($socket, self::NACK);

            return ScaleReading::failure('La balanza envió una trama con checksum inválido.');
        }

        $this->sendControlByte($socket, self::ACK);

        $raw = substr($buffer, $start + 1, $end - $start - 1);

        // Systel puede anteponer "-" cuando el peso es negativo.
        if ($raw === '' || ! preg_match('/^-?\d+$/', $raw)) {
            Log::warning('Balanza: contenido de trama no numérico', $logContext);

            return ScaleReading::failure('Respuesta de la balanza inválida.');
        }

        return ScaleReading::success(((int) $raw) / $divisor, $raw, $unit);
    }

    private function calculateXor(string $data): int
    {
        $crc = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
        }

        return $crc;
    }

    /** Envío best-effort: si la balanza ya cerró la conexión no es un error para el caller. */
    private function sendControlByte($socket, string $byte): void
    {
        if (is_resource($socket)) {
            @fwrite($socket, $byte);
        }
    }
}
