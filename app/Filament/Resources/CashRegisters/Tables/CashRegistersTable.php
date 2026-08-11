<?php

namespace App\Filament\Resources\CashRegisters\Tables;

use App\Filament\Resources\CashRegisters\Actions\CloseCashRegisterAction;
use App\Models\CashRegister;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashRegistersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),
                TextColumn::make('opening_amount')
                    ->label('Monto apertura')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('cash_sales_total')
                    ->label('Ventas efectivo')
                    ->state(fn (CashRegister $record): float => $record->cashSalesTotal())
                    ->money('ARS'),
                TextColumn::make('transfer_sales_total')
                    ->label('Ventas transferencia')
                    ->state(fn (CashRegister $record): float => $record->transferSalesTotal())
                    ->money('ARS')
                    ->placeholder('—'),
                TextColumn::make('closing_amount')
                    ->label('Monto cierre')
                    ->money('ARS')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('expected_amount')
                    ->label('Monto esperado')
                    ->money('ARS')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('difference')
                    ->label('Diferencia')
                    ->money('ARS')
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state > 0  => 'success',
                        (float) $state < 0  => 'danger',
                        default             => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open'   => 'Abierta',
                        'closed' => 'Cerrada',
                        default  => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open'   => 'success',
                        'closed' => 'gray',
                        default  => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                CloseCashRegisterAction::make(),
                EditAction::make()
                    ->label(fn (CashRegister $record): string => $record->status === 'open' ? 'Gestionar' : 'Ver'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
