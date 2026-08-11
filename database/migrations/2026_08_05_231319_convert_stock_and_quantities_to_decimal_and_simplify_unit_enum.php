<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Una carnicería vende por kg fraccionado (ej. 1.250 kg), así que
     * stock/cantidades pasan de integer a decimal(10,3). Se usa SQL crudo
     * porque el proyecto no tiene doctrine/dbal instalado (requerido por
     * Schema::table()->change() en columnas existentes).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY COLUMN stock DECIMAL(10,3) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY COLUMN min_stock DECIMAL(10,3) NOT NULL DEFAULT 0');
        DB::statement("ALTER TABLE products MODIFY COLUMN unit ENUM('unidad', 'kg', 'g', 'litro', 'caja', 'par') NOT NULL DEFAULT 'unidad'");

        DB::statement('ALTER TABLE sale_items MODIFY COLUMN quantity DECIMAL(10,3) NOT NULL');

        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN quantity DECIMAL(10,3) NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN stock_before DECIMAL(10,3) NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN stock_after DECIMAL(10,3) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN stock_after INT NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN stock_before INT NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN quantity INT NOT NULL');

        DB::statement('ALTER TABLE sale_items MODIFY COLUMN quantity INT NOT NULL');

        DB::statement("ALTER TABLE products MODIFY COLUMN unit ENUM('unidad', 'metro', 'm2', 'kg', 'g', 'litro', 'caja', 'rollo', 'par', 'docena') NOT NULL DEFAULT 'unidad'");
        DB::statement('ALTER TABLE products MODIFY COLUMN min_stock INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY COLUMN stock INT NOT NULL DEFAULT 0');
    }
};
