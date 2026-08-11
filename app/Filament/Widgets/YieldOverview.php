<?php

namespace App\Filament\Widgets;

use App\Models\CarcassPurchase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class YieldOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return CarcassPurchase::where('status', 'confirmed')->exists();
    }

    protected function getStats(): array
    {
        $recent = CarcassPurchase::where('status', 'confirmed')
            ->with('items')
            ->where('confirmed_at', '>=', Carbon::now()->subDays(30))
            ->get();

        if ($recent->isEmpty()) {
            $recent = CarcassPurchase::where('status', 'confirmed')
                ->with('items')
                ->latest('confirmed_at')
                ->limit(5)
                ->get();
        }

        $yields = $recent->map(fn (CarcassPurchase $purchase) => $purchase->yieldPercentage())
            ->filter(fn (?float $yield) => $yield !== null);

        $avgYield = $yields->isNotEmpty() ? round($yields->avg(), 1) : null;
        $count    = $recent->count();

        return [
            Stat::make('Rendimiento promedio', $avgYield !== null ? "{$avgYield}%" : '—')
                ->description($count > 0 ? 'Últimos ' . $count . ' ' . \Illuminate\Support\Str::plural('despiece', $count) : 'Sin despieces confirmados')
                ->descriptionIcon('heroicon-o-scale')
                ->color(match (true) {
                    $avgYield === null => 'gray',
                    $avgYield < 60      => 'danger',
                    $avgYield < 75      => 'warning',
                    default             => 'success',
                }),
        ];
    }
}
