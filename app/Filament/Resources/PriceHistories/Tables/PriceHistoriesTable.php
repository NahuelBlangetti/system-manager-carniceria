<?php

namespace App\Filament\Resources\PriceHistories\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PriceHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->placeholder('—'),
                TextColumn::make('old_cost_price')
                    ->label('Costo anterior')
                    ->money('ARS')
                    ->color('gray'),
                TextColumn::make('new_cost_price')
                    ->label('Costo nuevo')
                    ->money('ARS')
                    ->weight('medium')
                    ->color(fn ($record): string => $record->new_cost_price > $record->old_cost_price ? 'danger' : 'success'),
                TextColumn::make('old_sale_price')
                    ->label('Venta anterior')
                    ->money('ARS')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('new_sale_price')
                    ->label('Venta nueva')
                    ->money('ARS'),
                TextColumn::make('margin_percentage')
                    ->label('Margen')
                    ->formatStateUsing(fn ($state): string => $state ? number_format($state, 1) . '%' : '—')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        $state < 15    => 'danger',
                        $state < 25    => 'warning',
                        default        => 'success',
                    }),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->label('Producto')
                    ->searchable(),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Proveedor')
                    ->searchable(),
            ]);
    }
}
