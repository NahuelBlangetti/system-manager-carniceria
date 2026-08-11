<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarcassPurchaseItem extends Model
{
    protected $fillable = [
        'carcass_purchase_id',
        'product_id',
        'weight_kg',
        'unit_cost',
        'expiration_date',
    ];

    protected $casts = [
        'weight_kg'       => 'decimal:3',
        'unit_cost'       => 'decimal:2',
        'expiration_date' => 'date',
    ];

    public function carcassPurchase(): BelongsTo
    {
        return $this->belongsTo(CarcassPurchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
