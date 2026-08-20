<?php

namespace Tests\Feature;

use App\Filament\Resources\CashRegisters\Pages\CreateCashRegister;
use App\Filament\Resources\CashRegisters\Pages\EditCashRegister;
use App\Models\CashRegister;
use App\Models\CashRegisterEntry;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashRegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Cortes',
            'slug' => 'cortes',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Asado',
            'sale_price' => 1000,
            'stock' => 100,
        ]);
    }

    /**
     * Sale of $total in $paymentMethod, associated to $register.
     */
    private function sale(CashRegister $register, User $user, Product $product, float $total, string $paymentMethod = 'cash', string $status = 'completed'): Sale
    {
        $sale = Sale::create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'payment_method' => $paymentMethod,
            'subtotal' => $total,
            'total' => $total,
            'status' => $status,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $total,
            'quantity' => 1,
            'subtotal' => $total,
        ]);

        return $sale;
    }

    // Motivo original de este test (portado de punto-manija): un import de clase Filament mal
    // resuelto tira un 500 solo al renderizar el formulario, algo que ni `php -l` ni un
    // `class_exists` genérico detectan.
    public function test_cash_register_pages_render_without_errors(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/admin/cash-registers')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/cash-registers/create')
            ->assertOk();

        $register = CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 1000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->get("/admin/cash-registers/{$register->id}/edit")
            ->assertOk();

        $register->close(1000);

        $this->actingAs($user)
            ->get("/admin/cash-registers/{$register->id}/edit")
            ->assertOk();
    }

    public function test_expected_amount_sums_cash_sales_and_entries_only(): void
    {
        $user = $this->user();
        $product = $this->product();

        $register = CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 1000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->sale($register, $user, $product, 500, paymentMethod: 'cash');
        $this->sale($register, $user, $product, 300, paymentMethod: 'transfer');
        $this->sale($register, $user, $product, 9999, paymentMethod: 'cash', status: 'cancelled');

        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'type' => 'income',
            'amount' => 200,
            'description' => 'Cobro de deuda',
        ]);
        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'type' => 'expense',
            'amount' => 150,
            'description' => 'Compra de bolsas',
        ]);

        // 1000 apertura + 500 venta efectivo + 200 ingreso - 150 egreso
        // (la transferencia y la venta cancelada no cuentan).
        $this->assertEquals(1550.0, $register->calculateExpectedAmount());
    }

    public function test_close_sets_closed_at_automatically_and_computes_difference(): void
    {
        $user = $this->user();

        $register = CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 1000,
            'opened_at' => now()->subHours(3),
            'status' => 'open',
        ]);

        $this->assertNull($register->closed_at);

        $register->close(1200, 'Todo en orden');

        $register->refresh();

        $this->assertEquals('closed', $register->status);
        $this->assertNotNull($register->closed_at);
        $this->assertEquals(1000.0, (float) $register->expected_amount);
        $this->assertEquals(200.0, (float) $register->difference);
        $this->assertEquals('Todo en orden', $register->notes);
    }

    public function test_cannot_open_a_second_cash_register_while_one_is_open(): void
    {
        $user = $this->user();

        CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 0,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        Livewire::actingAs($user)
            ->test(CreateCashRegister::class)
            ->fillForm([
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_amount' => 500,
            ])
            ->call('create');

        $this->assertEquals(1, CashRegister::count());
    }

    public function test_closed_cash_register_cannot_be_edited(): void
    {
        $user = $this->user();

        $register = CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 1000,
            'opened_at' => now()->subHour(),
            'status' => 'open',
        ]);

        $register->close(1000);

        Livewire::actingAs($user)
            ->test(EditCashRegister::class, [
                'record' => $register->getRouteKey(),
            ])
            ->fillForm(['notes' => 'Intento de edición posterior al cierre'])
            ->call('save');

        $this->assertNotEquals(
            'Intento de edición posterior al cierre',
            $register->fresh()->notes,
        );
    }
}
