@props([
    'connection' => 'main',
    'label' => 'Balanza',
    'selected' => false,
])

@php
    $divisor = max(1, (int) config("scale.connections.{$connection}.divisor", 100));
    $decimals = \App\Services\Scale\ScaleReading::displayDecimals($divisor);
    $unit = (string) config("scale.connections.{$connection}.unit", 'kg');
@endphp

{{--
    Instrumento compacto de una balanza, para la franja siempre visible
    de la venta.

    La tarjeta usa la paleta de Filament (gray / primary / danger) para que
    claro y oscuro coincidan: un bg-danger-50 en el modo oscuro queda rosa
    claro y deja los dígitos blancos ilegibles. El rojo de "sin conexión"
    va en el LCD, la barra y el chip, no en el fondo de la tarjeta.

    El hueco del peso es siempre oscuro (como el display de la Systel),
    en los dos temas. Las clases del pozo van en el HTML, no solo en
    Alpine: Tailwind no genera utilidades que viva únicamente dentro de
    un :class.

    Los atributos extra van al elemento raíz:

        <x-scale-reader connection="main" label="Mostrador 1"
                        :selected="$selectedScale === 'main'"
                        wire:click="seleccionarBalanza('main')" />
--}}

<x-scale-hub />

<button
    type="button"
    x-data="window.scaleHub.readerData(@js($connection), {
        decimals: {{ $decimals }},
        divisor: {{ $divisor }},
        selected: @js($selected),
    })"
    :title="status === 'error' || status === 'unauthenticated' ? (errorMessage || shortStatus) : ''"
    {{ $attributes->class([
        'relative flex w-full min-w-0 cursor-pointer flex-col gap-2 rounded-xl bg-white p-3 pl-4 text-left shadow-sm transition dark:bg-gray-900',
        'ring-2 ring-primary-500' => $selected,
        'ring-1 ring-gray-200 hover:ring-gray-300 dark:ring-white/10 dark:hover:ring-white/20' => ! $selected,
    ]) }}
    :class="{
        'ring-danger-500 dark:ring-danger-400': tone === 'error' && ! selected,
    }"
>
    <span
        class="absolute inset-y-2 left-1.5 w-1 rounded-full bg-gray-300 dark:bg-gray-600"
        :class="{
            '!bg-danger-500': tone === 'error',
            '!bg-warning-400': tone === 'loading',
            '!bg-success-500': tone === 'ready',
            '!bg-primary-500': selected && (tone === 'idle' || tone === 'moving'),
        }"
    ></span>

    <div class="flex min-w-0 items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            <span class="truncate text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ $label }}
            </span>

            @if ($selected)
                <span class="inline-flex shrink-0 items-center rounded-md bg-primary-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                    En uso
                </span>
            @endif
        </div>

        <span
            class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300"
            :class="{
                '!bg-danger-500/15 !text-danger-600 dark:!text-danger-400': tone === 'error',
                '!bg-success-500/15 !text-success-700 dark:!text-success-400': tone === 'ready',
                '!bg-warning-500/15 !text-warning-700 dark:!text-warning-400': tone === 'loading',
            }"
        >
            <span
                class="h-1.5 w-1.5 rounded-full bg-current"
                :class="tone === 'moving' || tone === 'loading' ? 'animate-pulse' : ''"
            ></span>
            <span x-text="shortStatus"></span>
        </span>
    </div>

    {{-- Separado del borde de la tarjeta: si el LCD pega al anillo, deja de
         leerse como un display y parece un recuadro dentro de otro. --}}
    <div
        class="scale-lcd flex items-baseline justify-between gap-2 rounded-lg bg-gray-950 px-3 py-2 ring-1 ring-gray-800"
        :class="{
            'ring-danger-500': tone === 'error',
            'ring-warning-500': tone === 'loading',
            'ring-success-500': tone === 'ready',
        }"
    >
        <span
            class="font-mono text-4xl font-semibold leading-none tabular-nums tracking-tight text-gray-500 transition-colors"
            :class="{
                'text-danger-400': tone === 'error',
                'text-warning-400': tone === 'loading',
                'text-success-400': tone === 'ready',
                'text-gray-200': tone === 'moving',
                'animate-pulse': tone === 'moving' || tone === 'loading',
            }"
            x-text="weightLabel"
        >{{ number_format(0, $decimals, ',', '') }}</span>
        <span
            class="text-sm font-medium text-gray-500"
            :class="{
                'text-danger-400': tone === 'error',
                'text-warning-400': tone === 'loading',
                'text-success-400': tone === 'ready',
                'text-gray-400': tone === 'idle' || tone === 'moving',
            }"
        >{{ $unit }}</span>
    </div>
</button>
