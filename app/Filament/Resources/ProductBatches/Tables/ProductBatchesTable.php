<?php

namespace App\Filament\Resources\ProductBatches\Tables;

use App\Models\ProductBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class ProductBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('quantity')
                    ->label('Cantidad ingresada')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 3, ',', '.').' kg')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('remaining_quantity')
                    ->label('Restante')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 3, ',', '.').' kg'),
                TextColumn::make('received_at')
                    ->label('Ingreso')
                    ->date('d/m/Y'),
                TextColumn::make('expires_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('Sin vencimiento')
                    ->sortable()
                    ->badge()
                    ->color(fn (ProductBatch $record): string => match (true) {
                        $record->expires_at === null           => 'gray',
                        $record->status === 'expired'          => 'danger',
                        $record->expires_at->isPast()           => 'danger',
                        $record->expires_at->diffInDays(Carbon::today()) <= 3 => 'warning',
                        default                                  => 'success',
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state, ProductBatch $record): string => match (true) {
                        $state === 'active' && $record->expires_at?->isPast() => 'Vencido',
                        $state === 'active'    => 'Activo',
                        $state === 'depleted'  => 'Agotado',
                        $state === 'expired'   => 'Vencido',
                        $state === 'discarded' => 'Descartado',
                        default                => $state,
                    })
                    ->color(fn (string $state, ProductBatch $record): string => match (true) {
                        $state === 'active' && $record->expires_at?->isPast() => 'danger',
                        $state === 'active'    => 'success',
                        $state === 'depleted'  => 'gray',
                        $state === 'expired'   => 'danger',
                        $state === 'discarded' => 'gray',
                        default                => 'gray',
                    }),
                TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => $state === \App\Models\CarcassPurchase::class ? 'Despiece' : 'Carga manual')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active'    => 'Activo',
                        'depleted'  => 'Agotado',
                        'expired'   => 'Vencido',
                        'discarded' => 'Descartado',
                    ]),
            ])
            ->recordActions([
                Action::make('discard')
                    ->label('Descartar lote')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (ProductBatch $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('¿Descartar este lote?')
                    ->modalDescription('Esto solo marca el lote como descartado para dejar de alertar sobre su vencimiento. No ajusta el stock del producto — si corresponde, ajustá el stock manualmente.')
                    ->action(function (ProductBatch $record): void {
                        $record->update(['status' => 'discarded']);

                        Notification::make()
                            ->title('Lote descartado')
                            ->send();
                    }),
            ]);
    }
}
