<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'supplier_id',
        'old_cost_price',
        'new_cost_price',
        'old_sale_price',
        'new_sale_price',
        'margin_percentage',
        'notes',
    ];

    protected $casts = [
        'old_cost_price'   => 'decimal:2',
        'new_cost_price'   => 'decimal:2',
        'old_sale_price'   => 'decimal:2',
        'new_sale_price'   => 'decimal:2',
        'margin_percentage' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
