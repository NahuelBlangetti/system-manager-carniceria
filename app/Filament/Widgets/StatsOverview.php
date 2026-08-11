<?php

namespace App\Filament\Widgets;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();

        $ventasHoy = Sale::whereDate('created_at', $today)->where('status', 'completed')->count();
        $ventasAyer = Sale::whereDate('created_at', $yesterday)->where('status', 'completed')->count();

        $ingresosHoy = Sale::whereDate('created_at', $today)->where('status', 'completed')->sum('total');
        $ingresosMes = Sale::where('created_at', '>=', $thisMonth)->where('status', 'completed')->sum('total');

        $stockBajo = Product::where('active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->count();

        $totalProductos = Product::where('active', true)->count();
        $cajaAbierta = CashRegister::where('status', 'open')->exists();

        $ventasSemana = collect(range(6, 0))->map(
            fn ($d) => Sale::whereDate('created_at', Carbon::today()->subDays($d))
                ->where('status', 'completed')->count()
        )->values()->toArray();

        $ingresosSemana = collect(range(6, 0))->map(
            fn ($d) => (float) Sale::whereDate('created_at', Carbon::today()->subDays($d))
                ->where('status', 'completed')->sum('total')
        )->values()->toArray();

        $tendenciaVentas = $ventasAyer > 0
            ? (($ventasHoy - $ventasAyer) / $ventasAyer * 100)
            : ($ventasHoy > 0 ? 100 : 0);

        return [
            Stat::make('Ventas hoy', $ventasHoy)
                ->description($tendenciaVentas >= 0
                    ? '+' . round($tendenciaVentas) . '% vs ayer'
                    : round($tendenciaVentas) . '% vs ayer')
                ->descriptionIcon($tendenciaVentas >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($tendenciaVentas >= 0 ? 'success' : 'warning')
                ->chart($ventasSemana),

            Stat::make('Ingresos hoy', '$' . number_format($ingresosHoy, 0, ',', '.'))
                ->description('Mes: $' . number_format($ingresosMes, 0, ',', '.'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info')
                ->chart($ingresosSemana),

            Stat::make('Productos activos', $totalProductos)
                ->description($stockBajo > 0 ? $stockBajo . ' con stock bajo' : 'Stock en orden')
                ->descriptionIcon($stockBajo > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($stockBajo > 0 ? 'warning' : 'success'),

            Stat::make('Caja', $cajaAbierta ? 'Abierta' : 'Cerrada')
                ->description($cajaAbierta ? 'Turno en curso' : 'Sin turno activo')
                ->descriptionIcon('heroicon-o-building-library')
                ->color($cajaAbierta ? 'success' : 'gray'),
        ];
    }
}
