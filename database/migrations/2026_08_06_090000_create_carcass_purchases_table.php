<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carcass_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('animal_type', ['vacuno', 'cerdo', 'pollo', 'cordero', 'otro']);
            $table->date('purchase_date');
            $table->decimal('carcass_weight_kg', 10, 3);
            $table->decimal('total_cost', 10, 2);
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carcass_purchases');
    }
};
