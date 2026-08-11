<?php

namespace App\Services\Tickets;

use App\Models\Sale;
use App\Models\SaleItem;

class SaleTicketEscPosBuilder
{
    /**
     * Según la ficha técnica real de la impresora (Inkspire, clon OCPP-58H):
     * papel de 58 mm, 57.5 mm imprimibles, resolución de 384 puntos por
     * línea. En fuente A (12x24 puntos, la fuente estándar) entran
     * 384 / 12 = 32 caracteres por línea. A doble ancho el texto queda
     * demasiado grande y corta palabras a la mitad, así que solo se usa
     * negrita/doble alto (sin duplicar el ancho) para destacar.
     */
    private const WIDTH = 32;

    private const BOLD = 8;

    private const BOLD_TALL = 24; // negrita + doble alto, sin duplicar ancho

    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const PAYMENT_LABELS = [
        'cash'     => 'Efectivo',
        'transfer' => 'Transferencia',
        'card'     => 'Tarjeta',
    ];

    public function build(Sale $sale): string
    {
        $sale->loadMissing('items');

        $ticket = self::ESC.'@'; // Reset impresora

        $ticket .= self::ESC.'!'.chr(self::BOLD_TALL);
        $ticket .= $this->centered(config('store.name'));
        $ticket .= self::ESC.'!'.chr(0);
        $ticket .= $this->centered('Comprobante no válido como factura');
        $ticket .= $this->centered($sale->created_at->format('d/m/Y H:i'));
        $ticket .= $this->centered("Venta {$sale->sale_number}");
        $ticket .= $this->separator();

        foreach ($sale->items as $item) {
            $ticket .= $this->itemLine($item);
        }

        $ticket .= $this->separator();
        $ticket .= $this->totalLine('Subtotal', (float) $sale->subtotal);

        if ((float) $sale->discount > 0) {
            $ticket .= $this->totalLine('Descuento', (float) $sale->discount);
        }

        $ticket .= self::ESC.'!'.chr(self::BOLD);
        $ticket .= $this->totalLine('TOTAL', (float) $sale->total);
        $ticket .= self::ESC.'!'.chr(0);
        $ticket .= $this->separator();

        $ticket .= "\n";
        $ticket .= 'Medio de pago: '.(self::PAYMENT_LABELS[$sale->payment_method] ?? $sale->payment_method)."\n";
        $ticket .= "\n";
        $ticket .= $this->centered('¡Gracias por su compra!');
        $ticket .= "\n\n\n";
        $ticket .= self::ESC.'!'.chr(0); // Restablecer tamaño de fuente
        $ticket .= self::GS.'V'.chr(0); // Corte de papel

        return $ticket;
    }

    /**
     * Arma la linea de un item aprovechando el ancho disponible: si el
     * nombre entra junto con el importe en una sola linea, no gasta una
     * linea aparte solo para el detalle de cantidad/precio unitario (que
     * ademas es redundante cuando la cantidad es 1).
     */
    private function itemLine(SaleItem $item): string
    {
        $qty = (float) $item->quantity;
        $qtyLabel = $this->formatNumber($qty);
        $unitPrice = $this->formatNumber((float) $item->unit_price);
        $subtotal = $this->formatNumber((float) $item->subtotal);
        $productName = $this->sanitize($item->product_name);

        // El codigo de barra es lo que el cliente realmente usa para
        // ubicar el producto; el SKU queda como respaldo mientras se
        // completa la carga de codigos de barra en el catalogo.
        $code = $item->barcode ?: $item->sku;
        $codeLine = $code ? '  Cod '.$this->sanitize($code)."\n" : '';

        $head = $qty == 1.0
            ? $productName
            : "{$qtyLabel} x {$productName}";

        if (mb_strlen($head) + 1 + mb_strlen($subtotal) <= self::WIDTH) {
            return $this->padRight($head, self::WIDTH - mb_strlen($subtotal)).$subtotal."\n".$codeLine;
        }

        $detail = $qty == 1.0 ? '' : "{$qtyLabel} x {$unitPrice}";
        $detailLine = $this->padRight($detail, self::WIDTH - mb_strlen($subtotal)).$subtotal."\n";

        return implode("\n", $this->wrap($head))."\n".$detailLine.$codeLine;
    }

    /**
     * str_pad() cuenta bytes, no caracteres: con tildes/ñ (multibyte en
     * UTF-8) desalinea la columna de importes. Este helper rellena segun
     * ancho visual real.
     */
    private function padRight(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    /**
     * Parte $text en lineas de a lo sumo self::WIDTH caracteres, respetando
     * palabras completas (y cortando a la fuerza si una palabra sola supera
     * el ancho del ticket).
     *
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

    private function totalLine(string $label, float $amount): string
    {
        $right = $this->formatNumber($amount);
        $label = $this->sanitize($label);

        return $this->padRight($label.':', self::WIDTH - mb_strlen($right)).$right."\n";
    }

    /**
     * El firmware de esta impresora (clon OCPP-58H) no interpreta la
     * codificación UTF-8: los caracteres multibyte (tildes, ñ, ¡, ¿) se
     * imprimen como bytes sueltos ilegibles y además rompen el conteo de
     * ancho de línea, provocando cortes de palabra. Se translitera todo el
     * texto a ASCII antes de mandarlo a la impresora.
     */
    private function sanitize(string $text): string
    {
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
            '¡' => '', '¿' => '',
        ];

        return strtr($text, $map);
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * Centra $text a mano en vez de usar el comando ESC/POS "ESC a" (justificar):
     * el firmware de esta impresora (clon OCPP-58H) no lo soporta y termina
     * imprimiendo los bytes del comando como texto literal en vez de
     * ejecutarlo. Envuelve el texto si no entra en el ancho del ticket.
     */
    private function centered(string $text): string
    {
        $out = '';

        foreach ($this->wrap($this->sanitize($text)) as $line) {
            $padding = max(0, intdiv(self::WIDTH - mb_strlen($line), 2));
            $out .= str_repeat(' ', $padding).$line."\n";
        }

        return $out;
    }

    private function separator(): string
    {
        return str_repeat('-', self::WIDTH)."\n";
    }
}
