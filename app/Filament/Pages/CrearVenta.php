<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\Tickets\SaleTicketEscPosBuilder;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

class CrearVenta extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Nueva Venta';

    protected static ?string $title = 'Nueva Venta';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.crear-venta';

    // Búsqueda / escáner unificado
    public string $productQuery  = '';
    public array  $searchResults = [];

    // Carrito
    public array $cartItems = [];

    // Pago
    public string $paymentMethod = '';
    public string $notes = '';
    public bool $printTicket = true;

    // Número de venta creada (para confirmación)
    public ?string $lastSaleNumber = null;

    // ── Getters reactivos ──────────────────────────────────────────────

    public function getSubtotal(): float
    {
        return collect($this->cartItems)->sum('subtotal');
    }

    public function getCartCount(): int
    {
        return count($this->cartItems);
    }

    #[Computed]
    public function hasCashRegisterOpen(): bool
    {
        return CashRegister::where('status', 'open')->exists();
    }

    // ── Búsqueda / escáner unificado ───────────────────────────────────

    public function updatedProductQuery(): void
    {
        $query = trim($this->productQuery);

        if (strlen($query) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->findProducts($query);
    }

    public function addProduct(): void
    {
        $query = trim($this->productQuery);

        if ($query === '') {
            return;
        }

        $exact = Product::where('active', true)
            ->where(fn ($q) => $q->where('barcode', $query)->orWhere('sku', $query))
            ->first();

        if ($exact) {
            $this->resetProductSearch();
            $this->addToCart($exact->id);
            $this->dispatch('focus-product-search');

            return;
        }

        if (strlen($query) >= 2 && empty($this->searchResults)) {
            $this->searchResults = $this->findProducts($query);
        }

        if (count($this->searchResults) === 1) {
            $productId = $this->searchResults[0]['id'];
            $this->resetProductSearch();
            $this->addToCart($productId);
            $this->dispatch('focus-product-search');

            return;
        }

        Notification::make()
            ->title('Producto no encontrado')
            ->body(count($this->searchResults) > 1
                ? 'Hay varios resultados. Seleccioná uno de la lista.'
                : "No hay productos para \"{$query}\".")
            ->warning()
            ->send();
    }

    private function findProducts(string $query): array
    {
        return Product::where('active', true)
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('sku', 'like', "%{$query}%")
                ->orWhere('barcode', 'like', "%{$query}%")
            )
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sale_price', 'stock', 'unit', 'sku', 'barcode'])
            ->toArray();
    }

    private function resetProductSearch(): void
    {
        $this->productQuery  = '';
        $this->searchResults = [];
    }

    // ── Carrito ────────────────────────────────────────────────────────

    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $existingIndex = collect($this->cartItems)
            ->search(fn ($item) => $item['product_id'] === $productId);

        if ($product->sale_price <= 0) {
            Notification::make()
                ->title("Precio en $0: {$product->name}")
                ->body('Este producto no tiene precio de venta cargado. Verificá el producto antes de vender.')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($existingIndex !== false) {
            $newQty         = $this->cartItems[$existingIndex]['quantity'] + 1;
            $availableStock = $this->cartItems[$existingIndex]['stock'];

            if ($newQty > $availableStock) {
                Notification::make()
                    ->title("Stock insuficiente: {$product->name}")
                    ->body("Solo hay {$availableStock} {$product->unit} disponibles.")
                    ->warning()
                    ->send();

                return;
            }

            $this->cartItems[$existingIndex]['quantity'] = $newQty;
            $this->cartItems[$existingIndex]['subtotal']  = $newQty * $this->cartItems[$existingIndex]['unit_price'];
        } else {
            if ($product->stock <= 0) {
                Notification::make()
                    ->title("Sin stock: {$product->name}")
                    ->body('Este producto no tiene unidades disponibles.')
                    ->warning()
                    ->send();

                return;
            }

            $this->cartItems[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'unit'       => $product->unit,
                'unit_price' => (float) $product->sale_price,
                'quantity'   => 1,
                'subtotal'   => (float) $product->sale_price,
                'stock'      => $product->stock,
            ];
        }

        $this->resetProductSearch();
        $this->dispatch('focus-product-search');

        Notification::make()
            ->title("{$product->name} agregado")
            ->success()
            ->duration(1500)
            ->send();
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
    }

    public function updateQuantity(int $index, mixed $quantity): void
    {
        $qty = round((float) str_replace(',', '.', (string) $quantity), 3);

        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $availableStock = $this->cartItems[$index]['stock'];

        if ($qty > $availableStock) {
            Notification::make()
                ->title('Stock insuficiente')
                ->body('Solo hay ' . Product::formatQuantity($this->cartItems[$index]['unit'], $availableStock) . ' disponibles.')
                ->warning()
                ->send();

            return;
        }

        $this->cartItems[$index]['quantity'] = $qty;
        $this->cartItems[$index]['subtotal'] = $qty * $this->cartItems[$index]['unit_price'];
    }

    public function clearCart(): void
    {
        $this->cartItems    = [];
        $this->paymentMethod = '';
        $this->notes         = '';
        $this->lastSaleNumber = null;
    }

    // ── Confirmar venta ───────────────────────────────────────────────

    public function confirmSale(): void
    {
        if (empty($this->cartItems)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();

            return;
        }

        if (empty($this->paymentMethod)) {
            Notification::make()->title('Seleccioná un método de pago')->warning()->send();

            return;
        }

        $cashRegister = CashRegister::where('status', 'open')->first();

        if (! $cashRegister) {
            Notification::make()
                ->title('No hay caja abierta')
                ->body('Debés abrir una caja antes de registrar una venta.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $sale = null;

        try {
            DB::transaction(function () use ($cashRegister, &$sale) {
                // Lock all products upfront and validate stock before crear nada
                $products = [];

                foreach ($this->cartItems as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if (! $product || $product->stock < $item['quantity']) {
                        $available = $product ? $product->stock : 0;

                        throw new \RuntimeException(
                            "Stock insuficiente para \"{$item['name']}\". " .
                            'Disponible: ' . Product::formatQuantity($item['unit'], $available) . '. ' .
                            "En carrito: {$item['quantity']}."
                        );
                    }

                    $products[$item['product_id']] = $product;
                }

                $subtotal = $this->getSubtotal();

                $sale = Sale::create([
                    'user_id'          => Auth::id(),
                    'cash_register_id' => $cashRegister->id,
                    'payment_method'   => $this->paymentMethod,
                    'subtotal'         => $subtotal,
                    'discount'         => 0,
                    'total'            => $subtotal,
                    'notes'            => $this->notes,
                    'status'           => 'completed',
                ]);

                foreach ($this->cartItems as $item) {
                    $product = $products[$item['product_id']];

                    SaleItem::create([
                        'sale_id'      => $sale->id,
                        'product_id'   => $item['product_id'],
                        'product_name' => $item['name'],
                        'sku'          => $product->sku,
                        'barcode'      => $product->barcode,
                        'unit_price'   => $item['unit_price'],
                        'quantity'     => $item['quantity'],
                        'subtotal'     => $item['subtotal'],
                    ]);

                    $stockBefore = $product->stock;
                    $product->decrement('stock', $item['quantity']);

                    StockMovement::create([
                        'product_id'     => $product->id,
                        'user_id'        => Auth::id(),
                        'type'           => 'out',
                        'quantity'       => $item['quantity'],
                        'stock_before'   => $stockBefore,
                        'stock_after'    => $stockBefore - $item['quantity'],
                        'notes'          => "Venta {$sale->sale_number}",
                        'reference_type' => Sale::class,
                        'reference_id'   => $sale->id,
                    ]);

                    $this->consumeFromBatches($product, (float) $item['quantity']);
                }

                $this->lastSaleNumber = $sale->sale_number;
            });
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('No se pudo registrar la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cartItems     = [];
        $this->paymentMethod = '';
        $this->notes         = '';

        if ($this->printTicket) {
            $ticket = app(SaleTicketEscPosBuilder::class)->build($sale);
            $this->dispatch('print-escpos-ticket', content: $ticket);
        }

        Notification::make()
            ->title("¡Venta {$this->lastSaleNumber} registrada!")
            ->body('El stock fue actualizado automáticamente.')
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * Descuenta la venta de los lotes con vencimiento más próximo (best-effort:
     * si el producto no tiene lotes cargados, no hace nada). Nunca debe poder
     * abortar la venta, por eso el try/catch silencioso.
     */
    private function consumeFromBatches(Product $product, float $qty): void
    {
        try {
            $batches = ProductBatch::where('product_id', $product->id)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->orderByRaw('expires_at IS NULL, expires_at ASC')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($qty <= 0) {
                    break;
                }

                $consumed = min($qty, (float) $batch->remaining_quantity);
                $remaining = (float) $batch->remaining_quantity - $consumed;

                $batch->update([
                    'remaining_quantity' => $remaining,
                    'status'             => $remaining <= 0 ? 'depleted' : 'active',
                ]);

                $qty -= $consumed;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo descontar de los lotes de vencimiento', [
                'product_id' => $product->id,
                'qty'        => $qty,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
