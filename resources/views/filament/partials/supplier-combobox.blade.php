{{--
    Combobox de proveedor con búsqueda + creación rápida.
    Props:
      $size  'lg' (idle state, ancho completo) | 'sm' (done header, compacto)
--}}
@php
    $size ??= 'lg';
    $isLg = $size === 'lg';
    $suppliersJson = collect($supplierOptions)
        ->map(fn ($name, $id) => ['id' => $id, 'name' => $name])
        ->values()
        ->toJson();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        supplierId: $wire.entangle('importSupplierId'),
        suppliers: {{ $suppliersJson }},
        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.suppliers;
            return this.suppliers.filter(s => {
                const n = s.name.toLowerCase();
                return n.includes(q) || q.includes(n);
            });
        },
        get similarExists() {
            // Avisa si ya hay un proveedor con nombre idéntico (ignorando mayúsculas).
            const q = this.search.toLowerCase().trim();
            return q && this.suppliers.some(s => s.name.toLowerCase() === q);
        },
        init() {
            if (this.supplierId) {
                const found = this.suppliers.find(s => s.id == this.supplierId);
                if (found) this.search = found.name;
            }
            this.$wire.on('supplier-created', ({ id, name }) => {
                if (!this.suppliers.find(s => s.id == id)) {
                    this.suppliers.push({ id, name });
                }
                this.supplierId = id;
                this.search = name;
                this.open = false;
            });
            this.$wire.on('supplier-selected', ({ id, name }) => {
                const found = this.suppliers.find(s => s.id == id);
                if (found) {
                    this.supplierId = id;
                    this.search = found.name;
                    this.open = false;
                }
            });
        },
        select(s) {
            this.supplierId = s.id;
            this.search = s.name;
            this.open = false;
            this.$wire.set('supplierAutoDetected', false);
        },
        clear() {
            this.supplierId = null;
            this.search = '';
            this.$wire.set('supplierAutoDetected', false);
            this.$nextTick(() => this.$refs.input.focus());
        },
        createNew() {
            const name = this.search.trim();
            if (!name) return;
            this.$wire.createSupplier(name);
        }
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative {{ $isLg ? 'w-full' : 'w-56' }}"
>
    {{-- Input --}}
    <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="{{ $isLg ? 'h-4 w-4' : 'h-3.5 w-3.5' }}" />
        </span>
        <input
            x-ref="input"
            type="text"
            x-model="search"
            @focus="open = true"
            @input="open = true; if (!search.trim()) { supplierId = null; }"
            @keydown.enter.prevent="filtered.length === 1 ? select(filtered[0]) : (filtered.length === 0 && search.trim() ? createNew() : null)"
            @keydown.arrow-down.prevent="open = true"
            placeholder="Buscar proveedor…"
            autocomplete="off"
            @class([
                'block w-full rounded-xl border border-gray-300 bg-white text-gray-900 shadow-sm transition',
                'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20',
                'dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder-gray-500',
                'py-3 pl-9 pr-9 text-sm' => $isLg,
                'py-1.5 pl-8 pr-7 text-xs' => ! $isLg,
            ])
        />
        {{-- Chip de seleccionado / botón limpiar --}}
        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <template x-if="supplierId">
                <button
                    type="button"
                    @click.stop="clear()"
                    title="Quitar proveedor"
                    @class([
                        'rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-gray-200',
                        'p-1' => $isLg,
                        'p-0.5' => ! $isLg,
                    ])
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="{{ $isLg ? 'h-4 w-4' : 'h-3 w-3' }}" />
                </button>
            </template>
        </div>
    </div>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1.5 w-full min-w-[14rem] origin-top rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-900"
        style="display: none"
    >
        {{-- Resultados --}}
        <template x-if="filtered.length > 0">
            <div class="max-h-52 overflow-y-auto">
                <template x-for="s in filtered" :key="s.id">
                    <button
                        type="button"
                        @click.prevent="select(s)"
                        @class([
                            'flex w-full items-center gap-2 text-left text-gray-800 transition hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-primary-500/10',
                            'px-4 py-2.5 text-sm' => $isLg,
                            'px-3 py-2 text-xs' => ! $isLg,
                        ])
                    >
                        <x-filament::icon icon="heroicon-o-building-storefront" class="{{ $isLg ? 'h-4 w-4' : 'h-3.5 w-3.5' }} shrink-0 text-gray-400" />
                        <span x-text="s.name"></span>
                        <template x-if="s.id == supplierId">
                            <x-filament::icon icon="heroicon-o-check" class="{{ $isLg ? 'h-4 w-4' : 'h-3 w-3' }} ml-auto text-primary-500" />
                        </template>
                    </button>
                </template>
            </div>
        </template>

        {{-- Sin resultados + opción crear --}}
        <template x-if="search.trim() && filtered.length === 0">
            <div @class(['px-4 py-3 space-y-2' => $isLg, 'px-3 py-2 space-y-1.5' => ! $isLg])>
                <p @class(['text-sm text-gray-400' => $isLg, 'text-xs text-gray-400' => ! $isLg])>
                    No se encontró ningún proveedor.
                </p>

                {{-- Aviso si el nombre ya existe con distinta capitalización --}}
                <template x-if="similarExists">
                    <p @class(['text-sm text-warning-600 dark:text-warning-400' => $isLg, 'text-xs text-warning-600 dark:text-warning-400' => ! $isLg])>
                        Ya existe un proveedor con ese nombre. Se seleccionará el existente.
                    </p>
                </template>

                <button
                    type="button"
                    @click.prevent="createNew()"
                    wire:loading.attr="disabled"
                    wire:target="createSupplier"
                    class="{{ $isLg ? 'px-4 py-2 text-sm' : 'px-3 py-1.5 text-xs' }} inline-flex items-center gap-1.5 rounded-lg font-semibold text-white transition disabled:opacity-60"
                    :class="similarExists ? 'bg-warning-500 hover:bg-warning-400' : 'bg-primary-600 hover:bg-primary-500'"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="{{ $isLg ? 'h-4 w-4' : 'h-3 w-3' }}" />
                    <template x-if="!similarExists">
                        <span>Crear "<span x-text="search.trim()"></span>"</span>
                    </template>
                    <template x-if="similarExists">
                        <span>Usar "<span x-text="search.trim()"></span>"</span>
                    </template>
                </button>
            </div>
        </template>

        {{-- Dropdown vacío sin búsqueda --}}
        <template x-if="!search.trim() && suppliers.length === 0">
            <p @class(['px-4 py-3 text-sm text-gray-400' => $isLg, 'px-3 py-2 text-xs text-gray-400' => ! $isLg])>
                No hay proveedores cargados.
            </p>
        </template>
    </div>
</div>
