<?php

namespace App\Support;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida códigos de barras pensados para impresión CODE39 en impresora
 * térmica ESC/POS. Evita cargar nombres de producto u otros textos
 * que la impresora termina dibujando como letras.
 */
class ProductBarcode implements ValidationRule
{
    /** Caracteres CODE39 sin espacio (los espacios casi nunca vienen de un escáner). */
    public const ALLOWED_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-.$/+%';

    public const MIN_LENGTH = 1;

    public const MAX_LENGTH = 20;

    public function __construct(private readonly ?int $ignoreProductId = null) {}

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value === '' ? null : $value;
    }

    /**
     * @return string|null Mensaje de error, o null si es válido / vacío.
     */
    public static function errorMessage(?string $value, ?int $ignoreProductId = null): ?string
    {
        $code = self::normalize($value);

        if ($code === null) {
            return null;
        }

        $length = strlen($code);

        if ($length < self::MIN_LENGTH) {
            return 'El código es demasiado corto (mínimo ' . self::MIN_LENGTH . ' caracteres). Escaneá el código del producto.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'El código es demasiado largo (máximo ' . self::MAX_LENGTH . ' caracteres). ¿Estás escribiendo el nombre del producto en vez del código?';
        }

        if (! preg_match('/^[' . preg_quote(self::ALLOWED_CHARS, '/') . ']+$/', $code)) {
            return 'El código solo puede tener letras, números y los símbolos - . $ / + %. Sacá espacios y tildes.';
        }

        if (! preg_match('/\d/', $code)) {
            return 'El código debe incluir al menos un número. No uses el nombre del producto.';
        }

        // Muchas letras seguidas sin números suelen ser un nombre mal cargado
        // (ej. ATEXPROFEXT/INT). Los códigos reales suelen ser numéricos o
        // alfanuméricos cortos con dígitos intercalados.
        if (preg_match('/[A-Z]{8,}/', $code)) {
            return 'Ese valor parece un nombre, no un código. Escaneá el código de la balanza o la etiqueta del proveedor.';
        }

        $exists = Product::query()
            ->whereRaw('UPPER(barcode) = ?', [$code])
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($exists) {
            return 'Ese código ya está asignado a otro producto.';
        }

        return null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = self::errorMessage(
            is_string($value) || $value === null ? $value : (string) $value,
            $this->ignoreProductId,
        );

        if ($message !== null) {
            $fail($message);
        }
    }
}
