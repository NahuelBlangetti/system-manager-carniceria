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
    ])

    <table>
        <thead>
            <tr>
                <th style="width:70%">Producto</th>
                <th style="width:30%">Código</th>
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
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="center no-data" style="padding: 20px;">
                        No hay productos para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', [
        'footerNote' => 'Listado de productos con código interno.',
    ])

</div>
</body>
</html>
