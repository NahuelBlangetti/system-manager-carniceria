<?php

namespace Tests\Unit;

use App\Services\Scale\ScaleReading;
use App\Services\Scale\StabilityTracker;
use PHPUnit\Framework\TestCase;

class StabilityTrackerTest extends TestCase
{
    /** Una división de tolerancia, el valor por defecto de la configuración. */
    private const TOLERANCE = 1;

    public function test_it_needs_enough_samples_before_declaring_stability(): void
    {
        $tracker = new StabilityTracker(4, self::TOLERANCE);

        $this->assertFalse($tracker->record($this->reading(1.0))->stable);
        $this->assertFalse($tracker->record($this->reading(1.0))->stable);
        $this->assertFalse($tracker->record($this->reading(1.0))->stable);
        $this->assertTrue($tracker->record($this->reading(1.0))->stable);
    }

    /**
     * El último dígito de la balanza titila una división de forma
     * permanente, así que si esto no se tolerara el peso nunca se declararía
     * estable y no se podría cobrar nada.
     */
    public function test_it_tolerates_variation_of_one_division(): void
    {
        $tracker = new StabilityTracker(3, self::TOLERANCE);

        $tracker->record($this->reading(1.00));
        $tracker->record($this->reading(1.01));

        $this->assertTrue($tracker->record($this->reading(1.00))->stable);
    }

    public function test_it_does_not_tolerate_variation_beyond_the_configured_divisions(): void
    {
        $tracker = new StabilityTracker(3, self::TOLERANCE);

        $tracker->record($this->reading(1.00));
        $tracker->record($this->reading(1.02));

        $this->assertFalse($tracker->record($this->reading(1.00))->stable);
    }

    public function test_it_does_not_declare_stability_while_the_weight_moves(): void
    {
        $tracker = new StabilityTracker(3, self::TOLERANCE);

        $tracker->record($this->reading(1.00));
        $tracker->record($this->reading(1.40));

        $this->assertFalse($tracker->record($this->reading(1.80))->stable);
    }

    public function test_it_becomes_stable_once_the_weight_settles(): void
    {
        $tracker = new StabilityTracker(3, self::TOLERANCE);

        // La carne cae en el plato y rebota antes de asentarse.
        foreach ([0.0, 1.9, 1.4, 1.55] as $weight) {
            $this->assertFalse($tracker->record($this->reading($weight))->stable);
        }

        $tracker->record($this->reading(1.50));
        $tracker->record($this->reading(1.50));

        $this->assertTrue($tracker->record($this->reading(1.50))->stable);
    }

    public function test_a_failed_reading_breaks_the_streak(): void
    {
        $tracker = new StabilityTracker(3, self::TOLERANCE);

        $tracker->record($this->reading(1.0));
        $tracker->record($this->reading(1.0));
        $this->assertTrue($tracker->record($this->reading(1.0))->stable);

        // Si se perdió la comunicación en el medio no se puede seguir
        // afirmando que el peso está quieto: hay que juntar muestras de nuevo.
        $tracker->record(ScaleReading::failure('sin conexión'));

        $this->assertFalse($tracker->record($this->reading(1.0))->stable);
        $this->assertFalse($tracker->record($this->reading(1.0))->stable);
        $this->assertTrue($tracker->record($this->reading(1.0))->stable);
    }

    public function test_it_never_marks_a_failed_reading_as_stable(): void
    {
        $tracker = new StabilityTracker(2, self::TOLERANCE);

        $reading = $tracker->record(ScaleReading::failure('sin conexión'));

        $this->assertFalse($reading->stable);
        $this->assertFalse($reading->success);
    }

    private function reading(float $weight): ScaleReading
    {
        return ScaleReading::success($weight, (string) (int) round($weight * 100), 'kg');
    }
}
