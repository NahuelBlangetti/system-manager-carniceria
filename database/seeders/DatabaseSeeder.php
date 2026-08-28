<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SupplierSeeder::class,
            // ProductSeeder::class es catálogo demo (Vacuno, Embutidos y Fiambres,
            // Congelados y Elaborados, Insumos de Mostrador, etc.). Se sacó del
            // flujo automático para que no conviva con la lista de precios real.
            ProductPriceListSeeder::class,
        ]);
    }
}
