<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad del pesaje en cada ítem vendido.
 *
 * En una carnicería esto no es opcional: cuando el cliente vuelve diciendo
 * "me cobraste 2 kg y era kilo y medio", hay que poder reconstruir de dónde
 * salió esa cantidad. Además `weight_source` sirve de control interno: un
 * ítem por peso vendido como 'manual' es exactamente el patrón que conviene
 * poder filtrar al cierre de caja.
 *
 * `unit` se guarda como snapshot, por el mismo motivo que ya se guardaba
 * `product_name`: si mañana el producto pasa de kg a unidad, el ticket
 * histórico tiene que seguir diciendo lo que se cobró en su momento. Sin
 * esto el comprobante no puede saber si "1,250" son kilos o unidades, y
 * termina redondeando gramos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->after('barcode');

            // 'scale' = lo pesó la balanza, 'manual' = lo tipeó el operador,
            // null = ítem por unidad, donde el pesaje no aplica.
            $table->string('weight_source', 20)->nullable()->after('quantity');

            // Qué balanza pesó, para poder auditar por mostrador.
            $table->string('scale_connection', 50)->nullable()->after('weight_source');

            $table->timestamp('weighed_at')->nullable()->after('scale_connection');

            // Cuentas crudas que devolvió la balanza (ej. "1250"). Es lo que
            // permite detectar un divisor mal configurado a posteriori.
            $table->string('raw_reading', 32)->nullable()->after('weighed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit',
                'weight_source',
                'scale_connection',
                'weighed_at',
                'raw_reading',
            ]);
        });
    }
};
