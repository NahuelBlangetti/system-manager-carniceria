<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL ENUM no soporta ALTER COLUMN directo — se modifica con CHANGE
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE product_imports MODIFY COLUMN status ENUM('pending','processing','done','error','validated') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE product_imports MODIFY COLUMN status ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending'"
        );
    }
};
