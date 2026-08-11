<x-filament-panels::page>
    {{-- ── Importaciones pendientes de validar ────────────────────────────────── --}}
    @if (count($pendingImports) > 0)
        <div class="mb-6 overflow-hidden rounded-2xl border border-warning-200 bg-warning-50 shadow-sm dark:border-warning-500/20 dark:bg-warning-500/5">
            {{-- Header --}}
            <div class="flex items-center gap-3 border-b border-warning-200 bg-warning-100/60 px-5 py-3.5 dark:border-warning-500/20 dark:bg-warning-500/10">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-warning-500 text-white shadow-sm">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4" />
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-warning-800 dark:text-warning-300">
                        {{ count($pendingImports) === 1 ? '1 importación lista para validar' : count($pendingImports) . ' importaciones listas para validar' }}
                    </p>
                    <p class="mt-0.5 text-xs text-warning-700 dark:text-warning-400">
                        Los productos fueron extraídos por IA. Revisalos y guardalos antes de que queden huérfanos.
                    </p>
                </div>
                <span class="shrink-0 animate-pulse rounded-full bg-warning-500 px-2.5 py-1 text-xs font-bold text-white">
                    PENDIENTE{{ count($pendingImports) > 1 ? 'S' : '' }}
                </span>
            </div>

            {{-- Lista de importaciones --}}
            <div class="divide-y divide-warning-100 dark:divide-warning-500/10">
                @foreach ($pendingImports as $pi)
                    <div class="flex items-center gap-4 px-5 py-3.5">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 shrink-0 text-warning-500" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $pi['filename'] }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $pi['product_count'] }} {{ $pi['product_count'] === 1 ? 'producto' : 'productos' }}
                                · {{ \Carbon\Carbon::parse($pi['processed_at'])->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                wire:click="discardImport({{ $pi['id'] }})"
                                wire:confirm="¿Eliminar esta importación? No se guardará ningún producto del archivo."
                                wire:loading.attr="disabled"
                                wire:target="discardImport({{ $pi['id'] }})"
                                title="Eliminar importación"
                                class="inline-flex items-center justify-center rounded-xl border border-warning-300 bg-white px-3 py-2 text-sm font-medium text-warning-800 shadow-sm transition hover:bg-warning-100 focus:outline-none focus:ring-2 focus:ring-warning-400/50 dark:border-warning-500/30 dark:bg-transparent dark:text-warning-300 dark:hover:bg-warning-500/10"
                            >
                                <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                                <span class="sr-only">Eliminar</span>
                            </button>
                            <a
                                href="{{ \App\Filament\Pages\ValidarImport::getUrl(['id' => $pi['id']]) }}"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-warning-500 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-warning-400 focus:outline-none focus:ring-2 focus:ring-warning-400/50"
                            >
                                <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                                Validar ahora
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Tabs ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex w-fit gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5">
        <button
            wire:click="switchTab('rapida')"
            type="button"
            @class([
                'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition',
                'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' => $tab === 'rapida',
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== 'rapida',
            ])
        >
            <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4" />
            Carga Rápida
        </button>
        <button
            wire:click="switchTab('archivo')"
            type="button"
            @class([
                'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition',
                'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' => $tab === 'archivo',
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== 'archivo',
            ])
        >
            <x-filament::icon icon="heroicon-o-document-arrow-up" class="h-4 w-4" />
            Desde archivo
        </button>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Carga Rápida                                                       --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($tab === 'rapida')
        <div class="carga-rapida-layout">

            {{-- ── Columna principal ──────────────────────────────────── --}}
            <div class="flex flex-col gap-8">

                <div @class([
                    'carga-rapida-card transition',
                    'ring-2 ring-primary-500/40' => $showForm,
                ])>

                    <div class="carga-rapida-card-header">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $showForm ? 'Completar producto' : 'Escaneá o cargá manualmente' }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $showForm ? 'Nombre, precio y stock opcional' : 'El lector dispara solo con Enter' }}
                                </p>
                            </div>
                        </div>

                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-wide',
                            'bg-success-500/10 text-success-600 dark:text-success-400' => ! $showForm,
                            'bg-warning-500/10 text-warning-600 dark:text-warning-400' => $showForm,
                        ])>
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-success-500 animate-pulse' => ! $showForm,
                                'bg-warning-500' => $showForm,
                            ])></span>
                            {{ $showForm ? 'Paso 2' : 'Paso 1' }}
                        </span>
                    </div>

                    <div class="carga-rapida-card-body">
                        <label class="mb-3 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Código de barras
                        </label>

                        <div class="carga-rapida-scanner-row">
                            <div class="relative min-w-0 flex-1">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2">
                                    <x-filament::icon icon="heroicon-o-qr-code" wire:loading.remove wire:target="scanBarcode" class="h-5 w-5 text-gray-400" />
                                    <svg wire:loading wire:target="scanBarcode" class="h-5 w-5 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </span>
                                <input
                                    wire:model="barcodeInput"
                                    wire:keydown.enter.prevent="scanBarcode"
                                    type="text"
                                    placeholder="Apuntá el escáner aquí…"
                                    autocomplete="off"
                                    x-init="$el.focus()"
                                    x-on:focus-barcode.window="$nextTick(() => $el.focus())"
                                    @if($showForm) disabled @endif
                                    class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white pl-10 pr-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder-gray-500"
                                />
                            </div>

                            @if (! $showForm)
                                <button
                                    wire:click="scanBarcode"
                                    wire:loading.attr="disabled"
                                    wire:target="scanBarcode"
                                    type="button"
                                    class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:opacity-60 sm:w-auto"
                                >
                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4" wire:loading.remove wire:target="scanBarcode" />
                                    <span wire:loading.remove wire:target="scanBarcode">Buscar</span>
                                    <span wire:loading wire:target="scanBarcode">Buscando…</span>
                                </button>
                            @endif
                        </div>

                        @if (! $showForm)
                            <div class="carga-rapida-footer-row">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Tras guardar, el foco vuelve acá automáticamente.
                                </p>
                                <button
                                    wire:click="startManualEntry"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-primary-600 transition hover:bg-primary-50 dark:border-white/10 dark:text-primary-400 dark:hover:bg-primary-500/10"
                                >
                                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                    Sin código de barras
                                </button>
                            </div>
                        @endif
                    </div>

                    @if ($showForm)
                        <div
                            x-data
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            class="carga-rapida-form-zone"
                        >
                            @if ($manualMode)
                                <div class="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                    Carga manual
                                </div>
                            @elseif ($barcodeInput)
                                <div class="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                    <span class="text-gray-500 dark:text-gray-400">Código</span>
                                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $barcodeInput }}</span>
                                </div>
                            @endif

                            <div class="carga-rapida-fields">
                                <div class="field-full">
                                    <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Nombre <span class="text-danger-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            wire:model="productName"
                                            wire:keydown.enter.prevent="saveProduct"
                                            type="text"
                                            placeholder="Ej: Bife de chorizo"
                                            autocomplete="off"
                                            x-on:focus-name.window="$nextTick(() => $el.focus())"
                                            class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white {{ $lookupSource === 'api' ? 'pr-24' : '' }}"
                                        />
                                        @if ($lookupSource === 'api')
                                            <span class="absolute inset-y-0 right-3 flex items-center">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-info-500/10 px-2 py-1 text-xs font-semibold text-info-600 dark:text-info-400">
                                                    <x-filament::icon icon="heroicon-o-sparkles" class="h-3.5 w-3.5" />
                                                    Auto
                                                </span>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Precio de venta <span class="text-danger-500">*</span>
                                    </label>
                                    <div class="flex">
                                        <span class="carga-rapida-input inline-flex items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-100 px-4 text-sm font-medium text-gray-500 dark:border-white/15 dark:bg-white/5 dark:text-gray-400">$</span>
                                        <input
                                            wire:model="salePrice"
                                            wire:keydown.enter.prevent="saveProduct"
                                            type="number"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            min="0"
                                            step="0.01"
                                            x-on:focus-price.window="$nextTick(() => $el.focus())"
                                            class="carga-rapida-input fi-input block w-full min-w-0 rounded-r-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Stock inicial
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-400 dark:bg-white/10 dark:text-gray-500">opcional</span>
                                    </label>
                                    <div class="flex">
                                        <span class="carga-rapida-input inline-flex items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-100 px-4 text-gray-400 dark:border-white/15 dark:bg-white/5">
                                            <x-filament::icon icon="heroicon-o-cube" class="h-4 w-4" />
                                        </span>
                                        <input
                                            wire:model="stockInput"
                                            wire:keydown.enter.prevent="saveProduct"
                                            type="number"
                                            inputmode="decimal"
                                            placeholder="0"
                                            min="0"
                                            step="0.001"
                                            x-on:focus-stock.window="$nextTick(() => $el.focus())"
                                            class="carga-rapida-input fi-input block w-full min-w-0 rounded-r-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Vencimiento
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-400 dark:bg-white/10 dark:text-gray-500">opcional</span>
                                    </label>
                                    <input
                                        wire:model="expirationDateInput"
                                        type="date"
                                        class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div class="carga-rapida-actions">
                                <button
                                    wire:click="saveProduct"
                                    wire:loading.attr="disabled"
                                    wire:target="saveProduct"
                                    type="button"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-success-600 px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-success-500 focus:outline-none focus:ring-2 focus:ring-success-500/40 disabled:opacity-60"
                                >
                                    <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="saveProduct">
                                        <x-filament::icon icon="heroicon-o-check" class="h-5 w-5" />
                                        Guardar y continuar
                                    </span>
                                    <span wire:loading wire:target="saveProduct">Guardando…</span>
                                </button>
                                <button
                                    wire:click="cancelForm"
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-white/15 dark:bg-transparent dark:text-gray-400 dark:hover:bg-white/5"
                                >
                                    <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($showForm)
                    <p class="px-1 text-sm text-gray-400 dark:text-gray-500">
                        <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:border-white/15 dark:bg-white/5">Enter</kbd>
                        guarda ·
                        <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:border-white/15 dark:bg-white/5">Tab</kbd>
                        siguiente campo
                    </p>
                @endif
            </div>

            {{-- ── Sidebar ─────────────────────────────────────────────── --}}
            <div class="carga-rapida-sidebar">

                <div class="carga-rapida-card">
                    <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-white/10">
                        <div class="px-6 py-7 text-center">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Sesión</p>
                            <p class="mt-3 text-4xl font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ $sessionCount }}</p>
                            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $sessionCount === 1 ? 'producto' : 'productos' }}</p>
                        </div>
                        <div class="flex flex-col items-center justify-center gap-2.5 px-6 py-7">
                            @if ($sessionCount > 0)
                                <a
                                    href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('index') }}?tableFilters[incomplete][isActive]=1"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 transition hover:text-primary-500 dark:text-primary-400"
                                >
                                    Completar datos
                                    <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                                </a>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Costo, margen, categoría</p>
                            @else
                                <x-filament::icon icon="heroicon-o-inbox" class="h-9 w-9 text-gray-300 dark:text-gray-600" />
                                <p class="text-sm text-gray-400 dark:text-gray-500">Sin cargas aún</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="carga-rapida-card">
                    <div class="flex items-center justify-between px-6 py-5">
                        <div class="flex items-center gap-2.5">
                            <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-gray-400" />
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recientes</h3>
                        </div>
                        @if (count($recentProducts) > 0)
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-white/10 dark:text-gray-400">
                                {{ count($recentProducts) }}
                            </span>
                        @endif
                    </div>

                    @if (count($recentProducts) > 0)
                        <div class="max-h-80 overflow-y-auto px-2 pb-2">
                            @foreach ($recentProducts as $recent)
                                <div
                                    wire:key="recent-{{ $recent['id'] }}"
                                    class="carga-rapida-recent-item group flex items-center gap-3 rounded-lg px-4 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $recent['name'] }}">
                                            {{ $recent['name'] }}
                                        </p>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <span class="text-sm font-semibold tabular-nums text-primary-600 dark:text-primary-400">
                                                ${{ number_format($recent['price'], 2, ',', '.') }}
                                            </span>
                                            @if (($recent['stock'] ?? 0) > 0)
                                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                                    · {{ $recent['stock'] }} u.
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <button
                                        wire:click="removeRecent({{ $recent['id'] }})"
                                        wire:confirm='¿Eliminar "{{ $recent['name'] }}"?'
                                        type="button"
                                        title="Deshacer"
                                        class="shrink-0 rounded-lg p-2 text-gray-300 opacity-0 transition hover:bg-danger-500/10 hover:text-danger-500 group-hover:opacity-100 dark:text-gray-600 dark:hover:text-danger-400"
                                    >
                                        <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-6 pb-8 pt-2 text-center">
                            <p class="text-sm leading-relaxed text-gray-400 dark:text-gray-500">
                                Los productos que guardes<br>aparecen acá al instante.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="carga-rapida-tip-card">
                    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        <x-filament::icon icon="heroicon-o-light-bulb" class="h-4 w-4 text-primary-500" />
                        Tips
                    </p>
                    <ul class="space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        <li>Solo nombre y precio son obligatorios.</li>
                        <li>Stock opcional — vacío = 0.</li>
                        <li>Pasá el mouse sobre un reciente para deshacer.</li>
                    </ul>
                </div>

            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: Importar desde archivo                                              --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($tab === 'archivo')
        @php
            $archivoStep = $state === 'queued' ? 2 : 1;
        @endphp

        {{-- Estado: enviado a la cola --}}
        @if ($state === 'queued')
            <div class="flex flex-col items-center gap-6 py-16 text-center">
                <span class="flex h-20 w-20 items-center justify-center rounded-2xl bg-success-500/10 text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-paper-airplane" class="h-10 w-10" />
                </span>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Archivo enviado para procesar</h3>
                    <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                        La IA está analizando <span class="font-medium text-gray-700 dark:text-gray-200">{{ $importedFileName }}</span> en segundo plano.
                        Vas a recibir una notificación cuando esté listo para revisar — podés seguir trabajando normalmente.
                    </p>
                </div>
                <button
                    wire:click="startOver"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
                    Subir otro archivo
                </button>
            </div>
        @else
        <div class="carga-rapida-layout">

            {{-- ── Columna principal ──────────────────────────────────── --}}
            <div class="flex flex-col gap-8">

                <div class="carga-rapida-card relative">
                        {{-- Overlay de análisis (visible durante la petición Livewire) --}}
                        <div
                            wire:loading.flex
                            wire:target="processFile"
                            class="carga-archivo-loading-overlay"
                        >
                            <svg class="h-12 w-12 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <div class="text-center">
                                <p class="text-base font-semibold text-gray-800 dark:text-gray-100">
                                    Enviando archivo…
                                </p>
                                <p class="mt-1.5 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                                    Guardando el archivo y enviando a procesar en segundo plano.
                                </p>
                            </div>
                        </div>

                        <div class="carga-rapida-card-header">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-o-document-arrow-up" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        Subí la lista de precios
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        PDF o Excel — la IA detecta nombre, precios y códigos
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                                Paso 1
                            </span>
                        </div>

                        <div class="carga-rapida-card-body space-y-6">
                            @if ($state === 'error')
                                <div class="flex items-start gap-3 rounded-xl bg-danger-500/10 px-4 py-3.5 text-sm text-danger-600 dark:text-danger-400">
                                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                                    <div>
                                        <p class="font-medium">No se pudo analizar el archivo</p>
                                        <p class="mt-1 text-danger-600/90 dark:text-danger-400/90">{{ $errorMessage }}</p>
                                    </div>
                                </div>
                            @endif

                            {{-- Banner: archivo duplicado detectado --}}
                            @if ($duplicateImport)
                                <div class="overflow-hidden rounded-xl border border-warning-200 bg-warning-50 dark:border-warning-500/20 dark:bg-warning-500/5">
                                    <div class="flex items-start gap-3 px-4 py-4">
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-warning-500 text-white shadow-sm">
                                            <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">
                                                Este archivo ya fue procesado
                                            </p>
                                            <p class="mt-1 text-xs text-warning-700 dark:text-warning-400">
                                                <span class="font-medium">{{ $duplicateImport['filename'] }}</span>
                                                @if ($duplicateImport['processed_at'])
                                                    · {{ $duplicateImport['processed_at'] }}
                                                @endif
                                                @if ($duplicateImport['product_count'] > 0)
                                                    · {{ $duplicateImport['product_count'] }} {{ $duplicateImport['product_count'] === 1 ? 'producto' : 'productos' }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 border-t border-warning-200 bg-warning-100/50 px-4 py-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                                        <button
                                            wire:click="cancelDuplicate"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-warning-300 bg-white px-3.5 py-2 text-xs font-medium text-warning-700 transition hover:bg-warning-50 dark:border-warning-500/30 dark:bg-transparent dark:text-warning-300 dark:hover:bg-warning-500/10"
                                        >
                                            <x-filament::icon icon="heroicon-o-x-mark" class="h-3.5 w-3.5" />
                                            Cancelar
                                        </button>
                                        <button
                                            wire:click="forceProcess"
                                            wire:loading.attr="disabled"
                                            wire:target="forceProcess"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-warning-400 focus:outline-none focus:ring-2 focus:ring-warning-400/50 disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="forceProcess" class="inline-flex items-center gap-1.5">
                                                <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                                                Sí, procesar de nuevo
                                            </span>
                                            <span wire:loading wire:target="forceProcess">Procesando…</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Zona drag & drop --}}
                            <div>
                                <label class="mb-2.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Archivo
                                </label>

                                <div
                                    x-data="{ dragging: false }"
                                    x-on:dragover.prevent="dragging = true"
                                    x-on:dragleave.prevent="dragging = false"
                                    x-on:drop.prevent="
                                        dragging = false;
                                        if ($event.dataTransfer.files.length) {
                                            $refs.fileInput.files = $event.dataTransfer.files;
                                            $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                    "
                                    @class([
                                        'carga-archivo-dropzone',
                                        'carga-archivo-dropzone--has-file' => $importFile,
                                    ])
                                    :class="{ 'carga-archivo-dropzone--dragging': dragging }"
                                    x-on:click="if (! $wire.importFile) $refs.fileInput.click()"
                                >
                                    <input
                                        x-ref="fileInput"
                                        type="file"
                                        wire:model="importFile"
                                        accept=".pdf,.xlsx,.xls"
                                        class="sr-only"
                                    />

                                    @if ($importedFileName)
                                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500/15 text-primary-600 dark:text-primary-400">
                                            <x-filament::icon icon="heroicon-o-document-check" class="h-6 w-6" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $importedFileName }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                @if ($importedFileSize){{ $importedFileSize }} · @endif listo para analizar
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click.stop="$refs.fileInput.click()"
                                            class="mt-1 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-gray-200"
                                        >
                                            <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                                            Cambiar archivo
                                        </button>
                                    @else
                                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-white/10 dark:text-gray-500">
                                            <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="h-6 w-6" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                Arrastrá el archivo acá o <span class="text-primary-600 dark:text-primary-400">elegí uno</span>
                                            </p>
                                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                PDF, XLSX o XLS · máximo 25 MB
                                            </p>
                                        </div>
                                        <div class="mt-1 flex flex-wrap justify-center gap-2">
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">PDF</span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">XLSX</span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">XLS</span>
                                        </div>
                                    @endif

                                    {{-- Subida en curso --}}
                                    <div
                                        wire:loading.flex
                                        wire:target="importFile"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-2xl bg-white/90 dark:bg-gray-900/90"
                                    >
                                        <svg class="h-8 w-8 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Subiendo archivo…</p>
                                    </div>
                                </div>

                                @error('importFile')
                                    <p class="mt-2 flex items-center gap-1.5 text-sm text-danger-600 dark:text-danger-400">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 shrink-0" />
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- Proveedor --}}
                            <div>
                                <label class="mb-2.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Proveedor <span class="ml-1 font-normal normal-case text-gray-400">(opcional)</span>
                                </label>
                                @include('filament.partials.supplier-combobox', ['size' => 'lg'])
                                @if ($supplierAutoDetected && $importSupplierId)
                                    <p class="mt-2 flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400">
                                        <x-filament::icon icon="heroicon-o-sparkles" class="h-3.5 w-3.5 shrink-0" />
                                        Proveedor detectado desde el nombre del archivo
                                    </p>
                                @else
                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                        Se asignará a todos los productos que guardes de esta importación.
                                    </p>
                                @endif
                            </div>

                            {{-- Acción --}}
                            @if (! $duplicateImport)
                            <div class="carga-rapida-actions !mt-0 !border-t-0 !pt-0">
                                <button
                                    wire:click="processFile"
                                    wire:loading.attr="disabled"
                                    wire:target="processFile,importFile"
                                    type="button"
                                    @disabled(! $importFile)
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="processFile" class="inline-flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                                        Enviar a analizar
                                    </span>
                                    <span wire:loading wire:target="processFile" class="inline-flex items-center gap-2">
                                        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                        Enviando…
                                    </span>
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── Sidebar ─────────────────────────────────────────────── --}}
            <div class="carga-rapida-sidebar">

                <div class="carga-rapida-card">
                    <div class="px-6 py-5">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400">Proceso</p>
                        <ol class="space-y-4">
                            <li class="carga-archivo-step">
                                <span @class([
                                    'carga-archivo-step-num',
                                    'carga-archivo-step-num--done' => $archivoStep > 1,
                                    'carga-archivo-step-num--active' => $archivoStep === 1,
                                    'carga-archivo-step-num--pending' => $archivoStep < 1,
                                ])>
                                    @if ($archivoStep > 1)
                                        <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5" />
                                    @else
                                        1
                                    @endif
                                </span>
                                <div>
                                    <p @class(['text-sm font-medium', 'text-gray-900 dark:text-white' => $archivoStep >= 1, 'text-gray-400 dark:text-gray-500' => $archivoStep < 1])>
                                        Subir archivo
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">PDF o Excel de tu proveedor</p>
                                </div>
                            </li>
                            <li class="carga-archivo-step">
                                <span @class([
                                    'carga-archivo-step-num',
                                    'carga-archivo-step-num--done' => $archivoStep > 2,
                                    'carga-archivo-step-num--active' => $archivoStep === 2,
                                    'carga-archivo-step-num--pending' => $archivoStep < 2,
                                ])>
                                    @if ($archivoStep > 2)
                                        <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5" />
                                    @else
                                        2
                                    @endif
                                </span>
                                <div>
                                    <p @class(['text-sm font-medium', 'text-gray-900 dark:text-white' => $archivoStep >= 2, 'text-gray-400 dark:text-gray-500' => $archivoStep < 2])>
                                        Revisar productos
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Corregí precios, categorías y duplicados</p>
                                </div>
                            </li>
                            <li class="carga-archivo-step">
                                <span @class([
                                    'carga-archivo-step-num',
                                    'carga-archivo-step-num--active' => $archivoStep === 3,
                                    'carga-archivo-step-num--pending' => $archivoStep < 3,
                                ])>3</span>
                                <div>
                                    <p @class(['text-sm font-medium', 'text-gray-900 dark:text-white' => $archivoStep >= 3, 'text-gray-400 dark:text-gray-500' => $archivoStep < 3])>
                                        Guardar en catálogo
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Solo los productos seleccionados</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="carga-rapida-tip-card">
                    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        <x-filament::icon icon="heroicon-o-light-bulb" class="h-4 w-4 text-primary-500" />
                        Tips
                    </p>
                    <ul class="space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        <li>Funciona mejor con listas de precios en texto (no escaneadas).</li>
                        <li>Los productos que ya existen se marcan para actualizar precio.</li>
                        <li>Podés desmarcar filas que no quieras importar.</li>
                    </ul>
                </div>

                <div class="carga-rapida-card">
                    <div class="px-6 py-5">
                        <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4 text-gray-400" />
                            Formatos
                        </p>
                        <div class="space-y-2.5 text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase dark:bg-white/10">PDF</span>
                                <span>Listas de proveedor, catálogos</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase dark:bg-white/10">XLSX</span>
                                <span>Planillas con columnas de precio</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endif
    @endif

</x-filament-panels::page>
