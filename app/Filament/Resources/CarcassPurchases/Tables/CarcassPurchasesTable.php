<?php

namespace App\Filament\Resources\CarcassPurchases\Tables;

use App\Models\CarcassPurchase;
use App\Services\CarcassPurchaseService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CarcassPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('purchase_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->placeholder('—'),
                TextColumn::make('animal_type')
                    ->label('Animal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vacuno'  => 'Vacuno',
                        'cerdo'   => 'Cerdo',
                        'pollo'   => 'Pollo',
                        'cordero' => 'Cordero',
                        default   => 'Otro',
                    }),
                TextColumn::make('carcass_weight_kg')
                    ->label('Peso comprado')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 3, ',', '.').' kg'),
                TextColumn::make('total_weight_obtained')
                    ->label('Peso obtenido')
                    ->getStateUsing(fn (CarcassPurchase $record): string => number_format($record->totalWeightObtained(), 3, ',', '.').' kg'),
                TextColumn::make('yield_percentage')
                    ->label('Rendimiento')
                    ->badge()
                    ->getStateUsing(fn (CarcassPurchase $record): ?float => $record->yieldPercentage())
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 1, ',', '.').'%')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 60     => 'danger',
                        $state < 75     => 'warning',
                        default         => 'success',
                    }),
                TextColumn::make('total_cost')
                    ->label('Costo total')
                    ->money('ARS'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'confirmed' ? 'Confirmado' : 'Borrador')
                    ->color(fn (string $state): string => $state === 'confirmed' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(['draft' => 'Borrador', 'confirmed' => 'Confirmado']),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirmar despiece')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CarcassPurchase $record): bool => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('¿Confirmar este despiece?')
                    ->modalDescription('Se actualizará el stock y el costo de cada corte. Esta acción no se puede deshacer.')
                    ->action(function (CarcassPurchase $record): void {
                        try {
                            app(CarcassPurchaseService::class)->confirm($record);

                            Notification::make()
                                ->title('Despiece confirmado')
                                ->body('El stock y el costo de los cortes fueron actualizados.')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('No se pudo confirmar el despiece')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
