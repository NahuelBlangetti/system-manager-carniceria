<?php

namespace Tests\Unit;

use App\Services\Scale\ScaleReading;
use Tests\TestCase;

class ScaleReadingDisplayTest extends TestCase
{
    public function test_the_display_decimals_match_the_scale_division(): void
    {
        // Cada cuenta de la trama vale 1 / divisor. Esos son los dígitos
        // que hay que mostrar para que el número coincida con el LCD.
        $this->assertSame(0, ScaleReading::displayDecimals(1));
        $this->assertSame(1, ScaleReading::displayDecimals(10));
        $this->assertSame(2, ScaleReading::displayDecimals(100));
        $this->assertSame(3, ScaleReading::displayDecimals(1000));
    }
}
