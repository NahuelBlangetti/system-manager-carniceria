<x-filament-panels::page>

    @php
        $totalCount    = count($products);
        $selectedCount = collect($products)->where('selected', true)->count();
        $newCount      = collect($products)->where('action', 'create')->whereNull('duplicate')->where('selected', true)->count();
        $updateCount   = collect($products)->where('action', 'update')->where('selected', true)->count();
    @endphp

    <div class="flex flex-col gap-6 pb-10">
        <div class="carga-rapida-card">
            {{-- Header --}}
            <div class="carga-rapida-card-header">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-success-500/10 text-success-600 dark:text-success-400">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $totalCount }} {{ $totalCount === 1 ? 'producto detectado' : 'productos detectados' }}
                            @if ($updateCount > 0)
                                <span class="ml-1 text-warning-600 dark:text-warning-400">({{ $updateCount }} {{ $updateCount === 1 ? 'ya existe' : 'ya existen' }})</span>
                            @endif
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Revisá y corregí antes de guardar
                            @if ($importedFileName)
                                · <span class="font-medium text-gray-600 dark:text-gray-300">{{ $importedFileName }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-warning-600 dark:text-warning-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span>
                        Pendiente de validar
                    </span>

                    <div class="flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-o-building-storefront" class="h-4 w-4 shrink-0 text-gray-400" />
                        @include('filament.partials.supplier-combobox', ['size' => 'sm'])
                    </div>

                    <a
                        href="{{ \App\Filament\Pages\CargarProductos::getUrl() }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                        Volver
                    </a>
                </div>
            </div>

            {{-- Leyenda de colores --}}
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-3 dark:border-white/10">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Referencias:</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-success-600 dark:text-success-400">
                    <span class="h-2 w-2 rounded-full bg-success-500"></span> Nuevo
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-warning-600 dark:text-warning-400">
                    <span class="h-2 w-2 rounded-full bg-warning-500"></span> Precio sube
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-info-600 dark:text-info-400">
                    <span class="h-2 w-2 rounded-full bg-info-500"></span> Precio baja
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <span class="h-2 w-2 rounded-full bg-gray-400"></span> Sin cambios
                </span>
            </div>

            {{-- Tabla --}}
            @if ($totalCount === 0)
                <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <x-filament::icon icon="heroicon-o-inbox" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No se detectaron productos en el archivo</p>
                    <p class="max-w-md text-sm text-gray-400 dark:text-gray-500">
                        Probá con otro formato o verificá que la lista tenga nombres y precios legibles.
                    </p>
                    <button
                        wire:click="cancelImport"
                        wire:confirm="¿Cancelar esta importación?"
                        type="button"
                        class="mt-2 inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-200"
                    >
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                        Cancelar importación
                    </button>
                </div>
            @else
                <div class="carga-archivo-table-wrap overflow-x-auto">
                    <table class="w-full min-w-[72rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">SKU proveedor</th>
                                <th class="px-4 py-3">Código de barras</th>
                                <th class="px-4 py-3">Unidad</th>
                                <th class="px-4 py-3">Costo</th>
                                <th class="px-4 py-3">Venta</th>
                                <th class="px-4 py-3">Stock</th>
                                <th class="px-4 py-3">Categoría</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($products as $index => $product)
                                @php
                                    $action    = $product['action'] ?? 'create';
                                    $duplicate = $product['duplicate'] ?? null;
                                    $priceDir  = $product['price_direction'] ?? null;
                                    $existCost = (float) ($product['existing_cost'] ?? 0);
                                    $newCost   = (float) ($product['cost_price'] ?? 0);
                                    $pct       = ($existCost > 0 && $newCost > 0)
                                                    ? round(abs($newCost - $existCost) / $existCost * 100, 1)
                                                    : null;
                                @endphp
                                <tr wire:key="product-{{ $index }}" class="{{ ($product['selected'] ?? false) ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-2.5">
                                        <input
                                            type="checkbox"
                                            wire:model.live="products.{{ $index }}.selected"
                                            class="fi-checkbox-input rounded-md border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                    </td>

                                    <td class="min-w-[10rem] px-4 py-2.5">
                                        @if ($action === 'update')
                                            @if ($priceDir === 'up')
                                                <span title="{{ $duplicate }}{{ $pct !== null ? " · +{$pct}% en costo" : '' }}" class="inline-flex items-center gap-1 rounded-full bg-warning-500/10 px-2 py-1 text-xs font-medium text-warning-600 dark:text-warning-400">
                                                    ↑ Sube{{ $pct !== null ? " {$pct}%" : '' }}
                                                </span>
                                            @elseif ($priceDir === 'down')
                                                <span title="{{ $duplicate }}{{ $pct !== null ? " · -{$pct}% en costo" : '' }}" class="inline-flex items-center gap-1 rounded-full bg-info-500/10 px-2 py-1 text-xs font-medium text-info-600 dark:text-info-400">
                                                    ↓ Baja{{ $pct !== null ? " {$pct}%" : '' }}
                                                </span>
                                            @else
                                                <span title="{{ $duplicate }}" class="inline-flex items-center gap-1 rounded-full bg-gray-500/10 px-2 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                    = Sin cambios
                                                </span>
                                            @endif
                                            <p class="mt-1 text-[10px] leading-tight text-gray-400 dark:text-gray-500">
                                                ${{ number_format($existCost, 2, ',', '.') }} → ${{ number_format($newCost, 2, ',', '.') }}
                                            </p>
                                        @elseif ($duplicate)
                                            <span title="{{ $duplicate }}" class="inline-flex items-center gap-1 rounded-full bg-warning-500/10 px-2 py-1 text-xs font-medium text-warning-600 dark:text-warning-400">
                                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-3.5 w-3.5" />
                                                En archivo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-success-500/10 px-2 py-1 text-xs font-medium text-success-600 dark:text-success-400">
                                                Nuevo
                                            </span>
                                        @endif
                                    </td>

                                    <td class="min-w-[14rem] px-4 py-2.5">
                                        <input type="text" wire:model="products.{{ $index }}.name" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[8rem] px-4 py-2.5">
                                        <input type="text" wire:model="products.{{ $index }}.sku" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[9rem] px-4 py-2.5">
                                        <input type="text" wire:model="products.{{ $index }}.barcode" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[7rem] px-4 py-2.5">
                                        <select wire:model="products.{{ $index }}.unit" class="fi-select-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                            @foreach (\App\Filament\Pages\ValidarImport::ALLOWED_UNITS as $unitOption)
                                                <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="min-w-[7rem] px-4 py-2.5">
                                        <input type="number" step="0.01" min="0" wire:model="products.{{ $index }}.cost_price" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[7rem] px-4 py-2.5">
                                        <input type="number" step="0.01" min="0" wire:model="products.{{ $index }}.sale_price" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[6rem] px-4 py-2.5">
                                        <input type="number" step="1" min="0" wire:model="products.{{ $index }}.stock" class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="min-w-[10rem] px-4 py-2.5">
                                        <select wire:model="products.{{ $index }}.category_id" class="fi-select-input block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                                            <option value="">Sin categoría</option>
                                            @foreach ($categoryOptions as $catId => $cName)
                                                <option value="{{ $catId }}">{{ $cName }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Footer --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-5 dark:border-white/10">
                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ $selectedCount }} seleccionados</span>
                    @if ($newCount > 0)
                        <span class="text-success-600 dark:text-success-400">{{ $newCount }} nuevos</span>
                    @endif
                    @if ($updateCount > 0)
                        <span class="text-warning-600 dark:text-warning-400">{{ $updateCount }} actualizar</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        wire:click="cancelImport"
                        wire:confirm="¿Cancelar esta importación? No se guardará ningún producto del archivo."
                        wire:loading.attr="disabled"
                        wire:target="cancelImport"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:border-white/15 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        <span wire:loading.remove wire:target="cancelImport" class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                            Cancelar importación
                        </span>
                        <span wire:loading wire:target="cancelImport" class="inline-flex items-center gap-2">
                            Cancelando…
                        </span>
                    </button>
                    <button
                        wire:click="createProducts"
                        wire:loading.attr="disabled"
                        wire:target="createProducts"
                        type="button"
                        @disabled($totalCount === 0 || $selectedCount === 0)
                        class="inline-flex items-center gap-2 rounded-xl bg-success-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-success-500 focus:outline-none focus:ring-2 focus:ring-success-500/40 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="createProducts" class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                            Guardar seleccionados
                        </span>
                        <span wire:loading wire:target="createProducts" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Guardando…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
