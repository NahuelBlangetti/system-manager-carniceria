<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deduplicar filas existentes antes de agregar el constraint: conserva el id menor.
        DB::statement("
            DELETE s1 FROM suppliers s1
            INNER JOIN suppliers s2
            ON LOWER(s1.name) = LOWER(s2.name) AND s1.id > s2.id
        ");

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
