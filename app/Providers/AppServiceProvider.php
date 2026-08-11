<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // La app es 100% en español: forzamos locale aunque el .env
        // o un config:cache viejo traigan "en" (evita notificaciones
        // de Filament como "Deleted" en lugar de "Borrado").
        app()->setLocale('es');
        \Carbon\Carbon::setLocale('es');

        Product::observe(ProductObserver::class);
    }
}
