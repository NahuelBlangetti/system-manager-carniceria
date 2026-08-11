<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductBatch extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'remaining_quantity',
        'received_at',
        'expires_at',
        'source_type',
        'source_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'remaining_quantity'  => 'decimal:3',
        'received_at'         => 'date',
        'expires_at'          => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
