<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Filament\Resources\Sales\Actions\PrintTicketAction;
use App\Models\Sale;
use App\Models\StockMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Nro.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Medio de pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash'     => 'Efectivo',
                        'transfer' => 'Transferencia',
                        'card'     => 'Tarjeta',
                        default    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash'     => 'success',
                        'transfer' => 'info',
                        'card'     => 'warning',
                        default    => 'gray',
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Medio de pago')
                    ->options([
                        'cash'     => 'Efectivo',
                        'transfer' => 'Transferencia',
                        'card'     => 'Tarjeta',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                    ]),
            ])
            ->recordActions([
                PrintTicketAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $record) {
                                    if ($record->status === 'completed') {
                                        foreach ($record->items as $item) {
                                            $product = \App\Models\Product::lockForUpdate()->find($item->product_id);

                                            if ($product) {
                                                $stockBefore = $product->stock;
                                                $product->increment('stock', $item->quantity);

                                                StockMovement::create([
                                                    'product_id'     => $product->id,
                                                    'user_id'        => Auth::id(),
                                                    'type'           => 'in',
                                                    'quantity'       => $item->quantity,
                                                    'stock_before'   => $stockBefore,
                                                    'stock_after'    => $stockBefore + $item->quantity,
                                                    'notes'          => "Reversión por eliminación de venta {$record->sale_number}",
                                                    'reference_type' => Sale::class,
                                                    'reference_id'   => $record->id,
                                                ]);
                                            }
                                        }
                                    }

                                    $record->delete();
                                }
                            });

                            Notification::make()
                                ->title('Ventas eliminadas')
                                ->body('El stock fue revertido para las ventas completadas.')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
