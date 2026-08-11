<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->decimal('remaining_quantity', 10, 3);
            $table->date('received_at');
            $table->date('expires_at')->nullable();
            $table->nullableMorphs('source');
            $table->enum('status', ['active', 'depleted', 'expired', 'discarded'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
