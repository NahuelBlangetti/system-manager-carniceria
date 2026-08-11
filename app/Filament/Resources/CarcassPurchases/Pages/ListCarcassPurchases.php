<?php

namespace App\Filament\Resources\CarcassPurchases\Pages;

use App\Filament\Resources\CarcassPurchases\CarcassPurchaseResource;
use App\Filament\Widgets\YieldOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarcassPurchases extends ListRecords
{
    protected static string $resource = CarcassPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [YieldOverview::class];
    }
}
