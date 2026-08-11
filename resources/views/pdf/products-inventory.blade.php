<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.styles', [
        'fontSize'    => '9px',
        'pagePadding' => '22px 28px',
        'thPadding'   => '6px 7px',
        'thFontSize'  => '8px',
        'tdPadding'   => '5px 7px',
        'badgeFontSize' => '7.5px',
    ])
</head>
<body>
<div class="page">

    @include('pdf.partials.header', [
        'title'     => $pdfTitle,
        'dateLabel' => now()->format('d/m/Y H:i') . ' hs.',
        'audience'  => 'Proveedores',
    ])

    <table>
        <thead>
            <tr>
                <th style="width:22%">Producto</th>
                <th style="width:10%">Código</th>
                <th style="width:12%">Categoría</th>
                <th style="width:12%">Proveedor</th>
                <th class="center" style="width:7%">Unidad</th>
                <th class="right" style="width:10%">Costo</th>
                <th class="right" style="width:10%">Venta</th>
                <th class="center" style="width:8%">Margen</th>
                <th class="center" style="width:9%">Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $stockClass = match(true) {
                        $product->stock <= 0                   => 'stock-danger',
                        $product->stock <= $product->min_stock => 'stock-warning',
                        default                                  => 'stock-ok',
                    };
                    $marginClass = match(true) {
                        $product->margin_percentage === null => '',
                        $product->margin_percentage < 15      => 'margin-danger',
                        $product->margin_percentage < 25      => 'margin-warning',
                        default                                => 'margin-ok',
                    };
                @endphp
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>
                        @if ($product->barcode)
                            <span class="badge">{{ $product->barcode }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($product->category)
                            <span class="badge">{{ $product->category->name }}</span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($product->supplier)
                            {{ $product->supplier->name }}
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td class="center">
                        {{ match($product->unit) {
                            'unidad' => 'Unid.',
                            'kg'     => 'Kg',
                            'g'      => 'Gr',
                            'litro'  => 'Litro',
                            'caja'   => 'Caja',
                            'par'    => 'Par',
                            default  => $product->unit,
                        } }}
                    </td>
                    <td class="right cost">
                        ${{ number_format($product->cost_price, 2, ',', '.') }}
                    </td>
                    <td class="right price">
                        ${{ number_format($product->sale_price, 2, ',', '.') }}
                    </td>
                    <td class="center">
                        @if ($product->margin_percentage !== null)
                            <span class="badge {{ $marginClass }}">
                                {{ number_format($product->margin_percentage, 1) }}%
                            </span>
                        @else
                            <span class="no-data">—</span>
                        @endif
                    </td>
                    <td class="center">
                        <span class="badge {{ $stockClass }}">{{ number_format($product->stock, in_array($product->unit, ['kg', 'g', 'litro'], true) ? 3 : 0, ',', '.') }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', [
        'footerNote' => 'Inventario para proveedores. Precios en pesos argentinos (ARS).',
    ])

</div>
</body>
</html>
