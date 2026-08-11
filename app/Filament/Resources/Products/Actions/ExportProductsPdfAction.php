<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Category;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductsPdfAction
{
    private const TYPES = [
        'barcodes' => [
            'label'        => 'Códigos de barras',
            'modalHeading' => 'Exportar códigos de barras',
            'description'  => 'Listado con nombre del producto y su código de barras.',
            'pdfTitle'     => 'Códigos de Barras',
            'view'         => 'pdf.products-barcodes',
            'orientation'  => 'portrait',
            'filename'     => 'codigos-barras',
        ],
        'price_list' => [
            'label'        => 'Lista de precios (Clientes)',
            'modalHeading' => 'Exportar lista de precios para clientes',
            'description'  => 'Para entregar o enviar a clientes. Incluye nombre, código, categoría y precio de venta.',
            'pdfTitle'     => 'Lista de Precios — Clientes',
            'view'         => 'pdf.products-price-list',
            'orientation'  => 'portrait',
            'filename'     => 'lista-precios',
        ],
        'inventory' => [
            'label'        => 'Inventario interno (Proveedores)',
            'modalHeading' => 'Exportar inventario para proveedores',
            'description'  => 'Para compartir con proveedores. Incluye costo, margen, stock y datos del proveedor.',
            'pdfTitle'     => 'Inventario Interno — Proveedores',
            'view'         => 'pdf.products-inventory',
            'orientation'  => 'landscape',
            'filename'     => 'inventario',
        ],
    ];

    public static function make(string $type = 'price_list'): Action
    {
        $meta = self::TYPES[$type];

        return Action::make('exportPdf_' . $type)
            ->label($meta['label'])
            ->icon('heroicon-o-document-arrow-down')
            ->modalHeading($meta['modalHeading'])
            ->modalDescription(fn (HasTable $livewire): string => $meta['description'] . ' '
                . self::exportScopeDescription($livewire->getTableQueryForExport()->count()))
            ->modalSubmitActionLabel('Descargar PDF')
            ->modalWidth('sm')
            ->action(fn (HasTable $livewire): mixed => self::exportFromQuery(
                $livewire->getTableQueryForExport(),
                $type,
            ));
    }

    public static function bulk(string $type = 'price_list'): BulkAction
    {
        $meta = self::TYPES[$type];

        return BulkAction::make('exportPdf_' . $type)
            ->label($meta['label'])
            ->icon('heroicon-o-document-arrow-down')
            ->color('info')
            ->modalHeading($meta['modalHeading'])
            ->modalDescription(fn (Collection $records): string => $meta['description'] . ' '
                . self::exportScopeDescription($records->count()))
            ->modalSubmitActionLabel('Descargar PDF')
            ->modalWidth('sm')
            ->action(fn (Collection $records): mixed => self::exportFromCollection($records, $type))
            ->deselectRecordsAfterCompletion();
    }

    public static function exportFromQuery($query, string $type): mixed
    {
        $products = $query
            ->with(['category', 'supplier'])
            ->orderBy(
                Category::select('name')
                    ->whereColumn('categories.id', 'products.category_id'),
            )
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            Notification::make()
                ->title('Sin productos para exportar')
                ->body('No hay productos que coincidan con los filtros actuales.')
                ->warning()
                ->send();

            return null;
        }

        return self::download($products, $type);
    }

    public static function exportFromCollection(Collection $records, string $type): mixed
    {
        $records->load(['category', 'supplier']);

        $products = $records->sortBy(
            fn ($product) => ($product->category?->name ?? 'zzz') . $product->name,
        )->values();

        if ($products->isEmpty()) {
            Notification::make()
                ->title('Sin productos para exportar')
                ->body('Seleccioná al menos un producto.')
                ->warning()
                ->send();

            return null;
        }

        return self::download($products, $type);
    }

    public static function download(Collection $products, string $type): StreamedResponse
    {
        $meta = self::TYPES[$type];

        $pdf = Pdf::loadView($meta['view'], [
            'products' => $products,
            'store'    => config('store'),
            'pdfTitle' => $meta['pdfTitle'],
        ])->setPaper('a4', $meta['orientation']);

        $slug = str(config('store.name'))->slug();
        $filename = "{$meta['filename']}-{$slug}-" . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private static function exportScopeDescription(int $count): string
    {
        return $count === 1
            ? 'Se exportará 1 producto.'
            : "Se exportarán {$count} productos.";
    }
}
