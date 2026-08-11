<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarcassPurchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'user_id',
        'animal_type',
        'purchase_date',
        'carcass_weight_kg',
        'total_cost',
        'status',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'purchase_date'     => 'date',
        'carcass_weight_kg' => 'decimal:3',
        'total_cost'        => 'decimal:2',
        'confirmed_at'      => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarcassPurchaseItem::class);
    }

    public function totalWeightObtained(): float
    {
        return (float) $this->items->sum('weight_kg');
    }

    public function shrinkageKg(): float
    {
        return max(0, (float) $this->carcass_weight_kg - $this->totalWeightObtained());
    }

    public function yieldPercentage(): ?float
    {
        if ((float) $this->carcass_weight_kg <= 0) {
            return null;
        }

        return round($this->totalWeightObtained() / (float) $this->carcass_weight_kg * 100, 1);
    }
}
