<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all()->keyBy('name');

        $frigorifico = $suppliers['Frigorífico San Cayetano S.A.'] ?? null;
        $granja      = $suppliers['Granja Avícola Los Pinos'] ?? null;
        $chacinados  = $suppliers['Chacinados y Fiambres del Sur S.A.'] ?? null;
        $cerdos      = $suppliers['Cerdos del Norte S.R.L.'] ?? null;
        $insumos     = $suppliers['Distribuidora de Insumos Carnicero S.A.'] ?? null;

        // [nombre, categoría, costo, margen%, unidad, stock, min_stock, proveedor]
        $products = [

            // ── VACUNO ────────────────────────────────────────────────────────
            ['Asado de tira',                  'Vacuno', 3800, 35, 'kg', 22.500, 8, $frigorifico],
            ['Vacío',                          'Vacuno', 4200, 32, 'kg', 15.750, 6, $frigorifico],
            ['Matambre',                       'Vacuno', 4500, 30, 'kg', 10.200, 4, $frigorifico],
            ['Bife de chorizo',                'Vacuno', 5800, 30, 'kg', 18.400, 6, $frigorifico],
            ['Bife ancho (ojo de bife)',       'Vacuno', 6200, 28, 'kg', 12.600, 5, $frigorifico],
            ['Lomo',                           'Vacuno', 8500, 25, 'kg', 7.300,  3, $frigorifico],
            ['Peceto',                         'Vacuno', 5200, 30, 'kg', 9.800,  4, $frigorifico],
            ['Nalga',                          'Vacuno', 4800, 32, 'kg', 16.500, 6, $frigorifico],
            ['Cuadrada',                       'Vacuno', 4600, 32, 'kg', 14.200, 5, $frigorifico],
            ['Paleta',                         'Vacuno', 3600, 35, 'kg', 13.900, 5, $frigorifico],
            ['Roast beef',                     'Vacuno', 5000, 30, 'kg', 8.600,  3, $frigorifico],
            ['Falda',                          'Vacuno', 2800, 38, 'kg', 20.100, 8, $frigorifico],
            ['Osobuco',                        'Vacuno', 3200, 35, 'kg', 11.400, 4, $frigorifico],
            ['Carne picada especial',          'Vacuno', 3400, 35, 'kg', 24.800, 10, $frigorifico],
            ['Carne picada común',             'Vacuno', 2900, 38, 'kg', 28.300, 10, $frigorifico],
            ['Colita de cuadril',              'Vacuno', 6000, 28, 'kg', 9.100,  4, $frigorifico],
            ['Cuadril',                        'Vacuno', 5400, 30, 'kg', 13.700, 5, $frigorifico],
            ['Tapa de asado',                  'Vacuno', 3900, 33, 'kg', 12.300, 5, $frigorifico],
            ['Milanesa de nalga',              'Vacuno', 5200, 32, 'kg', 10.900, 4, $frigorifico],
            ['Bola de lomo',                   'Vacuno', 4400, 32, 'kg', 11.600, 4, $frigorifico],

            // ── CERDO ─────────────────────────────────────────────────────────
            ['Bondiola de cerdo',              'Cerdo', 4200, 32, 'kg', 9.500,  4, $cerdos],
            ['Matambrito de cerdo',            'Cerdo', 4600, 30, 'kg', 6.800,  3, $cerdos],
            ['Costillas de cerdo (asado)',     'Cerdo', 3200, 35, 'kg', 14.200, 5, $cerdos],
            ['Carré de cerdo',                 'Cerdo', 3800, 32, 'kg', 8.400,  3, $cerdos],
            ['Pechito de cerdo',                'Cerdo', 3400, 35, 'kg', 10.100, 4, $cerdos],
            ['Pata muslo de cerdo',            'Cerdo', 2600, 38, 'kg', 7.900,  3, $cerdos],
            ['Panceta de cerdo',               'Cerdo', 3600, 33, 'kg', 11.300, 4, $cerdos],
            ['Solomillo de cerdo',             'Cerdo', 5800, 28, 'kg', 4.600,  2, $cerdos],
            ['Chuleta de cerdo',               'Cerdo', 3300, 35, 'kg', 9.700,  4, $cerdos],
            ['Carne picada de cerdo',          'Cerdo', 2800, 35, 'kg', 8.200,  3, $cerdos],

            // ── POLLO ─────────────────────────────────────────────────────────
            ['Pollo entero',                   'Pollo', 2200, 30, 'kg', 32.000, 12, $granja],
            ['Pechuga de pollo',               'Pollo', 3200, 32, 'kg', 18.500, 7, $granja],
            ['Muslo de pollo',                 'Pollo', 2400, 32, 'kg', 20.300, 8, $granja],
            ['Pata muslo de pollo',            'Pollo', 2300, 32, 'kg', 22.100, 8, $granja],
            ['Alitas de pollo',                'Pollo', 2100, 35, 'kg', 15.600, 6, $granja],
            ['Suprema de pollo',               'Pollo', 3400, 30, 'kg', 12.400, 5, $granja],
            ['Milanesa de pollo',              'Pollo', 3600, 32, 'kg', 14.800, 5, $granja],
            ['Carcasa de pollo (para caldo)',  'Pollo',  900, 40, 'kg', 9.200,  4, $granja],
            ['Higadito de pollo',              'Pollo', 1800, 38, 'kg', 6.500,  3, $granja],
            ['Molleja de pollo',               'Pollo', 2000, 38, 'kg', 5.900,  3, $granja],

            // ── ACHURAS ───────────────────────────────────────────────────────
            ['Chinchulines',                   'Achuras', 3200, 35, 'kg', 8.400, 3, $frigorifico],
            ['Mollejas de vaca',               'Achuras', 6500, 28, 'kg', 4.100, 2, $frigorifico],
            ['Riñón vacuno',                   'Achuras', 1800, 40, 'kg', 5.600, 2, $frigorifico],
            ['Hígado vacuno',                  'Achuras', 1600, 40, 'kg', 6.900, 3, $frigorifico],
            ['Tripa gorda',                    'Achuras', 2800, 35, 'kg', 4.700, 2, $frigorifico],
            ['Corazón vacuno',                 'Achuras', 2000, 38, 'kg', 5.200, 2, $frigorifico],
            ['Ubre',                           'Achuras', 2400, 35, 'kg', 3.800, 2, $frigorifico],

            // ── EMBUTIDOS Y FIAMBRES ──────────────────────────────────────────
            ['Chorizo parrillero',             'Embutidos y Fiambres', 3200, 35, 'kg', 16.500, 6, $chacinados],
            ['Morcilla',                       'Embutidos y Fiambres', 2400, 35, 'kg', 9.800,  4, $chacinados],
            ['Salchicha parrillera',           'Embutidos y Fiambres', 2800, 35, 'kg', 8.600,  3, $chacinados],
            ['Jamón cocido',                   'Embutidos y Fiambres', 4200, 30, 'kg', 7.200,  3, $chacinados],
            ['Jamón crudo',                    'Embutidos y Fiambres', 9500, 25, 'kg', 3.400,  2, $chacinados],
            ['Salame',                         'Embutidos y Fiambres', 5200, 28, 'kg', 4.900,  2, $chacinados],
            ['Mortadela',                      'Embutidos y Fiambres', 3200, 32, 'kg', 6.100,  3, $chacinados],
            ['Queso de máquina',               'Embutidos y Fiambres', 2800, 35, 'kg', 5.800,  3, $chacinados],
            ['Bondiola ahumada',               'Embutidos y Fiambres', 6500, 28, 'kg', 3.200,  2, $chacinados],
            ['Panceta ahumada',                'Embutidos y Fiambres', 4800, 30, 'kg', 5.500,  2, $chacinados],
            ['Longaniza',                      'Embutidos y Fiambres', 3400, 33, 'kg', 6.700,  3, $chacinados],

            // ── CONGELADOS Y ELABORADOS ───────────────────────────────────────
            ['Milanesas de nalga rebozadas x6', 'Congelados y Elaborados', 3800, 32, 'unidad', 18, 6, $frigorifico],
            ['Hamburguesas caseras x4',        'Congelados y Elaborados', 2600, 35, 'unidad', 22, 8, $frigorifico],
            ['Empanadas de carne x12',         'Congelados y Elaborados', 4200, 30, 'unidad', 15, 5, $chacinados],
            ['Matambre arrollado relleno',     'Congelados y Elaborados', 5800, 28, 'unidad', 8,  3, $frigorifico],
            ['Pollo relleno',                  'Congelados y Elaborados', 6200, 25, 'unidad', 5,  2, $granja],
            ['Pata muslo marinada x1kg',       'Congelados y Elaborados', 2600, 32, 'unidad', 12, 4, $granja],

            // ── INSUMOS DE MOSTRADOR ──────────────────────────────────────────
            ['Bolsas de vacío chicas (x100)',  'Insumos de Mostrador', 3200, 30, 'unidad', 20, 8, $insumos],
            ['Bolsas de vacío grandes (x100)', 'Insumos de Mostrador', 4200, 30, 'unidad', 15, 6, $insumos],
            ['Bobina de film para envolver',   'Insumos de Mostrador', 2800, 35, 'unidad', 12, 5, $insumos],
            ['Bandejas de telgopor chicas (x50)',  'Insumos de Mostrador', 1800, 38, 'unidad', 18, 6, $insumos],
            ['Bandejas de telgopor grandes (x50)', 'Insumos de Mostrador', 2400, 38, 'unidad', 15, 5, $insumos],
            ['Rollo de etiquetas para balanza', 'Insumos de Mostrador', 1500, 40, 'unidad', 25, 8, $insumos],
            ['Guantes de nitrilo (caja x100)',  'Insumos de Mostrador', 6500, 32, 'caja',   10, 4, $insumos],
        ];

        $categoryCache = [];

        foreach ($products as $item) {
            [$name, $catName, $cost, $margin, $unit, $stock, $minStock, $supplier] = $item;

            // Crear categoría si no existe
            if (! isset($categoryCache[$catName])) {
                $categoryCache[$catName] = Category::firstOrCreate(
                    ['slug' => Str::slug($catName)],
                    ['name' => $catName, 'active' => true]
                );
            }

            $salePrice = round($cost * (1 + $margin / 100), 2);

            Product::firstOrCreate(
                ['name' => $name],
                [
                    'category_id'        => $categoryCache[$catName]->id,
                    'supplier_id'        => $supplier?->id,
                    'sale_price'         => $salePrice,
                    'cost_price'         => $cost,
                    'margin_percentage'  => $margin,
                    'unit'               => $unit,
                    'stock'              => $stock,
                    'min_stock'          => $minStock,
                    'active'             => true,
                ]
            );
        }
    }
}
