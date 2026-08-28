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

    {{-- ══════════════════════════════════════════
         Franja de balanzas

         Siempre visible: con dos carniceros pesando y un solo operador en la
         PC, saber de un vistazo qué mostrador tiene peso puesto y quieto es
         la mitad del trabajo. Hacer clic elige de qué balanza se toma el peso.
    ══════════════════════════════════════════ --}}
    @if (count($this->scales) > 0)
        {{-- Siempre en columnas: en el mostrador las dos balanzas se miran
             juntas, y apilarlas duplica el alto sin sumar información.
             Las clases van estáticas para que Tailwind las vea. --}}
        <div @class([
            'grid gap-3',
            'grid-cols-2' => count($this->scales) === 2,
            'grid-cols-2 lg:grid-cols-3' => count($this->scales) === 3,
            'grid-cols-2 lg:grid-cols-4' => count($this->scales) >= 4,
            'grid-cols-1' => count($this->scales) === 1,
        ])>
            @foreach ($this->scales as $connection => $label)
                <x-scale-reader
                    :connection="$connection"
                    :label="$label"
                    :selected="$selectedScale === $connection"
                    wire:click="seleccionarBalanza('{{ $connection }}')"
                />
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- ══════════════════════════════════════════
             PANEL IZQUIERDO: pesaje + buscar/escanear
        ══════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4 lg:col-span-3">

            {{-- ══════════════════════════════════════
                 Pesaje pendiente

                 El peso en vivo no puede ser la cantidad de un ítem del
                 carrito: cuando el carnicero retira la carne para seguir con
                 el producto siguiente, el ítem se iría a cero. Por eso el
                 producto espera acá hasta que se congela un peso, y solo
                 entonces entra al carrito.

                 wire:key incluye la balanza para que Alpine se vuelva a montar
                 al cambiar de mostrador y se suscriba a la conexión correcta.
            ══════════════════════════════════════ --}}
            @if ($this->isWeighingPending())
                <div
                    wire:key="pesaje-{{ $pendingItem['product_id'] }}-{{ $selectedScale }}"
                    {{-- El precio se pasa por unidad de balanza, no por unidad de
                         venta: con un producto en gramos y una balanza en kilos, el
                         importe proyectado saldría mil veces menor al que se cobra. --}}
                    @php
                        $pendingDivisor = max(1, (int) config("scale.connections.{$selectedScale}.divisor", 100));
                        $pendingDecimals = \App\Services\Scale\ScaleReading::displayDecimals($pendingDivisor);
                    @endphp
                    x-data="window.scaleHub.readerData(@js($selectedScale), {
                        pricePerKg: {{ $this->pendingPricePerScaleUnit() }},
                        decimals: {{ $pendingDecimals }},
                        divisor: {{ $pendingDivisor }},
                    })"
                    class="overflow-hidden rounded-xl border-2 border-primary-500 bg-white shadow-lg ring-4 ring-primary-500/10 dark:bg-gray-900"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-primary-200 bg-primary-50 px-5 py-3 dark:border-primary-900 dark:bg-primary-950/40">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-filament::icon icon="heroicon-o-scale" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-gray-950 dark:text-white">
                                    {{ $pendingItem['name'] }}
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    ${{ number_format($pendingItem['unit_price'], 2, ',', '.') }} / {{ $pendingItem['unit'] }}
                                    · stock {{ \App\Models\Product::formatQuantity($pendingItem['unit'], $pendingItem['stock']) }}
                                    · {{ $this->scales[$selectedScale] ?? $selectedScale }}
                                </p>
                                @if (! empty($pendingItem['bulk_price_2kg']))
                                    <p class="mt-0.5 text-[11px] font-medium text-success-700 dark:text-success-400">
                                        2 kg o más: ${{ number_format($pendingItem['bulk_price_2kg'] / 2, 2, ',', '.') }} / kg
                                    </p>
                                @endif
                            </div>
                        </div>

                        <button
                            wire:click="cancelarPesaje"
                            type="button"
                            class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-gray-500 transition hover:bg-white hover:text-danger-600 dark:hover:bg-white/10"
                        >
                            Cancelar
                        </button>
                    </div>

                    <div class="px-5 py-5">
                        @if ($this->pendingScaleFactor() === null)
                            <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 dark:border-danger-800 dark:bg-danger-950/30">
                                <p class="text-sm font-semibold text-danger-800 dark:text-danger-300">
                                    La balanza no puede pesar en «{{ $pendingItem['unit'] }}»
                                </p>
                                <p class="mt-0.5 text-xs text-danger-700 dark:text-danger-400">
                                    Revisá la unidad del producto o la de la balanza. Mientras tanto,
                                    cargá la cantidad a mano.
                                </p>
                            </div>
                        @endif

                        @if (! $manualWeightMode)
                            <div class="flex flex-wrap items-end justify-between gap-4">
                                <div>
                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="font-mono text-6xl font-bold tabular-nums leading-none transition-colors"
                                            :class="capturable
                                                ? 'text-success-600 dark:text-success-400'
                                                : 'text-gray-400 dark:text-gray-500'"
                                            x-text="weightLabel"
                                        ></span>
                                        <span class="text-xl font-medium text-gray-500 dark:text-gray-400" x-text="unit"></span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="hint"></p>
                                </div>

                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">A cobrar</p>
                                    <p
                                        class="font-mono text-3xl font-bold tabular-nums"
                                        :class="capturable
                                            ? 'text-gray-950 dark:text-white'
                                            : 'text-gray-400 dark:text-gray-500'"
                                        x-text="projectedTotal"
                                    ></p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                {{-- El peso no se manda desde el navegador: solo se pide
                                     "capturá de esta balanza" más las cuentas crudas que
                                     el operador tenía en pantalla, que el servidor usa
                                     para verificar que el peso no cambió mientras tanto. --}}
                                <button
                                    type="button"
                                    x-on:click="$wire.capturarPeso(connection, raw)"
                                    {{-- Enfocar el botón es lo que hace que Enter tome el
                                         peso sin agregar un atajo global que pelearía con
                                         el foco del buscador. --}}
                                    x-init="$nextTick(() => $el.focus())"
                                    :disabled="! capturable"
                                    :class="capturable
                                        ? 'bg-success-600 hover:bg-success-500 focus:ring-success-500/40 cursor-pointer'
                                        : 'bg-gray-400 cursor-not-allowed dark:bg-gray-700'"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl px-6 py-4 text-base font-bold text-white shadow-sm transition focus:outline-none focus:ring-4"
                                >
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                                    Tomar peso
                                </button>

                                <button
                                    wire:click="activarPesoManual"
                                    type="button"
                                    class="shrink-0 rounded-xl border border-gray-300 px-4 py-4 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-white/20 dark:text-gray-300 dark:hover:bg-white/5"
                                >
                                    Peso a mano
                                </button>
                            </div>

                            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono dark:border-white/20 dark:bg-white/10">Enter</kbd>
                                toma el peso cuando está estable.
                            </p>
                        @else
                            {{-- Salida de emergencia: si la balanza no responde, la venta
                                 no puede quedar trabada. Queda marcado como peso manual
                                 para poder auditarlo después. --}}
                            <div class="rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-800 dark:bg-warning-950/30">
                                <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">
                                    Peso ingresado a mano
                                </p>
                                <p class="mt-0.5 text-xs text-warning-700 dark:text-warning-400">
                                    Queda registrado como manual. Usalo solo si la balanza no responde.
                                </p>
                            </div>

                            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="relative flex-1">
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        wire:model="manualWeightValue"
                                        wire:keydown.enter.prevent="usarPesoManual"
                                        x-init="$nextTick(() => $el.focus())"
                                        placeholder="0,000"
                                        class="fi-input block w-full rounded-xl border border-gray-300 bg-white py-3 pl-4 pr-12 font-mono text-2xl font-semibold tabular-nums text-gray-900 shadow-sm dark:border-white/20 dark:bg-white/5 dark:text-white"
                                    />
                                    <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $pendingItem['unit'] }}
                                    </span>
                                </div>

                                <button
                                    wire:click="usarPesoManual"
                                    type="button"
                                    class="rounded-xl bg-warning-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-warning-500 focus:outline-none focus:ring-4 focus:ring-warning-500/30"
                                >
                                    Agregar
                                </button>

                                <button
                                    wire:click="cancelarPesoManual"
                                    type="button"
                                    class="rounded-xl px-4 py-3 text-sm font-medium text-gray-500 transition hover:bg-gray-100 dark:hover:bg-white/5"
                                >
                                    Volver a la balanza
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div @class(['carga-rapida-card', 'pointer-events-none opacity-50' => $this->isWeighingPending()])>
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
                                @if ($this->isWeighingPending())
                                    Terminá el pesaje de arriba para seguir cargando
                                @else
                                    Escaneá un código o buscá por nombre o SKU
                                @endif
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
                                @if ($this->isWeighingPending()) disabled @endif
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

                    {{-- Resultados de búsqueda: van antes que Frecuentes porque
                         mientras el operador está escribiendo/escaneando algo,
                         eso es lo que quiere ver primero, no una grilla fija. --}}
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
                                        <div class="mt-0.5 flex items-center justify-end gap-1">
                                            @if (\App\Models\SaleItem::isWeighable($product['unit']))
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/50 dark:text-primary-400">
                                                    Se pesa
                                                </span>
                                            @endif
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

                    {{-- Frecuentes: mismo código corto que se carga en el campo
                         "código interno" del producto, para tocar en vez de
                         tipear. Tipear el código en el buscador de arriba
                         también funciona, es el mismo match exacto. --}}
                    @if (count($this->frequentProducts) > 0)
                        <div class="mt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Frecuentes
                            </p>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                @foreach ($this->frequentProducts as $product)
                                    <button
                                        type="button"
                                        wire:click="addToCart({{ $product['id'] }})"
                                        wire:loading.attr="disabled"
                                        class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-2 py-3 text-center transition hover:border-primary-400 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                                    >
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
                                            {{ $product['code'] }}
                                        </span>
                                        <span class="line-clamp-2 text-xs font-medium leading-tight text-gray-800 dark:text-gray-200">
                                            {{ $product['name'] }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
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
                                    {{ $this->getCartCount() }} {{ $this->getCartCount() === 1 ? 'ítem' : 'ítems' }}
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
                                @php
                                    $isWeighed = $item['weight_source'] !== null;
                                    $bulkApplied = ! empty($item['bulk_price_2kg']) && (float) $item['quantity'] >= 2;
                                @endphp
                                <div class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $item['name'] }}
                                            </p>
                                            @if ($isWeighed)
                                                {{-- Distinguir pesado de tipeado no es cosmético: un
                                                     ítem por peso cargado a mano es justo lo que hay
                                                     que poder revisar al cierre. --}}
                                                <p class="mt-0.5 flex items-center gap-1 text-[11px]">
                                                    @if ($item['weight_source'] === \App\Models\SaleItem::SOURCE_SCALE)
                                                        <span class="inline-flex items-center gap-1 rounded bg-success-50 px-1.5 py-0.5 font-medium text-success-700 dark:bg-success-950/50 dark:text-success-400">
                                                            <x-filament::icon icon="heroicon-m-scale" class="h-3 w-3" />
                                                            {{ $this->scales[$item['scale_connection']] ?? $item['scale_connection'] }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded bg-warning-50 px-1.5 py-0.5 font-medium text-warning-700 dark:bg-warning-950/50 dark:text-warning-400">
                                                            <x-filament::icon icon="heroicon-m-pencil" class="h-3 w-3" />
                                                            peso a mano
                                                        </span>
                                                    @endif
                                                </p>
                                            @endif
                                            @if ($bulkApplied)
                                                <p class="mt-0.5">
                                                    <span class="inline-flex items-center gap-1 rounded bg-success-50 px-1.5 py-0.5 text-[11px] font-medium text-success-700 dark:bg-success-950/50 dark:text-success-400">
                                                        precio x 2kg aplicado
                                                    </span>
                                                </p>
                                            @endif
                                        </div>
                                        <button
                                            wire:click="removeFromCart({{ $index }})"
                                            type="button"
                                            class="shrink-0 text-gray-400 hover:text-danger-500 transition"
                                        >
                                            <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <span class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                            {{ \App\Models\Product::formatQuantity($item['unit'], $item['quantity']) }}
                                            × ${{ number_format($item['unit_price'], 2, ',', '.') }}
                                        </span>

                                        <div class="flex shrink-0 items-center gap-3">
                                            <div class="flex items-center gap-1.5">
                                                {{-- Los +/- de a uno solo tienen sentido por unidad:
                                                     sumar 1 kg de un clic sería un salto enorme. --}}
                                                @if (! $isWeighed)
                                                    <button
                                                        wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})"
                                                        type="button"
                                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-white/20 dark:bg-white/5 dark:text-gray-300"
                                                    >−</button>
                                                @endif
                                                <input
                                                    type="number"
                                                    step="{{ $isWeighed ? '0.001' : '1' }}"
                                                    min="0"
                                                    value="{{ $item['quantity'] }}"
                                                    wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                                    class="w-16 rounded border border-gray-300 bg-white px-1 py-0.5 text-center text-sm font-semibold tabular-nums text-gray-900 dark:border-white/20 dark:bg-white/5 dark:text-white"
                                                />
                                                @if (! $isWeighed)
                                                    <button
                                                        wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                                        type="button"
                                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-white/20 dark:bg-white/5 dark:text-gray-300"
                                                    >+</button>
                                                @endif
                                            </div>

                                            <span class="w-20 text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-white">
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
                                <span class="text-xl font-bold tabular-nums text-gray-900 dark:text-white">
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

                        {{-- Cobrar con un pesaje a medias dejaría la venta sin ese
                             producto y el operador no se enteraría hasta el ticket. --}}
                        @if ($this->isWeighingPending())
                            <p class="rounded-lg bg-warning-50 px-3 py-2 text-xs font-medium text-warning-800 dark:bg-warning-950/30 dark:text-warning-300">
                                Hay un pesaje sin terminar: {{ $pendingItem['name'] }}.
                            </p>
                        @endif

                        {{-- Botón confirmar --}}
                        @php
                            $canConfirm = ! empty($paymentMethod) && ! $this->isWeighingPending();
                        @endphp
                        <button
                            wire:click="confirmSale"
                            wire:loading.attr="disabled"
                            type="button"
                            @class([
                                'w-full rounded-xl px-6 py-4 text-base font-bold text-white shadow-md transition focus:outline-none focus:ring-4',
                                'bg-primary-600 hover:bg-primary-500 focus:ring-primary-500/30 cursor-pointer' => $canConfirm,
                                'bg-gray-400 cursor-not-allowed' => ! $canConfirm,
                            ])
                            @if (! $canConfirm) disabled @endif
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
