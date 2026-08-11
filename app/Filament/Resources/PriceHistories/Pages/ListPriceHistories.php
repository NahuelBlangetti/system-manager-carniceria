<?php

namespace App\Filament\Resources\PriceHistories\Pages;

use App\Filament\Resources\PriceHistories\PriceHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListPriceHistories extends ListRecords
{
    protected static string $resource = PriceHistoryResource::class;

    public function getTitle(): string
    {
        return 'Historial de precios';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
