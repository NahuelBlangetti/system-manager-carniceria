<?php

namespace App\Observers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductObserver
{
    public function updating(Product $product): void
    {
        if (! $product->isDirty('cost_price') && ! $product->isDirty('sale_price')) {
            return;
        }

        $oldCost = (float) $product->getOriginal('cost_price');
        $newCost = (float) $product->cost_price;
        $oldSale = (float) $product->getOriginal('sale_price');
        $newSale = (float) $product->sale_price;

        if ($oldCost === $newCost && $oldSale === $newSale) {
            return;
        }

        $margin = $newCost > 0 ? round(($newSale / $newCost - 1) * 100, 2) : null;

        PriceHistory::create([
            'product_id'        => $product->id,
            'user_id'           => Auth::id() ?? 1,
            'supplier_id'       => $product->supplier_id,
            'old_cost_price'    => $oldCost,
            'new_cost_price'    => $newCost,
            'old_sale_price'    => $oldSale,
            'new_sale_price'    => $newSale,
            'margin_percentage' => $margin,
        ]);
    }
}
