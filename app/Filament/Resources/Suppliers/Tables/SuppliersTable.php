<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Filament\Resources\Products\Actions\AdjustProductPricesAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('contact_person')
                    ->label('Contacto')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—'),
                TextColumn::make('payment_terms')
                    ->label('Condición de pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'contado'      => 'Contado',
                        '15_dias'      => '15 días',
                        '30_dias'      => '30 días',
                        '60_dias'      => '60 días',
                        'consignacion' => 'Consignación',
                        default        => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'contado'      => 'success',
                        '15_dias'      => 'info',
                        '30_dias'      => 'info',
                        '60_dias'      => 'warning',
                        'consignacion' => 'gray',
                        default        => 'gray',
                    }),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->badge()
                    ->color('primary'),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Activos'),
            ])
            ->recordActions([
                AdjustProductPricesAction::make()
                    ->modalHeading(fn ($record): string => "Ajustar precios — {$record->name}"),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
