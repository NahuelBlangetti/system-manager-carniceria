<?php

namespace App\Filament\Widgets;

use App\Models\ProductBatch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class ExpirationAlerts extends TableWidget
{
    protected static ?string $heading = 'Vencimientos';

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '320px';

    private static function query()
    {
        return ProductBatch::query()
            ->with('product')
            ->active()
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::today()->addDays(3));
    }

    public static function canView(): bool
    {
        return self::query()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::query()->orderBy('expires_at')->limit(10))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->limit(25)
                    ->weight('medium'),

                TextColumn::make('remaining_quantity')
                    ->label('Restante')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 3, ',', '.').' kg'),

                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Carbon $state): string => $state->isPast() ? 'danger' : 'warning'),
            ])
            ->paginated(false);
    }
}
