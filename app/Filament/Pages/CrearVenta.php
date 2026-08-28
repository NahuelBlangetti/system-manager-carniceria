<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\ScaleService;
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

    /**
     * Margen al comparar cantidades contra el stock. El stock se guarda con
     * tres decimales y el peso llega como un float dividido por el divisor de
     * la balanza, así que una comparación exacta puede rechazar una venta por
     * ruido de punto flotante. Medio gramo es indetectable en el mostrador y
     * elimina el problema.
     */
    private const STOCK_EPSILON = 0.0005;

    // Búsqueda / escáner unificado
    public string $productQuery = '';

    public array $searchResults = [];

    /**
     * Producto elegido que todavía no tiene cantidad porque se vende por
     * peso. No entra al carrito hasta que se congela un peso.
     *
     * Es el corazón del flujo: el peso en vivo no puede ser la cantidad de un
     * ítem del carrito, porque cuando el carnicero retira la carne para pasar
     * al producto siguiente el ítem se iría a cero.
     */
    public array $pendingItem = [];

    /** Balanza desde la que se toma el peso. Cada mostrador tiene la suya. */
    public string $selectedScale = '';

    /** Se habilita solo si la balanza no responde y hay que seguir vendiendo. */
    public bool $manualWeightMode = false;

    public string $manualWeightValue = '';

    // Carrito
    public array $cartItems = [];

    // Pago
    public string $paymentMethod = '';

    public string $notes = '';

    public bool $printTicket = true;

    // Número de venta creada (para confirmación)
    public ?string $lastSaleNumber = null;

    public function mount(): void
    {
        $this->selectedScale = (string) config('scale.default');
    }

    // ── Getters reactivos ──────────────────────────────────────────────

    public function getSubtotal(): float
    {
        return round(collect($this->cartItems)->sum('subtotal'), 2);
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

    /** @return array<string, string> Nombre de conexión => etiqueta para el operador. */
    #[Computed]
    public function scales(): array
    {
        $labels = [];

        foreach (array_keys(config('scale.connections', [])) as $index => $connection) {
            $labels[$connection] = config("scale.connections.{$connection}.label")
                ?? 'Mostrador '.($index + 1);
        }

        return $labels;
    }

    /**
     * Productos con "código rápido": un barcode corto y numérico (1, 2, 3…),
     * pensado para tocarse o tipearse igual que el código PLU que se cargaba
     * a mano en la balanza. Es esa misma idea, resuelta del lado del sistema
     * en vez de sincronizada con el hardware — el campo es el mismo `barcode`
     * que ya usa `addProduct()` para el match exacto por escáner.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    #[Computed]
    public function frequentProducts(): array
    {
        return Product::query()
            ->where('active', true)
            ->whereNotNull('barcode')
            ->whereRaw("barcode REGEXP '^[0-9]{1,2}$'")
            ->orderByRaw('CAST(barcode AS UNSIGNED)')
            ->limit(24)
            ->get(['id', 'name', 'barcode'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'code' => $product->barcode,
                'name' => $product->name,
            ])
            ->all();
    }

    /**
     * No es #[Computed] a propósito: los computados de Livewire se memorizan
     * durante todo el request, y esto se consulta antes y después de mutar
     * `pendingItem` en la misma acción.
     */
    public function isWeighingPending(): bool
    {
        return $this->pendingItem !== [];
    }

    /**
     * Cuántas unidades del producto equivale una unidad de la balanza
     * seleccionada, o null si la combinación no se soporta.
     *
     * La pantalla lo necesita para proyectar el importe: si el producto se
     * vende por gramo y la balanza informa kilos, multiplicar el peso por el
     * precio daría un total mil veces menor al que se va a cobrar.
     */
    public function pendingScaleFactor(): ?float
    {
        if (! $this->isWeighingPending()) {
            return null;
        }

        return $this->quantityFactor($this->selectedScale, $this->pendingItem['unit']);
    }

    /** Precio del producto expresado por unidad de la balanza. */
    public function pendingPricePerScaleUnit(): float
    {
        $factor = $this->pendingScaleFactor();

        return $factor === null ? 0.0 : (float) $this->pendingItem['unit_price'] * $factor;
    }

    // ── Búsqueda / escáner unificado ───────────────────────────────────

    public function updatedProductQuery(): void
    {
        // Mientras hay un pesaje pendiente la búsqueda está bloqueada, para
        // que un disparo espurio del escáner no cambie el producto justo
        // antes de que el operador congele el peso.
        if ($this->isWeighingPending()) {
            $this->productQuery = '';

            return;
        }

        $query = trim($this->productQuery);

        if (strlen($query) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->findProducts($query);
    }

    public function addProduct(): void
    {
        if ($this->isWeighingPending()) {
            return;
        }

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

            return;
        }

        if (strlen($query) >= 2 && empty($this->searchResults)) {
            $this->searchResults = $this->findProducts($query);
        }

        if (count($this->searchResults) === 1) {
            $productId = $this->searchResults[0]['id'];
            $this->resetProductSearch();
            $this->addToCart($productId);

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
        $this->productQuery = '';
        $this->searchResults = [];
    }

    // ── Elección de producto ───────────────────────────────────────────

    public function addToCart(int $productId): void
    {
        if ($this->isWeighingPending()) {
            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        if ($product->sale_price <= 0) {
            Notification::make()
                ->title("Precio en \$0: {$product->name}")
                ->body('Este producto no tiene precio de venta cargado. Verificá el producto antes de vender.')
                ->warning()
                ->persistent()
                ->send();
        }

        if ((float) $product->stock <= 0) {
            Notification::make()
                ->title("Sin stock: {$product->name}")
                ->body('Este producto no tiene unidades disponibles.')
                ->warning()
                ->send();

            return;
        }

        $this->resetProductSearch();

        if (SaleItem::isWeighable($product->unit)) {
            $this->startWeighing($product);

            return;
        }

        $this->addUnitItem($product);
    }

    /** Deja el producto esperando peso, sin tocar el carrito todavía. */
    private function startWeighing(Product $product): void
    {
        $this->pendingItem = [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'unit_price' => (float) $product->sale_price,
            'bulk_price_2kg' => $product->bulk_price_2kg !== null ? (float) $product->bulk_price_2kg : null,
            'stock' => (float) $product->stock,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
        ];

        // Si la balanza elegida no puede pesar en la unidad del producto, no
        // se ofrece la captura: el operador va directo al peso manual en vez
        // de quedar frente a un botón que nunca se va a habilitar.
        $this->manualWeightMode = $this->pendingScaleFactor() === null;
        $this->manualWeightValue = '';
    }

    public function cancelarPesaje(): void
    {
        $this->pendingItem = [];
        $this->manualWeightMode = false;
        $this->manualWeightValue = '';
        $this->dispatch('focus-product-search');
    }

    public function seleccionarBalanza(string $connection): void
    {
        if (! config("scale.connections.{$connection}")) {
            return;
        }

        $this->selectedScale = $connection;
    }

    public function activarPesoManual(): void
    {
        $this->manualWeightMode = true;
        $this->manualWeightValue = '';
    }

    public function cancelarPesoManual(): void
    {
        $this->manualWeightMode = false;
        $this->manualWeightValue = '';
    }

    // ── Captura de peso ────────────────────────────────────────────────

    /**
     * Congela el peso de la balanza en un ítem del carrito.
     *
     * El peso NO se toma del cliente: el navegador solo dice "capturá de esta
     * balanza", y el servidor lee el valor autoritativo. `$rawShown` es lo
     * que el operador tenía en pantalla, y se usa como verificación cruzada:
     * si el peso cambió entre que lo vio y que apretó el botón (porque el
     * carnicero retiró la carne), la captura se rechaza en vez de cobrar un
     * número que nadie miró.
     */
    public function capturarPeso(string $connection, string $rawShown): void
    {
        if (! $this->isWeighingPending()) {
            return;
        }

        if (! config("scale.connections.{$connection}")) {
            $this->fail('Balanza desconocida', 'Esa balanza no está configurada.');

            return;
        }

        $scaleService = app(ScaleService::class);
        $reading = $scaleService->read($connection);

        if (! $reading->success) {
            $this->fail('No se pudo leer la balanza', $reading->message);

            return;
        }

        // La estabilidad medida solo es confiable cuando hay un vigilante
        // viendo el flujo completo de lecturas. Sin vigilante siempre viene
        // en falso, y exigirla dejaría al operador sin poder cobrar; en ese
        // caso la verificación cruzada de más abajo cumple el mismo rol,
        // porque un peso que se mueve no va a coincidir con el que se mostró.
        if ($scaleService->watcherIsAlive($connection) && ! $reading->stable) {
            $this->fail('El peso todavía se mueve', 'Esperá a que la balanza se estabilice.');

            return;
        }

        $tolerance = max(1, (int) config('scale.stability.tolerance_divisions', 1));

        if (abs((int) $reading->raw - (int) $rawShown) > $tolerance) {
            $this->fail(
                'El peso cambió',
                'La balanza no marca lo mismo que se mostró en pantalla. Volvé a tomar el peso.'
            );

            return;
        }

        if ((float) $reading->weight <= 0) {
            $this->fail('Peso inválido', 'La balanza marca cero o un valor negativo.');

            return;
        }

        $factor = $this->quantityFactor($connection, $this->pendingItem['unit']);

        if ($factor === null) {
            $this->fail(
                'Unidad incompatible',
                "No se puede pesar un producto en \"{$this->pendingItem['unit']}\" con esta balanza."
            );

            return;
        }

        $this->pushWeighedItem(
            (float) $reading->weight * $factor,
            SaleItem::SOURCE_SCALE,
            $connection,
            $reading->raw,
        );
    }

    /**
     * Alta con peso tipeado a mano, para cuando la balanza no responde y no
     * se puede frenar la venta. Queda marcado como 'manual' para poder
     * auditarlo después.
     */
    public function usarPesoManual(): void
    {
        if (! $this->isWeighingPending()) {
            return;
        }

        // Se tipea directo en la unidad del producto, así que acá no hay
        // conversión: el campo está rotulado con esa unidad.
        $quantity = round((float) str_replace(',', '.', trim($this->manualWeightValue)), 3);

        if ($quantity <= 0) {
            $this->fail('Peso inválido', 'Ingresá un peso mayor que cero.');

            return;
        }

        $this->pushWeighedItem($quantity, SaleItem::SOURCE_MANUAL, null, null);
    }

    /**
     * Agrega el ítem pesado al carrito.
     *
     * Cada pesaje es una línea propia y no se acumula con otra del mismo
     * producto: dos paquetes de asado pesados por separado son dos pesajes
     * distintos, y fusionarlos destruiría la trazabilidad de cada uno.
     */
    private function pushWeighedItem(
        float $quantity,
        string $source,
        ?string $connection,
        ?string $raw,
    ): void {
        $item = $this->pendingItem;
        $quantity = round($quantity, 3);
        $available = $this->availableStockFor($item['product_id'], (float) $item['stock']);

        if ($quantity > $available + self::STOCK_EPSILON) {
            $this->fail(
                'Stock insuficiente',
                'Quedan '.Product::formatQuantity($item['unit'], $available).' de '.$item['name'].
                ' contando lo que ya hay en el carrito.'
            );

            return;
        }

        $unitPrice = $this->resolveUnitPrice($item['unit'], $quantity, $item['unit_price'], $item['bulk_price_2kg']);

        $this->cartItems[] = [
            'product_id' => $item['product_id'],
            'name' => $item['name'],
            'unit' => $item['unit'],
            'unit_price' => $unitPrice,
            'base_unit_price' => $item['unit_price'],
            'bulk_price_2kg' => $item['bulk_price_2kg'],
            'quantity' => $quantity,
            // Se redondea al congelar y no al totalizar: el ticket imprime el
            // subtotal de cada línea, y si solo se redondeara el total la
            // columna no sumaría el TOTAL impreso.
            'subtotal' => round($quantity * $unitPrice, 2),
            'stock' => (float) $item['stock'],
            'weight_source' => $source,
            'scale_connection' => $connection,
            'weighed_at' => now()->toDateTimeString(),
            'raw_reading' => $raw,
        ];

        $this->pendingItem = [];
        $this->manualWeightMode = false;
        $this->manualWeightValue = '';

        Notification::make()
            ->title($item['name'].' · '.Product::formatQuantity($item['unit'], $quantity))
            ->success()
            ->duration(1500)
            ->send();

        $this->dispatch('focus-product-search');
    }

    private function addUnitItem(Product $product): void
    {
        $existingIndex = collect($this->cartItems)
            ->search(fn ($item) => $item['product_id'] === $product->id
                && $item['weight_source'] === null);

        if ($existingIndex !== false) {
            $this->updateQuantity($existingIndex, $this->cartItems[$existingIndex]['quantity'] + 1);
            $this->dispatch('focus-product-search');

            return;
        }

        $this->cartItems[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'unit_price' => (float) $product->sale_price,
            'base_unit_price' => (float) $product->sale_price,
            'bulk_price_2kg' => null,
            'quantity' => 1,
            'subtotal' => round((float) $product->sale_price, 2),
            'stock' => (float) $product->stock,
            'weight_source' => null,
            'scale_connection' => null,
            'weighed_at' => null,
            'raw_reading' => null,
        ];

        Notification::make()
            ->title("{$product->name} agregado")
            ->success()
            ->duration(1500)
            ->send();

        $this->dispatch('focus-product-search');
    }

    /**
     * Factor para pasar del peso que informa la balanza a la unidad en la que
     * se vende el producto.
     *
     * Devuelve null si la combinación no se soporta, en vez de asumir 1: un
     * producto mal configurado se cobraría mil veces de más o de menos, y es
     * mejor negarse a pesar que emitir ese ticket.
     */
    private function quantityFactor(string $connection, string $productUnit): ?float
    {
        $scaleUnit = (string) config("scale.connections.{$connection}.unit", 'kg');

        return match ("{$scaleUnit}=>{$productUnit}") {
            'kg=>kg', 'g=>g' => 1.0,
            'kg=>g' => 1000.0,
            'g=>kg' => 0.001,
            default => null,
        };
    }

    /**
     * Precio por unidad a cobrar según la cantidad.
     *
     * Si el producto tiene precio por 2kg y se pesan 2kg o más, se cobra la
     * mitad de ese precio por cada kg (tarifa más baja que el precio suelto),
     * en vez de precio_suelto × cantidad. Por debajo de 2kg siempre se cobra
     * el precio de venta normal.
     */
    private function resolveUnitPrice(string $unit, float $quantity, float $basePrice, ?float $bulkPrice2kg): float
    {
        if ($unit === 'kg' && $bulkPrice2kg !== null && $bulkPrice2kg > 0 && $quantity >= 2) {
            return round($bulkPrice2kg / 2, 2);
        }

        return $basePrice;
    }

    /**
     * Stock que queda para ese producto descontando lo que ya está en el
     * carrito. Sin esto, dos líneas del mismo producto pasarían la validación
     * por separado y juntas superarían el stock — y con pesajes que no se
     * fusionan, varias líneas del mismo producto son lo habitual.
     */
    private function availableStockFor(int $productId, float $stock): float
    {
        $inCart = collect($this->cartItems)
            ->where('product_id', $productId)
            ->sum('quantity');

        return $stock - (float) $inCart;
    }

    private function fail(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();
    }

    // ── Carrito ────────────────────────────────────────────────────────

    public function removeFromCart(int $index): void
    {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
    }

    public function updateQuantity(int $index, mixed $quantity): void
    {
        if (! isset($this->cartItems[$index])) {
            return;
        }

        $qty = round((float) str_replace(',', '.', (string) $quantity), 3);

        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $item = $this->cartItems[$index];
        $availableForOthers = $this->availableStockFor($item['product_id'], (float) $item['stock'])
            + (float) $item['quantity'];

        if ($qty > $availableForOthers + self::STOCK_EPSILON) {
            Notification::make()
                ->title('Stock insuficiente')
                ->body('Solo hay '.Product::formatQuantity($item['unit'], $availableForOthers).' disponibles.')
                ->warning()
                ->send();

            return;
        }

        $unitPrice = $this->resolveUnitPrice($item['unit'], $qty, $item['base_unit_price'], $item['bulk_price_2kg']);

        $this->cartItems[$index]['quantity'] = $qty;
        $this->cartItems[$index]['unit_price'] = $unitPrice;
        $this->cartItems[$index]['subtotal'] = round($qty * $unitPrice, 2);

        // Editar a mano la cantidad de un ítem pesado significa que el número
        // ya no es el que dio la balanza, y la auditoría tiene que reflejarlo.
        if ($item['weight_source'] === SaleItem::SOURCE_SCALE) {
            $this->cartItems[$index]['weight_source'] = SaleItem::SOURCE_MANUAL;
            $this->cartItems[$index]['scale_connection'] = null;
            $this->cartItems[$index]['raw_reading'] = null;
        }
    }

    public function clearCart(): void
    {
        $this->cartItems = [];
        $this->pendingItem = [];
        $this->manualWeightMode = false;
        $this->manualWeightValue = '';
        $this->paymentMethod = '';
        $this->notes = '';
        $this->lastSaleNumber = null;
    }

    // ── Confirmar venta ───────────────────────────────────────────────

    public function confirmSale(): void
    {
        if (empty($this->cartItems)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();

            return;
        }

        if ($this->isWeighingPending()) {
            Notification::make()
                ->title('Hay un pesaje sin terminar')
                ->body("Tomá el peso de \"{$this->pendingItem['name']}\" o cancelalo antes de cobrar.")
                ->warning()
                ->send();

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

        try {
            // La venta se devuelve desde la transacción en vez de escribirse a
            // una variable por referencia: así queda claro que después del
            // try no puede ser null.
            $sale = DB::transaction(function () use ($cashRegister): Sale {
                $products = $this->lockAndValidateStock();

                $subtotal = $this->getSubtotal();

                $sale = Sale::create([
                    'user_id' => Auth::id(),
                    'cash_register_id' => $cashRegister->id,
                    'payment_method' => $this->paymentMethod,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $subtotal,
                    'notes' => $this->notes,
                    'status' => 'completed',
                ]);

                foreach ($this->cartItems as $item) {
                    $product = $products[$item['product_id']];

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'unit' => $item['unit'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                        'weight_source' => $item['weight_source'],
                        'scale_connection' => $item['scale_connection'],
                        'weighed_at' => $item['weighed_at'],
                        'raw_reading' => $item['raw_reading'],
                    ]);

                    $stockBefore = (float) $product->stock;
                    $product->decrement('stock', $item['quantity']);
                    $product->refresh();

                    StockMovement::create([
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - $item['quantity'],
                        'notes' => "Venta {$sale->sale_number}",
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                    ]);

                    $this->consumeFromBatches($product, (float) $item['quantity']);
                }

                $this->lastSaleNumber = $sale->sale_number;

                return $sale;
            });
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('No se pudo registrar la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cartItems = [];
        $this->paymentMethod = '';
        $this->notes = '';

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
     * Bloquea los productos del carrito y valida el stock antes de crear
     * nada.
     *
     * La cantidad se agrega por producto: con pesajes que no se fusionan es
     * normal tener varias líneas del mismo corte, y validarlas por separado
     * dejaría pasar una venta que en total supera el stock.
     *
     * @return array<int, Product>
     */
    private function lockAndValidateStock(): array
    {
        $requested = [];

        foreach ($this->cartItems as $item) {
            $requested[$item['product_id']] ??= ['quantity' => 0.0, 'item' => $item];
            $requested[$item['product_id']]['quantity'] += (float) $item['quantity'];
        }

        $products = [];

        foreach ($requested as $productId => $request) {
            $product = Product::lockForUpdate()->find($productId);
            $item = $request['item'];

            if (! $product || (float) $product->stock + self::STOCK_EPSILON < $request['quantity']) {
                $available = $product ? (float) $product->stock : 0.0;

                throw new \RuntimeException(
                    "Stock insuficiente para \"{$item['name']}\". ".
                    'Disponible: '.Product::formatQuantity($item['unit'], $available).'. '.
                    'En carrito: '.Product::formatQuantity($item['unit'], $request['quantity']).'.'
                );
            }

            $products[$productId] = $product;
        }

        return $products;
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
                    'status' => $remaining <= 0 ? 'depleted' : 'active',
                ]);

                $qty -= $consumed;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo descontar de los lotes de vencimiento', [
                'product_id' => $product->id,
                'qty' => $qty,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
