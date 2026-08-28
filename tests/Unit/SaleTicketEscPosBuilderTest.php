<?php

namespace Tests\Unit;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Tickets\SaleTicketEscPosBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * El ticket es el único comprobante que se lleva el cliente, así que la
 * cantidad impresa tiene que coincidir con lo que marcó la balanza. No toca la
 * base: la relación de ítems se setea a mano.
 */
class SaleTicketEscPosBuilderTest extends TestCase
{
    public function test_a_weighed_item_prints_grams_and_the_unit(): void
    {
        // Con dos decimales, 1,255 kg se imprimiría como 1,25 y el ticket no
        // coincidiría con el display que vio el cliente.
        $ticket = $this->build([
            $this->item(['product_name' => 'Asado', 'unit' => 'kg', 'quantity' => 1.255, 'unit_price' => 9000, 'subtotal' => 11295]),
        ]);

        $this->assertStringContainsString('1,255 kg x Asado', $ticket);
    }

    public function test_a_weighed_item_prints_the_quantity_even_when_it_is_exactly_one(): void
    {
        // En una venta por peso, "1,000 kg" es justamente el dato a verificar:
        // omitirlo dejaría el ticket sin el peso cobrado.
        $ticket = $this->build([
            $this->item(['product_name' => 'Vacio', 'unit' => 'kg', 'quantity' => 1.0, 'unit_price' => 9000, 'subtotal' => 9000]),
        ]);

        $this->assertStringContainsString('1,000 kg x Vacio', $ticket);
    }

    public function test_a_single_unit_item_omits_the_quantity(): void
    {
        $ticket = $this->build([
            $this->item(['product_name' => 'Carbon', 'unit' => 'unidad', 'quantity' => 1.0, 'unit_price' => 3000, 'subtotal' => 3000]),
        ]);

        $this->assertStringNotContainsString('x Carbon', $ticket);
        $this->assertStringContainsString('Carbon', $ticket);
    }

    public function test_several_unit_items_print_a_whole_quantity(): void
    {
        // Tres bolsas de carbon son "3", no "3,00": los decimales solo tienen
        // sentido en lo que se vende pesado.
        $ticket = $this->build([
            $this->item(['product_name' => 'Carbon', 'unit' => 'unidad', 'quantity' => 3.0, 'unit_price' => 3000, 'subtotal' => 9000]),
        ]);

        $this->assertStringContainsString('3 x Carbon', $ticket);
    }

    public function test_an_item_without_unit_keeps_the_old_format(): void
    {
        // Las ventas anteriores a la columna `unit` no tienen unidad guardada;
        // su ticket reimpreso no debería cambiar de forma.
        $ticket = $this->build([
            $this->item(['product_name' => 'Historico', 'unit' => null, 'quantity' => 2.0, 'unit_price' => 1000, 'subtotal' => 2000]),
        ]);

        $this->assertStringContainsString('2,00 x Historico', $ticket);
    }

    /** @param  list<SaleItem>  $items */
    private function build(array $items): string
    {
        $sale = new Sale([
            'payment_method' => 'cash',
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
        ]);

        $sale->sale_number = 'V-000001';
        $sale->created_at = now();
        $sale->setRelation('items', new Collection($items));

        return app(SaleTicketEscPosBuilder::class)->build($sale);
    }

    private function item(array $attributes): SaleItem
    {
        return new SaleItem($attributes);
    }
}
