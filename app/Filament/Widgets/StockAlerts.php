<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlerts extends TableWidget
{
    protected static ?string $heading = 'Alertas de stock';

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        return Product::where('active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with('category')
                    ->where('active', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->where('min_stock', '>', 0)
                    ->orderBy('stock')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Producto')
                    ->limit(25)
                    ->weight('medium'),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->formatStateUsing(fn (float $state, $record): string => number_format(
                        $state, in_array($record->unit, ['kg', 'g', 'litro'], true) ? 3 : 0, ',', '.'
                    ))
                    ->badge()
                    ->color(fn (float $state): string => $state <= 0 ? 'danger' : 'warning'),

                TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->formatStateUsing(fn (float $state, $record): string => number_format(
                        $state, in_array($record->unit, ['kg', 'g', 'litro'], true) ? 3 : 0, ',', '.'
                    ))
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}
