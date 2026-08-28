<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearVenta;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Scale\ScaleReading;
use App\Services\Scale\ScaleReadingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cubre el flujo de venta por peso sin tocar hardware: se siembra en el store
 * lo que habría publicado el daemon y se opera la página como lo haría el
 * cajero.
 *
 * Cada test que espera una lectura siembra también el latido del vigilante.
 * Sin latido el servicio cae al camino directo e intenta conectarse a la IP
 * real de la configuración, lo que volvería la suite lenta y dependiente de
 * que la balanza esté en la red.
 */
class CrearVentaWeighingTest extends TestCase
{
    use RefreshDatabase;

    private ScaleReadingStore $store;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = app(ScaleReadingStore::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_product_sold_by_weight_waits_for_a_weighing_instead_of_entering_the_cart(): void
    {
        $product = $this->product(['unit' => 'kg']);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->assertSet('cartItems', [])
            ->assertSet('pendingItem.product_id', $product->id)
            ->assertSet('pendingItem.name', 'Asado');
    }

    public function test_a_product_sold_by_unit_goes_straight_to_the_cart(): void
    {
        $product = $this->product(['unit' => 'unidad', 'stock' => 10]);

        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->assertSet('pendingItem', []);

        $this->assertCount(1, $component->get('cartItems'));
        $this->assertSame(1, $component->get('cartItems')[0]['quantity']);
        $this->assertNull($component->get('cartItems')[0]['weight_source']);
    }

    public function test_capturing_freezes_the_weight_the_server_reads(): void
    {
        $product = $this->product(['unit' => 'kg', 'sale_price' => 9000]);
        $this->publish('main', 1.25, '125', stable: true);

        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            // El navegador solo manda las cuentas crudas que mostró; el peso
            // lo pone el servidor.
            ->call('capturarPeso', 'main', '125')
            ->assertSet('pendingItem', []);

        $item = $component->get('cartItems')[0];

        $this->assertSame(1.25, $item['quantity']);
        $this->assertSame(11250.0, $item['subtotal']);
        $this->assertSame(SaleItem::SOURCE_SCALE, $item['weight_source']);
        $this->assertSame('main', $item['scale_connection']);
        $this->assertSame('125', $item['raw_reading']);
    }

    public function test_it_ignores_the_weight_the_browser_claims(): void
    {
        $product = $this->product(['unit' => 'kg', 'sale_price' => 9000]);
        $this->publish('main', 2.0, '200', stable: true);

        // Un cliente manipulado no puede cobrar menos de lo que hay en el
        // plato: la cantidad sale de la lectura del servidor, no del request.
        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '200');

        $this->assertSame(2.0, $component->get('cartItems')[0]['quantity']);
    }

    public function test_it_refuses_to_capture_when_the_weight_changed_after_being_shown(): void
    {
        $product = $this->product(['unit' => 'kg']);
        $this->publish('main', 1.25, '125', stable: true);

        // El operador vio 3,00 kg y la balanza ahora marca 1,25: el carnicero
        // retiró la carne. Cobrar cualquiera de los dos números sería cobrar
        // un peso que nadie miró.
        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '300')
            ->assertSet('cartItems', [])
            ->assertSet('pendingItem.product_id', $product->id);
    }

    public function test_it_tolerates_a_flicker_of_one_division(): void
    {
        config()->set('scale.stability.tolerance_divisions', 1);

        $product = $this->product(['unit' => 'kg']);
        $this->publish('main', 1.25, '125', stable: true);

        // Una división de diferencia es el temblor normal del plato, no un
        // cambio de producto: rechazarlo obligaría a reintentar sin motivo.
        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '124');

        $this->assertCount(1, $component->get('cartItems'));
    }

    public function test_it_refuses_to_capture_an_unstable_weight_when_the_watcher_is_running(): void
    {
        $product = $this->product(['unit' => 'kg']);
        $this->publish('main', 1.25, '125', stable: false);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '125')
            ->assertSet('cartItems', []);
    }

    public function test_it_refuses_to_capture_zero(): void
    {
        $product = $this->product(['unit' => 'kg']);
        $this->publish('main', 0.0, '0', stable: true);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '0')
            ->assertSet('cartItems', []);
    }

    public function test_it_refuses_to_capture_more_than_the_available_stock(): void
    {
        $product = $this->product(['unit' => 'kg', 'stock' => 1.0]);
        $this->publish('main', 2.5, '250', stable: true);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '250')
            ->assertSet('cartItems', []);
    }

    public function test_each_weighing_is_its_own_line(): void
    {
        $product = $this->product(['unit' => 'kg', 'stock' => 10]);

        $component = Livewire::test(CrearVenta::class);

        $this->publish('main', 1.0, '100', stable: true);
        $component->call('addToCart', $product->id)->call('capturarPeso', 'main', '100');

        $this->publish('main', 2.0, '200', stable: true);
        $component->call('addToCart', $product->id)->call('capturarPeso', 'main', '200');

        // Dos paquetes pesados por separado son dos pesajes distintos:
        // fusionarlos borraría de dónde salió cada peso.
        $this->assertCount(2, $component->get('cartItems'));
    }

    public function test_stock_is_validated_against_every_line_of_the_same_product(): void
    {
        $product = $this->product(['unit' => 'kg', 'stock' => 3.0]);

        $component = Livewire::test(CrearVenta::class);

        $this->publish('main', 2.0, '200', stable: true);
        $component->call('addToCart', $product->id)->call('capturarPeso', 'main', '200');

        // El segundo pesaje entra en el stock por sí solo, pero sumado al
        // primero lo supera. Validar línea por línea lo dejaría pasar y la
        // venta explotaría al confirmar.
        $component->call('addToCart', $product->id)->call('capturarPeso', 'main', '200');

        $this->assertCount(1, $component->get('cartItems'));
    }

    public function test_it_converts_the_weight_to_the_unit_the_product_is_sold_in(): void
    {
        // Balanza en kilos, producto en gramos: si no se convirtiera, se
        // cobrarían 1,25 g de fiambre en vez de 1250.
        $product = $this->product(['unit' => 'g', 'sale_price' => 9, 'stock' => 5000]);
        $this->publish('main', 1.25, '125', stable: true);

        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '125');

        $item = $component->get('cartItems')[0];

        $this->assertSame(1250.0, $item['quantity']);
        $this->assertSame(11250.0, $item['subtotal']);
    }

    public function test_the_projected_price_is_expressed_per_unit_of_the_scale(): void
    {
        $product = $this->product(['unit' => 'g', 'sale_price' => 9, 'stock' => 5000]);

        $component = Livewire::test(CrearVenta::class)->call('addToCart', $product->id);

        // La pantalla multiplica el peso en kg por este número, así que tiene
        // que ser el precio por kilo y no el precio por gramo.
        $this->assertSame(9000.0, $component->instance()->pendingPricePerScaleUnit());
    }

    public function test_a_manual_weight_is_not_converted_because_it_is_typed_in_the_product_unit(): void
    {
        $product = $this->product(['unit' => 'g', 'sale_price' => 9, 'stock' => 5000]);

        // El campo está rotulado en gramos: convertirlo como si fuera una
        // lectura de balanza multiplicaría por mil lo que el operador tipeó.
        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('activarPesoManual')
            ->set('manualWeightValue', '500')
            ->call('usarPesoManual');

        $this->assertSame(500.0, $component->get('cartItems')[0]['quantity']);
    }

    public function test_a_manual_weight_is_recorded_as_manual(): void
    {
        $product = $this->product(['unit' => 'kg', 'sale_price' => 9000]);

        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('activarPesoManual')
            ->set('manualWeightValue', '1,5')
            ->call('usarPesoManual');

        $item = $component->get('cartItems')[0];

        $this->assertSame(1.5, $item['quantity']);
        $this->assertSame(SaleItem::SOURCE_MANUAL, $item['weight_source']);
        $this->assertNull($item['scale_connection']);
    }

    public function test_editing_the_quantity_of_a_weighed_item_marks_it_as_manual(): void
    {
        $product = $this->product(['unit' => 'kg', 'stock' => 10]);
        $this->publish('main', 1.25, '125', stable: true);

        // Si el número ya no es el que dio la balanza, la trazabilidad tiene
        // que decirlo: si no, un peso tipeado quedaría indistinguible de uno
        // pesado.
        $component = Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '125')
            ->call('updateQuantity', 0, '2.5');

        $this->assertSame(SaleItem::SOURCE_MANUAL, $component->get('cartItems')[0]['weight_source']);
        $this->assertNull($component->get('cartItems')[0]['raw_reading']);
    }

    public function test_it_will_not_charge_while_a_weighing_is_unfinished(): void
    {
        $this->openCashRegister();

        $unit = $this->product(['unit' => 'unidad', 'stock' => 5, 'sku' => 'U-1']);
        $weighed = $this->product(['unit' => 'kg', 'name' => 'Vacío', 'sku' => 'K-1']);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $unit->id)
            ->set('paymentMethod', 'cash')
            ->call('addToCart', $weighed->id)
            ->call('confirmSale');

        // Cobrar con un pesaje a medias dejaría la venta sin ese producto y
        // el cajero no se enteraría hasta mirar el ticket.
        $this->assertSame(0, Sale::count());
    }

    public function test_the_weighing_trail_is_persisted_with_the_sale(): void
    {
        $this->openCashRegister();

        $product = $this->product(['unit' => 'kg', 'sale_price' => 9000, 'stock' => 10]);
        $this->publish('main', 1.25, '125', stable: true);

        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('capturarPeso', 'main', '125')
            ->set('paymentMethod', 'cash')
            ->set('printTicket', false)
            ->call('confirmSale');

        $item = SaleItem::firstOrFail();

        $this->assertSame('1.250', $item->quantity);
        $this->assertSame('kg', $item->unit);
        $this->assertSame(SaleItem::SOURCE_SCALE, $item->weight_source);
        $this->assertSame('main', $item->scale_connection);
        $this->assertSame('125', $item->raw_reading);
        $this->assertNotNull($item->weighed_at);

        $this->assertEquals(8.75, Product::find($product->id)->stock);
    }

    public function test_the_panel_shows_the_scales_and_the_weighing_controls(): void
    {
        config()->set('scale.connections.main.label', 'Mostrador 1');
        config()->set('scale.connections.scale_2.label', 'Mostrador 2');

        $product = $this->product(['unit' => 'kg']);

        Livewire::test(CrearVenta::class)
            ->assertSee('Mostrador 1')
            ->assertSee('Mostrador 2')
            ->call('addToCart', $product->id)
            ->assertSee('Tomar peso')
            ->assertSee('Peso a mano');
    }

    public function test_it_falls_back_to_manual_when_the_scale_cannot_weigh_in_the_product_unit(): void
    {
        config()->set('scale.connections.main.unit', 'litro');

        $product = $this->product(['unit' => 'kg']);

        // Dejar al operador frente a un botón que nunca se habilita es peor
        // que mandarlo directo a la carga manual con el motivo a la vista.
        Livewire::test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->assertSet('manualWeightMode', true)
            ->assertSee('no puede pesar');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Asado',
            'sku' => 'ASADO-'.fake()->unique()->numberBetween(1, 99999),
            'unit' => 'kg',
            'cost_price' => 5000,
            'sale_price' => 9000,
            'stock' => 5,
            'min_stock' => 0,
            'active' => true,
        ], $attributes));
    }

    /** Simula una lectura publicada por un daemon vivo. */
    private function publish(string $connection, float $weight, string $raw, bool $stable): void
    {
        $this->store->heartbeat($connection);
        $this->store->put($connection, ScaleReading::success($weight, $raw, 'kg', stable: $stable));
    }

    private function openCashRegister(): CashRegister
    {
        return CashRegister::create([
            'user_id' => $this->user->id,
            'opening_amount' => 0,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }
}
