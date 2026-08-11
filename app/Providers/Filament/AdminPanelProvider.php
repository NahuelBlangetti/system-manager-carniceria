<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ExpirationAlerts;
use App\Filament\Widgets\SalesTrend;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\StockAlerts;
use App\Filament\Widgets\TopProducts;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName(config('store.name'))
            ->brandLogo(asset('images/logo-compact.png'))
            ->darkModeBrandLogo(asset('images/logo-compact-dark.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/favicon.png'))
            ->colors([
                'primary' => Color::hex(config('store.primary_color')),
                'gray'    => Color::Zinc,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.print-agent-listener')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                StatsOverview::class,
                SalesTrend::class,
                TopProducts::class,
                StockAlerts::class,
                ExpirationAlerts::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
