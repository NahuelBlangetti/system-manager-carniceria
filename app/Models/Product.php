<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    // Laravel's Str::plural() usa reglas en inglés (p. ej. "unidad" -> "unidads"),
    // por eso los plurales en español van a mano acá.
    private const UNIT_PLURALS = [
        'unidad' => 'unidades',
        'metro'  => 'metros',
        'litro'  => 'litros',
        'caja'   => 'cajas',
        'rollo'  => 'rollos',
        'par'    => 'pares',
        'docena' => 'docenas',
    ];

    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'sku',
        'barcode',
        'unit',
        'description',
        'image',
        'cost_price',
        'sale_price',
        'bulk_price_2kg',
        'margin_percentage',
        'stock',
        'min_stock',
        'active',
    ];

    protected $casts = [
        'cost_price'        => 'decimal:2',
        'sale_price'        => 'decimal:2',
        'bulk_price_2kg'    => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'stock'             => 'decimal:3',
        'min_stock'         => 'decimal:3',
        'active'            => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function carcassPurchaseItems(): HasMany
    {
        return $this->hasMany(CarcassPurchaseItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Decimales con los que se muestra una cantidad de esta unidad.
     *
     * Vive acá para que la pantalla y el ticket impreso coincidan: si el
     * comprobante redondeara los gramos, no daría lo mismo que el número que
     * el cliente vio en el display de la balanza.
     */
    public static function quantityDecimals(?string $unit): int
    {
        return in_array($unit, ['kg', 'g', 'litro'], true) ? 3 : 0;
    }

    public static function formatQuantity(string $unit, float|string $quantity): string
    {
        $quantity  = (float) $quantity;
        $decimals  = self::quantityDecimals($unit);
        $formatted = number_format($quantity, $decimals, ',', '.');

        $label = ($quantity !== 1.0)
            ? (self::UNIT_PLURALS[$unit] ?? $unit)
            : $unit;

        return "{$formatted} {$label}";
    }
}
