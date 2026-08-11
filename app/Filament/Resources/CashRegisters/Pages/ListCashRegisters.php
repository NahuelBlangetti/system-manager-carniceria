<?php

namespace App\Filament\Resources\CashRegisters\Pages;

use App\Filament\Resources\CashRegisters\Actions\CloseCashRegisterAction;
use App\Filament\Resources\CashRegisters\CashRegisterResource;
use App\Models\CashRegister;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashRegisters extends ListRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CloseCashRegisterAction::make(),
            CreateAction::make()
                ->label('Abrir caja')
                ->visible(fn (): bool => ! CashRegister::where('status', 'open')->exists()),
        ];
    }
}
