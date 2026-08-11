<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->enum('unit', ['unidad', 'metro', 'm2', 'kg', 'g', 'litro', 'caja', 'rollo', 'par', 'docena'])
                ->default('unidad')
                ->after('barcode');
            $table->decimal('margin_percentage', 5, 2)->default(0)->after('sale_price');
            $table->dropColumn('imei');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'unit', 'margin_percentage']);
            $table->string('imei')->nullable();
        });
    }
};
