<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
            return;
        }

        $skuUniqueIndexes = collect(Schema::getIndexes('products'))
            ->filter(fn (array $index) => $index['unique'] && in_array('sku', $index['columns'], true));

        if ($skuUniqueIndexes->isEmpty()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($skuUniqueIndexes) {
            foreach ($skuUniqueIndexes as $index) {
                $table->dropUnique($index['name']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
            return;
        }

        $hasSkuUnique = collect(Schema::getIndexes('products'))
            ->contains(fn (array $index) => $index['unique'] && in_array('sku', $index['columns'], true));

        if ($hasSkuUnique) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
        });
    }
};
