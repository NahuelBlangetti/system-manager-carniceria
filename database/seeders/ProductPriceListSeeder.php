<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductPriceListSeeder extends Seeder
{
    public function run(): void
    {
        // [categoría, producto, precio, precio 2 kg (null si no aplica), unidad]
        // Carbón y leña se venden por bolsa/paquete (no se pesan sueltos en
        // el mostrador), por eso van en "unidad" aunque el precio original
        // venga de la columna "Precio 1 kg".
        $products = [
            // ── TERNERA ───────────────────────────────────────────────────────
            ['Ternera', 'Costilla', 22000, null],
            ['Ternera', 'Vacío', 22000, null],
            ['Ternera', 'Matambre', 24000, null],
            ['Ternera', 'Tapa de asado', 23000, null],
            ['Ternera', 'Tapa de nalga', 22000, null],
            ['Ternera', 'Falda', 16000, null],
            ['Ternera', 'Falda especial', 18000, null],
            ['Ternera', 'Entrecot', 18000, null],
            ['Ternera', 'Bocado ancho', 16000, null],
            ['Ternera', 'Bocado fino', 13000, null],
            ['Ternera', 'Costeletas', 16000, null],
            ['Ternera', 'Agujas', 13000, 20000],
            ['Ternera', 'Jamón cuadrado', 20000, null],
            ['Ternera', 'Bola de lomo', 20000, null],
            ['Ternera', 'Nalga', 22000, null],
            ['Ternera', 'Cuadril', 22000, null],
            ['Ternera', 'Peceto', 21000, null],
            ['Ternera', 'Paleta', 16000, null],
            ['Ternera', 'Palomita', 15000, null],
            ['Ternera', 'Tortuguita', 15000, null],

            // ── PREPARADOS Y OTROS ───────────────────────────────────────────
            ['Preparados y otros', 'Molida común', 12000, null],
            ['Preparados y otros', 'Molida especial', 15000, null],
            ['Preparados y otros', 'Osobuco', 12000, null],
            ['Preparados y otros', 'Puchero común', 7000, null],
            ['Preparados y otros', 'Cogotera', 12000, null],
            ['Preparados y otros', 'Milanesas de carne', 15000, 24000],
            ['Preparados y otros', 'Milanesas de pollo', 10000, 18000],
            ['Preparados y otros', 'Milanesas de cerdo', 7000, 13000],
            ['Preparados y otros', 'Milanesas de soja', 11000, null],
            ['Preparados y otros', 'Hamburguesas de carne', 14000, null],
            ['Preparados y otros', 'Hamburguesas de cerdo', 10000, 18000],
            ['Preparados y otros', 'Medallones de espinaca', 12000, null],

            // ── POLLO ─────────────────────────────────────────────────────────
            ['Pollo', 'Medallones de pollo', 10500, null],
            ['Pollo', 'Patitas de pollo', 10000, null],
            ['Pollo', 'Pata muslo', 6500, 12000],
            ['Pollo', 'Pollo entero', 6500, null],

            // ── ACHURAS ───────────────────────────────────────────────────────
            ['Achuras', 'Chinchulín', 12000, null],
            ['Achuras', 'Riñón', 12000, null],
            ['Achuras', 'Hígado', 6500, null],
            ['Achuras', 'Mondongo', 11000, null],
            ['Achuras', 'Lengua', 12000, null],
            ['Achuras', 'Morcilla', 12000, null],

            // ── CERDO ─────────────────────────────────────────────────────────
            ['Cerdo', 'Chorizo de cerdo premium', 15000, null],
            ['Cerdo', 'Chorizo de cerdo económico', 12000, null],
            ['Cerdo', 'Costilla', 8000, null],
            ['Cerdo', 'Matambre', 14500, null],
            ['Cerdo', 'Entrecot', 10000, null],
            ['Cerdo', 'Vacío', 10000, null],
            ['Cerdo', 'Bocado', 7000, 12000],
            ['Cerdo', 'Agujas', 8000, 15000],
            ['Cerdo', 'Costillitas', 8000, 15000],
            ['Cerdo', 'Osobuco', 5000, null],
            ['Cerdo', 'Pulpa', 8600, null],

            // ── OTROS (se venden por bolsa, no por peso) ────────────────────
            ['Otros', 'Carbón', 4700, null],
            ['Otros', 'Leña', 4500, null],
        ];

        $categoryCache = [];

        foreach ($products as [$catName, $name, $price1kg, $price2kg]) {
            $unit = $catName === 'Otros' ? 'unidad' : 'kg';
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
                    'unit'           => $unit,
                    'stock'          => 0,
                    'min_stock'      => 0,
                    'active'         => true,
                ]
            );
        }
    }
}
