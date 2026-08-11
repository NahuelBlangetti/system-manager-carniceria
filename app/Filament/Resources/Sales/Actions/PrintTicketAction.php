<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Models\Sale;
use App\Services\Tickets\SaleTicketEscPosBuilder;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;

class PrintTicketAction
{
    public static function make(): Action
    {
        return Action::make('printTicket')
            ->label('Imprimir ticket')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (Sale $record): bool => $record->status === 'completed')
            ->action(function (Sale $record, HasTable $livewire): void {
                $ticket = app(SaleTicketEscPosBuilder::class)->build($record);

                $livewire->dispatch('print-escpos-ticket', content: $ticket);
            });
    }
}
