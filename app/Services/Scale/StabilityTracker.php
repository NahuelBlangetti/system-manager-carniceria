<?php

namespace App\Services\Scale;

/**
 * Deriva la estabilidad del peso a partir del historial de lecturas.
 *
 * Hace falta porque el comando de "peso + estabilidad" de Systel (0x07) no
 * se pudo confirmar contra este firmware, así que la balanza nos dice
 * cuánto pesa pero no si el peso está quieto. Sin esto, el error clásico es
 * congelar 1,8 kg porque el operador apretó el botón mientras la carne
 * todavía rebotaba en el plato.
 *
 * Se considera estable cuando las últimas N lecturas caen dentro de una
 * tolerancia expresada en divisiones de la balanza. Cualquier lectura
 * fallida corta la racha: no se puede afirmar que un peso está quieto si se
 * perdió el hilo de la comunicación en el medio.
 *
 * La comparación se hace sobre las cuentas enteras que manda la balanza y no
 * sobre el peso en kg, porque en punto flotante una variación de exactamente
 * una división no cabe en una tolerancia de una división: 1.01 - 1.00 da
 * 0.010000000000000009. Como el último dígito de la balanza titila una
 * división de forma permanente, con floats el peso nunca se declararía
 * estable.
 *
 * Es importante que esto se alimente de lecturas *reales* y no de una
 * respuesta cacheada repetida, o la estabilidad se cumpliría sola. Por eso
 * vive del lado del daemon, que es el único que ve el flujo completo.
 */
class StabilityTracker
{
    /** @var list<int> */
    private array $counts = [];

    public function __construct(
        private readonly int $samples,
        private readonly int $toleranceDivisions,
    ) {}

    public function record(ScaleReading $reading): ScaleReading
    {
        if (! $reading->success) {
            $this->reset();

            return $reading;
        }

        $this->counts[] = (int) $reading->raw;

        if (count($this->counts) > $this->samples) {
            $this->counts = array_slice($this->counts, -$this->samples);
        }

        return $reading->withStable($this->isStable());
    }

    public function reset(): void
    {
        $this->counts = [];
    }

    private function isStable(): bool
    {
        if (count($this->counts) < $this->samples) {
            return false;
        }

        return (max($this->counts) - min($this->counts)) <= $this->toleranceDivisions;
    }
}
