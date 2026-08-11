<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'cash_register_id',
        'sale_number',
        'payment_method',
        'subtotal',
        'discount',
        'total',
        'notes',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->sale_number)) {
                // Placeholder único mientras la BD asigna el ID. Se reemplaza en 'created'.
                $sale->sale_number = 'V-TEMP-' . uniqid('', true);
            }
        });

        static::created(function (Sale $sale) {
            // Usa el ID auto-increment como fuente del número: único por definición, sin race condition.
            $sale->updateQuietly([
                'sale_number' => 'V-' . str_pad($sale->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
