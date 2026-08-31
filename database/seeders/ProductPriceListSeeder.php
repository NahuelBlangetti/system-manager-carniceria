<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\ButcherPriceList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga inicial para una instalación nueva: crea la lista de precios oficial
 * si todavía no existe, sin tocar ni borrar nada. Para poner en caja una base
 * ya existente (ej. producción, con productos demo o desactualizados) usar
 * el comando `products:sync-price-list` en su lugar.
 */
class ProductPriceListSeeder extends Seeder
{
    public function run(): void
    {
        $categoryCache = [];

        foreach (ButcherPriceList::PRODUCTS as [$catName, $name, $price1kg, $price2kg]) {
            if (! isset($categoryCache[$catName])) {
                $categoryCache[$catName] = Category::firstOrCreate(
                    ['slug' => Str::slug($catName)],
                    ['name' => $catName, 'active' => true]
                );
            }

            // name + category porque hay nombres repetidos entre categorías
            // (ej. "Costilla" en Ternera y en Cerdo son productos distintos).
            Product::firstOrCreate(
                [
                    'name'        => $name,
                    'category_id' => $categoryCache[$catName]->id,
                ],
                [
                    'sale_price'     => $price1kg,
                    'bulk_price_2kg' => $price2kg,
                    'cost_price'     => 0,
                    'unit'           => ButcherPriceList::unitFor($catName),
                    'stock'          => 0,
                    'min_stock'      => 0,
                    'active'         => true,
                ]
            );
        }
    }
}
