<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Actions\AdjustProductPricesAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): ?string
    {
        return 'Filtrá por proveedor o categoría, seleccioná los productos y exportá el PDF desde las acciones masivas.';
    }

    protected function getHeaderActions(): array
    {
        return [
            AdjustProductPricesAction::make(),
            CreateAction::make(),
        ];
    }
}
