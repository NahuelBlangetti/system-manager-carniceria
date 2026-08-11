<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('nueva_venta')
                ->label('Nueva Venta')
                ->icon('heroicon-o-shopping-bag')
                ->url(CrearVenta::getUrl())
                ->color('primary')
                ->size('lg'),
        ];
    }
}
