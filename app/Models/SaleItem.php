<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /** Unidades que se venden pesando, y por lo tanto admiten captura de balanza. */
    public const WEIGHABLE_UNITS = ['kg', 'g'];

    public const SOURCE_SCALE = 'scale';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'sku',
        'barcode',
        'unit',
        'unit_price',
        'quantity',
        'subtotal',
        'weight_source',
        'scale_connection',
        'weighed_at',
        'raw_reading',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'weighed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function isWeighable(?string $unit): bool
    {
        return in_array($unit, self::WEIGHABLE_UNITS, true);
    }

    /** ¿Se cobró un peso que nadie pesó? Es la señal a revisar en una auditoría. */
    public function wasWeighedByHand(): bool
    {
        return $this->weight_source === self::SOURCE_MANUAL;
    }
}
