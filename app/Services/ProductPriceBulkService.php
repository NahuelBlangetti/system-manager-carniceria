<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPriceBulkService
{
    public const MODE_COST_KEEP_MARGIN = 'cost_keep_margin';
    public const MODE_SALE_ONLY = 'sale_only';
    public const MODE_BOTH = 'both';

    /**
     * @param  Builder<Product>|Collection<int, Product>  $products
     */
    public function applyPercentage(Builder | Collection $products, float $percentage, string $mode): int
    {
        if ($products instanceof Builder) {
            $products = $products->get();
        }

        if ($products->isEmpty()) {
            return 0;
        }

        $multiplier = 1 + ($percentage / 100);
        $updated = 0;

        DB::transaction(function () use ($products, $multiplier, $mode, &$updated): void {
            foreach ($products as $product) {
                $changes = $this->calculateChanges($product, $multiplier, $mode);

                if ($changes === null) {
                    continue;
                }

                $product->update($changes);
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * @return array<string, float>|null
     */
    private function calculateChanges(Product $product, float $multiplier, string $mode): ?array
    {
        $cost = (float) $product->cost_price;
        $sale = (float) $product->sale_price;
        $margin = (float) $product->margin_percentage;

        return match ($mode) {
            self::MODE_COST_KEEP_MARGIN => [
                'cost_price' => round($cost * $multiplier, 2),
                'sale_price' => round($cost * $multiplier * (1 + $margin / 100), 2),
            ],
            self::MODE_SALE_ONLY => [
                'sale_price' => round($sale * $multiplier, 2),
                'margin_percentage' => $cost > 0
                    ? round((($sale * $multiplier) / $cost - 1) * 100, 2)
                    : $margin,
            ],
            self::MODE_BOTH => [
                'cost_price' => round($cost * $multiplier, 2),
                'sale_price' => round($sale * $multiplier, 2),
            ],
            default => null,
        };
    }

    public function countForSupplier(int $supplierId): int
    {
        return Product::query()
            ->where('supplier_id', $supplierId)
            ->count();
    }
}
