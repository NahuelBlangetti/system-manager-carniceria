<?php

namespace App\Services\Scale;

/**
 * Una lectura de balanza, exitosa o fallida.
 *
 * `readAt` guarda microtime(true) y no un Carbon: la antigüedad de la
 * lectura se mide en decenas de milisegundos, y es la base tanto del
 * debounce sub-segundo como de la detección de lecturas rancias cuando el
 * daemon se muere. El TTL del caché de Laravel solo tiene granularidad de
 * segundos, así que la marca de tiempo tiene que viajar dentro del dato.
 */
final class ScaleReading
{
    private function __construct(
        public readonly bool $success,
        public readonly ?float $weight,
        public readonly ?string $raw,
        public readonly ?string $unit,
        public readonly bool $stable,
        public readonly float $readAt,
        public readonly ?string $message,
    ) {}

    public static function success(
        float $weight,
        string $raw,
        string $unit,
        bool $stable = false,
        ?float $readAt = null,
    ): self {
        return new self(true, $weight, $raw, $unit, $stable, $readAt ?? microtime(true), null);
    }

    public static function failure(string $message, ?float $readAt = null): self
    {
        return new self(false, null, null, null, false, $readAt ?? microtime(true), $message);
    }

    public function withStable(bool $stable): self
    {
        return new self(
            $this->success,
            $this->weight,
            $this->raw,
            $this->unit,
            $stable,
            $this->readAt,
            $this->message,
        );
    }

    /**
     * Decimales con los que hay que mostrar el peso para que coincida con
     * el display de la balanza.
     *
     * La trama Systel no trae la unidad ni la coma: trae cuentas enteras
     * (p. ej. "1250") y el divisor de la configuración dice cuánto vale
     * cada cuenta. Divisor 100 → 12,50 kg (2 decimales); divisor 1000 →
     * 1,250 kg (3 decimales). Si se muestran de más o de menos, el número
     * de la pantalla no es el que el carnicero está mirando.
     */
    public static function displayDecimals(int|float $divisor): int
    {
        $divisor = max(1, (int) $divisor);

        if ($divisor === 1) {
            return 0;
        }

        $decimals = (int) round(log10($divisor));

        // Un divisor que no es potencia de 10 no se puede inferir por el
        // logaritmo. En ese caso se usan 3 decimales, que es lo que el
        // ticket ya imprime para kg.
        if ((int) (10 ** $decimals) !== $divisor) {
            return 3;
        }

        return max(0, min(4, $decimals));
    }

    public function age(): float
    {
        return max(0.0, microtime(true) - $this->readAt);
    }

    /**
     * Forma serializada. Mantiene las claves que ya consumían el
     * controlador y el componente Blade (`success`, `weight`, `raw`,
     * `unit`, `message`) y agrega las nuevas.
     */
    public function toArray(): array
    {
        if (! $this->success) {
            return [
                'success' => false,
                'message' => $this->message,
                'read_at' => $this->readAt,
                'age_ms' => (int) round($this->age() * 1000),
            ];
        }

        return [
            'success' => true,
            'raw' => $this->raw,
            'weight' => $this->weight,
            'unit' => $this->unit,
            'stable' => $this->stable,
            'read_at' => $this->readAt,
            'age_ms' => (int) round($this->age() * 1000),
        ];
    }

    public static function fromArray(array $data): self
    {
        if (! ($data['success'] ?? false)) {
            return new self(
                false,
                null,
                null,
                null,
                false,
                (float) ($data['read_at'] ?? microtime(true)),
                $data['message'] ?? 'Error desconocido.',
            );
        }

        return new self(
            true,
            (float) $data['weight'],
            (string) $data['raw'],
            (string) $data['unit'],
            (bool) ($data['stable'] ?? false),
            (float) ($data['read_at'] ?? microtime(true)),
            null,
        );
    }
}
