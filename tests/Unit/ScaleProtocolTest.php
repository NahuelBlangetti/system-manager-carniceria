<?php

namespace Tests\Unit;

use App\Services\Scale\ScaleProtocol;
use Tests\TestCase;

/**
 * El protocolo se prueba contra una balanza simulada con stream_socket_pair:
 * se precargan en un extremo los bytes que enviaría la balanza y se deja que
 * ScaleProtocol lea del otro. Así se puede verificar el manejo de tramas
 * corruptas, que es justamente lo que no se puede provocar a voluntad con el
 * hardware real.
 */
class ScaleProtocolTest extends TestCase
{
    private const STX = "\x02";

    private const ETX = "\x03";

    private const ACK = "\x06";

    private const NACK = "\x15";

    private const ENQ = "\x05";

    /** @var resource */
    private $pc;

    /** @var resource */
    private $scale;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->pc, $this->scale] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        // Timeout corto para que un test que espera datos que no llegan
        // termine rápido en vez de colgar la suite.
        stream_set_blocking($this->pc, true);
        stream_set_timeout($this->pc, 0, 100_000);
    }

    protected function tearDown(): void
    {
        foreach ([$this->pc, $this->scale] as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        parent::tearDown();
    }

    public function test_it_reads_a_valid_frame_applying_the_divisor(): void
    {
        fwrite($this->scale, $this->frame('1250'));

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertTrue($reading->success);
        $this->assertSame(12.5, $reading->weight);
        $this->assertSame('1250', $reading->raw);
        $this->assertSame('kg', $reading->unit);

        // Una lectura suelta nunca puede afirmar estabilidad: eso lo decide
        // el historial, no la trama.
        $this->assertFalse($reading->stable);
    }

    public function test_it_acknowledges_a_valid_frame(): void
    {
        fwrite($this->scale, $this->frame('0500'));

        (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertSame(self::ACK, $this->lastControlByteSentToScale());
    }

    public function test_it_reads_negative_weights(): void
    {
        fwrite($this->scale, $this->frame('-250'));

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertTrue($reading->success);
        $this->assertSame(-2.5, $reading->weight);
    }

    public function test_it_rejects_a_frame_with_an_invalid_checksum(): void
    {
        $frame = $this->frame('1250');
        // Se corrompe el último byte (el checksum) manteniendo el resto.
        $corrupted = substr($frame, 0, -1).chr((ord($frame[strlen($frame) - 1]) + 1) % 256);

        fwrite($this->scale, $corrupted);

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertFalse($reading->success);
        $this->assertStringContainsString('checksum', $reading->message);

        // Un checksum malo tiene que responderse con NACK para que la balanza
        // sepa que hay que retransmitir.
        $this->assertSame(self::NACK, $this->lastControlByteSentToScale());
    }

    public function test_it_rejects_a_frame_whose_content_is_not_numeric(): void
    {
        fwrite($this->scale, $this->frame('12A0'));

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertFalse($reading->success);
        $this->assertSame('Respuesta de la balanza inválida.', $reading->message);
    }

    public function test_it_rejects_a_frame_without_the_checksum_byte(): void
    {
        fwrite($this->scale, self::STX.'1250'.self::ETX);

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertFalse($reading->success);
        $this->assertSame('Respuesta de la balanza incompleta.', $reading->message);
    }

    public function test_it_reassembles_a_frame_split_across_several_reads(): void
    {
        // El adaptador serial-a-TCP puede entregar la trama en pedazos; el
        // protocolo no debe asumir que un solo fread la trae completa.
        $frame = $this->frame('2000');
        fwrite($this->scale, substr($frame, 0, 3));
        fwrite($this->scale, substr($frame, 3));

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.5, 100, 'kg');

        $this->assertTrue($reading->success);
        $this->assertSame(20.0, $reading->weight);
    }

    public function test_it_reports_failure_when_the_scale_only_asks_to_wait(): void
    {
        // WACK aislado: la balanza pide esperar y nunca manda un peso.
        fwrite($this->scale, "\x11");

        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.4, 100, 'kg');

        $this->assertFalse($reading->success);
    }

    public function test_it_reports_failure_when_the_scale_says_nothing(): void
    {
        $reading = (new ScaleProtocol)->readWeight($this->pc, 0.3, 100, 'kg');

        $this->assertFalse($reading->success);
        $this->assertSame('La balanza no respondió.', $reading->message);
    }

    /** Trama tal como la arma la balanza: STX + dígitos + ETX + XOR(STX..ETX). */
    private function frame(string $digits): string
    {
        $body = self::STX.$digits.self::ETX;

        $crc = 0;
        for ($i = 0; $i < strlen($body); $i++) {
            $crc ^= ord($body[$i]);
        }

        return $body.chr($crc);
    }

    /**
     * Último byte que la PC le mandó a la balanza, descartando los ENQ de
     * pedido de peso.
     */
    private function lastControlByteSentToScale(): ?string
    {
        stream_set_blocking($this->scale, false);
        $sent = (string) fread($this->scale, 256);

        $bytes = array_values(array_filter(
            str_split($sent),
            fn (string $byte) => $byte !== self::ENQ,
        ));

        return $bytes === [] ? null : end($bytes);
    }
}
