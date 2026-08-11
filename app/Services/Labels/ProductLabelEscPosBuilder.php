<?php

namespace App\Services\Labels;

use App\Models\Product;
use App\Support\ProductBarcode;
use InvalidArgumentException;

class ProductLabelEscPosBuilder
{
    /**
     * Misma impresora que SaleTicketEscPosBuilder (Inkspire, clon OCPP-58H):
     * 58 mm de papel, 32 caracteres por línea en fuente A. No es una
     * etiqueta autoadhesiva como la Zebra: es una tira del rollo continuo,
     * cortada con GS V.
     */
    private const WIDTH = 32;

    private const BOLD = 8;

    private const ESC = "\x1B";

    private const GS = "\x1D";

    /**
     * Caracteres válidos para CODE39: dígitos, mayúsculas y $ % + - . / y
     * espacio. Es el símbolo de barras más compatible con clones ESC/POS
     * baratos (soportado desde el comando "GS k" original, sin necesitar
     * la variante extendida que algunos firmwares no implementan).
     */
    private const CODE39_CHARS = ProductBarcode::ALLOWED_CHARS;

    public function build(Product $product, int $copies = 1): string
    {
        if (blank($product->barcode)) {
            throw new InvalidArgumentException("El producto \"{$product->name}\" no tiene código de barras asignado.");
        }

        if ($error = ProductBarcode::errorMessage($product->barcode, $product->id)) {
            throw new InvalidArgumentException("\"{$product->name}\": {$error}");
        }

        $copies = max(1, $copies);
        $label = $this->buildOne($product);

        return str_repeat($label, $copies);
    }

    /**
     * @param  iterable<array{0: Product, 1: int}>  $items
     */
    public function buildMany(iterable $items): string
    {
        $out = '';

        foreach ($items as [$product, $copies]) {
            $out .= $this->build($product, $copies);
        }

        return $out;
    }

    private function buildOne(Product $product): string
    {
        $code = $this->sanitizeCode((string) $product->barcode);

        $label = self::ESC.'@'; // Reset impresora

        $label .= self::ESC.'!'.chr(self::BOLD);
        foreach ($this->wrap($this->sanitizeText($product->name)) as $line) {
            $label .= $line."\n";
        }
        $label .= self::ESC.'!'.chr(0);

        $label .= "\n";
        $label .= $this->barcode($code);
        $label .= "\n\n";

        $label .= self::GS.'V'.chr(0); // Corte de papel

        return $label;
    }

    /**
     * "GS k" formato clásico (m 0-6, datos terminados en NUL): es la
     * variante que más clones ESC/POS baratos implementan, a diferencia
     * del formato extendido (m 65+) que requiere firmware más completo.
     * m=4 selecciona CODE39. GS h/w fijan alto/ancho de módulo, GS H
     * habilita el texto legible (HRI) debajo del símbolo.
     */
    private function barcode(string $code): string
    {
        $out = self::GS.'h'.chr(60); // alto del codigo: 60 puntos
        $out .= self::GS.'w'.chr(2); // ancho de modulo: 2 (angosto, para que 32 col alcancen)
        $out .= self::GS.'H'.chr(2); // HRI debajo del codigo
        $out .= self::GS.'f'.chr(0); // fuente A para el HRI
        $out .= self::GS.'k'.chr(4).$code.chr(0);

        return $out;
    }

    /**
     * CODE39 solo admite el set en self::CODE39_CHARS; cualquier otro
     * caracter (tildes, minúsculas, símbolos raros de un SKU cargado a
     * mano) se transforma o se descarta para no mandarle a la impresora
     * datos que el símbolo no puede codificar.
     */
    private function sanitizeCode(string $code): string
    {
        $normalized = ProductBarcode::normalize($code);

        if ($normalized === null) {
            throw new InvalidArgumentException('El código de barras no tiene caracteres válidos para CODE39.');
        }

        return $normalized;
    }

    private function sanitizeText(string $text): string
    {
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
            '¡' => '', '¿' => '',
        ];

        return strtr($text, $map);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            while (mb_strlen($word) > self::WIDTH) {
                $lines[] = mb_substr($word, 0, self::WIDTH);
                $word = mb_substr($word, self::WIDTH);
            }

            $candidate = $current === '' ? $word : "{$current} {$word}";

            if (mb_strlen($candidate) > self::WIDTH) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }
}
