<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TopProducts extends TableWidget
{
    protected static ?string $heading = 'Más vendidos esta semana';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $weekStart = Carbon::now()->startOfWeek();

        return $table
            ->query(
                SaleItem::query()
                    ->select('sale_items.*')
                    ->selectRaw('SUM(sale_items.quantity) as total_vendido')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.status', 'completed')
                    ->where('sales.created_at', '>=', $weekStart)
                    ->with('product.category')
                    ->groupBy('sale_items.product_id', 'sale_items.id')
                    ->orderByDesc('total_vendido')
                    ->limit(8)
            )
            ->columns([
                ImageColumn::make('product.image')
                    ->label('')
                    ->disk('public')
                    ->imageSize(48)
                    ->defaultImageUrl(asset('images/logo.png'))
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover']),

                TextColumn::make('product.name')
                    ->label('Producto')
                    ->weight('semibold'),

                TextColumn::make('product.category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_vendido')
                    ->label('Vendidos')
                    ->formatStateUsing(fn (float $state, $record): string => number_format(
                        $state, in_array($record->product?->unit, ['kg', 'g', 'litro'], true) ? 3 : 0, ',', '.'
                    ))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('product.sale_price')
                    ->label('Precio')
                    ->money('ARS'),

                TextColumn::make('product.stock')
                    ->label('Stock actual')
                    ->formatStateUsing(fn (float $state, $record): string => number_format(
                        $state, in_array($record->product?->unit, ['kg', 'g', 'litro'], true) ? 3 : 0, ',', '.'
                    ))
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->product?->stock <= 0 => 'danger',
                        $record->product?->stock <= 5 => 'warning',
                        default                       => 'success',
                    }),
            ])
            ->paginated(false);
    }
}
