<x-filament-panels::page>
    @if (! $this->hasCashRegisterOpen)
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-6 py-4 dark:border-danger-800 dark:bg-danger-950/30">
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-6 w-6 shrink-0 text-danger-600 dark:text-danger-400"
                />
                <div class="flex-1">
                    <p class="font-semibold text-danger-800 dark:text-danger-300">
                        No hay caja abierta
                    </p>
                    <p class="text-sm text-danger-600 dark:text-danger-400">
                        No podés registrar ventas hasta abrir una caja.
                        <a
                            href="/admin/cash-registers/create"
                            class="font-semibold underline hover:text-danger-800 dark:hover:text-danger-200"
                        >
                            Abrir caja ahora
                        </a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-5"
        x-data="{}"
    >
        {{-- ══════════════════════════════════════════
             PANEL IZQUIERDO: Buscar / escanear producto
        ══════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4 lg:col-span-3">

            <div class="carga-rapida-card">
                <div class="carga-rapida-card-header">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                Buscar o escanear producto
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Escaneá un código, buscá por nombre, SKU o código de barras
                            </p>
                        </div>
                    </div>
                </div>

                <div class="carga-rapida-card-body">
                    <div class="carga-rapida-scanner-row">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2">
                                <x-filament::icon icon="heroicon-o-qr-code" wire:loading.remove wire:target="addProduct" class="h-5 w-5 text-gray-400" />
                                <svg wire:loading wire:target="addProduct" class="h-5 w-5 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </span>
                            <input
                                wire:model.live.debounce.300ms="productQuery"
                                wire:keydown.enter.prevent="addProduct"
                                type="text"
                                placeholder="Apuntá el escáner aquí o buscá por nombre, SKU o código…"
                                autocomplete="off"
                                x-init="$el.focus()"
                                x-on:focus-product-search.window="$nextTick(() => $el.focus())"
                                class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white pl-10 pr-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder-gray-500"
                            />
                        </div>

                        <button
                            wire:click="addProduct"
                            wire:loading.attr="disabled"
                            wire:target="addProduct"
                            type="button"
                            class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:opacity-60 sm:w-auto"
                        >
                            <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" wire:loading.remove wire:target="addProduct" />
                            <span wire:loading.remove wire:target="addProduct">Agregar</span>
                            <span wire:loading wire:target="addProduct">Buscando…</span>
                        </button>
                    </div>

                    <div class="carga-rapida-footer-row !mt-4 !border-t-0 !pt-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Presioná <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:border-white/20 dark:bg-white/10">Enter</kbd>
                            o hacé clic en Agregar. El escáner 1D funciona automáticamente.
                        </p>
                    </div>

                    {{-- Resultados de búsqueda --}}
                    @if (count($searchResults) > 0)
                        <div class="mt-4 divide-y divide-gray-100 dark:divide-white/10 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
                            @foreach ($searchResults as $product)
                                <button
                                    wire:click="addToCart({{ $product['id'] }})"
                                    type="button"
                                    class="flex w-full items-center gap-4 px-4 py-3 text-left transition hover:bg-primary-50 dark:hover:bg-primary-950/40 focus:outline-none focus:bg-primary-50"
                                >
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $product['name'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Stock: {{ \App\Models\Product::formatQuantity($product['unit'], $product['stock']) }}
                                            @if (! empty($product['sku']))
                                                · SKU: {{ $product['sku'] }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                            ${{ number_format($product['sale_price'], 2, ',', '.') }}
                                        </span>
                                        <div class="mt-0.5">
                                            @if ($product['stock'] > 0)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-950/50 dark:text-success-400">
                                                    Disponible
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
                                                    Sin stock
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <x-filament::icon
                                        icon="heroicon-o-plus-circle"
                                        class="h-5 w-5 text-primary-500 shrink-0"
                                    />
                                </button>
                            @endforeach
                        </div>
                    @elseif (strlen(trim($productQuery)) >= 2)
                        <div class="mt-4 rounded-xl border border-dashed border-gray-300 dark:border-white/10 px-4 py-6 text-center">
                            <x-filament::icon icon="heroicon-o-face-frown" class="mx-auto h-8 w-8 text-gray-400" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron productos para "{{ $productQuery }}"</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ══════════════════════════════════════════
             PANEL DERECHO: Carrito + Pago
        ══════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4 lg:col-span-2">

            {{-- Carrito --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <x-filament::icon
                            icon="heroicon-o-shopping-cart"
                            class="h-5 w-5 text-primary-500"
                        />
                        <h3 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                            Carrito
                            @if (count($cartItems) > 0)
                                <span class="ml-1 inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                                    {{ $this->getCartCount() }} {{ $this->getCartCount() === 1 ? 'producto' : 'productos' }}
                                </span>
                            @endif
                        </h3>
                    </div>
                    @if (count($cartItems) > 0)
                        <button
                            wire:click="clearCart"
                            wire:confirm="¿Vaciar el carrito?"
                            type="button"
                            class="text-xs text-danger-600 hover:text-danger-700 dark:text-danger-400 transition"
                        >
                            Vaciar
                        </button>
                    @endif
                </div>

                <div class="px-6 py-4">
                    @if (count($cartItems) === 0)
                        <div class="py-8 text-center">
                            <x-filament::icon icon="heroicon-o-shopping-cart" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm text-gray-400 dark:text-gray-500">
                                El carrito está vacío.<br>Buscá o escaneá un producto arriba.
                            </p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($cartItems as $index => $item)
                                <div class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $item['name'] }}
                                        </p>
                                        <button
                                            wire:click="removeFromCart({{ $index }})"
                                            type="button"
                                            class="shrink-0 text-gray-400 hover:text-danger-500 transition"
                                        >
                                            <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                            ${{ number_format($item['unit_price'], 2, ',', '.') }} / {{ $item['unit'] }}
                                        </span>

                                        <div class="flex items-center gap-3 shrink-0">
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})"
                                                    type="button"
                                                    class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-white/20 dark:bg-white/5 dark:text-gray-300"
                                                >−</button>
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0"
                                                    value="{{ $item['quantity'] }}"
                                                    wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                                    class="w-16 rounded border border-gray-300 bg-white px-1 py-0.5 text-center text-sm font-semibold text-gray-900 dark:border-white/20 dark:bg-white/5 dark:text-white"
                                                />
                                                <button
                                                    wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                                    type="button"
                                                    class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-white/20 dark:bg-white/5 dark:text-gray-300"
                                                >+</button>
                                            </div>

                                            <span class="w-20 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                                ${{ number_format($item['subtotal'], 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Total --}}
                        <div class="mt-4 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Total</span>
                                <span class="text-xl font-bold text-gray-900 dark:text-white">
                                    ${{ number_format($this->getSubtotal(), 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Método de pago + Confirmar --}}
            @if (count($cartItems) > 0)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                        <x-filament::icon
                            icon="heroicon-o-banknotes"
                            class="h-5 w-5 text-primary-500"
                        />
                        <h3 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                            Método de pago
                        </h3>
                    </div>
                    <div class="px-6 py-4 flex flex-col gap-4">

                        {{-- Botones de método de pago --}}
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                wire:click="$set('paymentMethod', 'cash')"
                                type="button"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-xl border-2 px-3 py-4 text-sm font-semibold transition focus:outline-none',
                                    'border-success-500 bg-success-50 text-success-700 dark:bg-success-950/40 dark:text-success-300 dark:border-success-600' => $paymentMethod === 'cash',
                                    'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-300' => $paymentMethod !== 'cash',
                                ])
                            >
                                <x-filament::icon icon="heroicon-o-banknotes" class="h-6 w-6" />
                                Efectivo
                            </button>

                            <button
                                wire:click="$set('paymentMethod', 'transfer')"
                                type="button"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-xl border-2 px-3 py-4 text-sm font-semibold transition focus:outline-none',
                                    'border-info-500 bg-info-50 text-info-700 dark:bg-info-950/40 dark:text-info-300 dark:border-info-600' => $paymentMethod === 'transfer',
                                    'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-300' => $paymentMethod !== 'transfer',
                                ])
                            >
                                <x-filament::icon icon="heroicon-o-device-phone-mobile" class="h-6 w-6" />
                                Transferencia
                            </button>

                            <button
                                wire:click="$set('paymentMethod', 'card')"
                                type="button"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-xl border-2 px-3 py-4 text-sm font-semibold transition focus:outline-none',
                                    'border-warning-500 bg-warning-50 text-warning-700 dark:bg-warning-950/40 dark:text-warning-300 dark:border-warning-600' => $paymentMethod === 'card',
                                    'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-300' => $paymentMethod !== 'card',
                                ])
                            >
                                <x-filament::icon icon="heroicon-o-credit-card" class="h-6 w-6" />
                                Tarjeta
                            </button>
                        </div>

                        {{-- Notas opcionales --}}
                        <textarea
                            wire:model="notes"
                            rows="2"
                            placeholder="Notas (opcional)..."
                            class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm resize-none dark:border-white/20 dark:bg-white/5 dark:text-white dark:placeholder-gray-400"
                        ></textarea>

                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                wire:model="printTicket"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                            />
                            Imprimir comprobante
                        </label>

                        {{-- Botón confirmar --}}
                        <button
                            wire:click="confirmSale"
                            wire:loading.attr="disabled"
                            type="button"
                            @class([
                                'w-full rounded-xl px-6 py-4 text-base font-bold text-white shadow-md transition focus:outline-none focus:ring-4',
                                'bg-primary-600 hover:bg-primary-500 focus:ring-primary-500/30 cursor-pointer' => ! empty($paymentMethod),
                                'bg-gray-400 cursor-not-allowed' => empty($paymentMethod),
                            ])
                            @if (empty($paymentMethod)) disabled @endif
                        >
                            <span wire:loading.remove wire:target="confirmSale">
                                <span class="flex items-center justify-center gap-2">
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                                    Confirmar venta · ${{ number_format($this->getSubtotal(), 2, ',', '.') }}
                                </span>
                            </span>
                            <span wire:loading wire:target="confirmSale">
                                Procesando...
                            </span>
                        </button>

                        @if (! empty($paymentMethod))
                            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                                Pago:
                                <strong class="text-gray-700 dark:text-gray-200">
                                    {{ match($paymentMethod) {
                                        'cash'     => 'Efectivo',
                                        'transfer' => 'Transferencia',
                                        'card'     => 'Tarjeta',
                                        default    => $paymentMethod,
                                    } }}
                                </strong>
                            </p>
                        @endif

                    </div>
                </div>
            @endif

            {{-- Última venta confirmada --}}
            @if ($lastSaleNumber)
                <div class="rounded-xl border border-success-200 bg-success-50 px-6 py-4 dark:border-success-800 dark:bg-success-950/30">
                    <div class="flex items-center gap-3">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-600 dark:text-success-400 shrink-0" />
                        <div>
                            <p class="font-semibold text-success-800 dark:text-success-300">
                                ¡Venta {{ $lastSaleNumber }} registrada!
                            </p>
                            <p class="text-xs text-success-600 dark:text-success-400">
                                Stock actualizado automáticamente.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-filament-panels::page>
