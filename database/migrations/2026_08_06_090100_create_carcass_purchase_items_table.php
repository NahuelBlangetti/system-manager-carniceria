<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carcass_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carcass_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 10, 3);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carcass_purchase_items');
    }
};
