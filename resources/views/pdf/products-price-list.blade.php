<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.styles')
</head>
<body>
<div class="page">

    @include('pdf.partials.header', [
        'title'     => $pdfTitle,
        'dateLabel' => now()->format('d/m/Y'),
        'audience'  => 'Clientes',
    ])

    <table>
        <thead>
            <tr>
                <th style="width:40%">Producto</th>
                <th style="width:15%">Código</th>
                <th style="width:20%">Categoría</th>
                <th class="center" style="width:10%">Unidad</th>
                <th class="right" style="width:15%">Precio</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
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
                    <td class="right price">
                        ${{ number_format($product->sale_price, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', [
        'footerNote' => 'Lista de precios para clientes. Precios en pesos argentinos (ARS) e incluyen IVA.',
    ])

</div>
</body>
</html>
