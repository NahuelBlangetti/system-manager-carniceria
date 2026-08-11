<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class SalesTrend extends ChartWidget
{
    protected ?string $heading = 'Ingresos del mes';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): string|Htmlable|null
    {
        $thisMonthTotal = Sale::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('total');

        $lastMonth = Carbon::now()->subMonthNoOverflow();
        $lastMonthTotal = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])
            ->sum('total');

        $variation = $lastMonthTotal > 0
            ? round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100)
            : ($thisMonthTotal > 0 ? 100 : 0);

        return sprintf(
            'Mes actual: $%s · Mes anterior: $%s (%s%%)',
            number_format((float) $thisMonthTotal, 0, ',', '.'),
            number_format((float) $lastMonthTotal, 0, ',', '.'),
            $variation >= 0 ? '+'.$variation : $variation
        );
    }

    protected function getData(): array
    {
        $today = Carbon::now()->day;

        $revenueByDay = Sale::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->selectRaw('DAY(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = collect(range(1, $today));

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $days->map(fn ($day) => (float) ($revenueByDay[$day] ?? 0))->values()->toArray(),
                    'backgroundColor' => '#B91C1C',
                ],
            ],
            'labels' => $days->map(fn ($day) => str_pad((string) $day, 2, '0', STR_PAD_LEFT))->values()->toArray(),
        ];
    }
}
