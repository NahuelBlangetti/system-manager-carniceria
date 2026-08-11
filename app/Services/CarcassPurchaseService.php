<?php

namespace App\Services;

use App\Models\CarcassPurchase;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class CarcassPurchaseService
{
    public function confirm(CarcassPurchase $purchase): void
    {
        if ($purchase->status !== 'draft') {
            throw new \RuntimeException('Este despiece ya fue confirmado.');
        }

        if ((float) $purchase->carcass_weight_kg <= 0) {
            throw new \RuntimeException('El peso comprado debe ser mayor a 0.');
        }

        $items = $purchase->items()->orderBy('product_id')->get();
        $totalWeightObtained = (float) $items->sum('weight_kg');

        if ($totalWeightObtained <= 0) {
            throw new \RuntimeException('Agregá al menos un corte con peso mayor a 0 antes de confirmar.');
        }

        $costPerKg = round((float) $purchase->total_cost / $totalWeightObtained, 2);

        DB::transaction(function () use ($purchase, $items, $costPerKg): void {
            foreach ($items as $item) {
                if ((float) $item->weight_kg <= 0) {
                    continue;
                }

                $product = Product::lockForUpdate()->find($item->product_id);

                if (! $product) {
                    continue;
                }

                $stockBefore = (float) $product->stock;
                $weight      = (float) $item->weight_kg;
                $newStock    = $stockBefore + $weight;

                $avgCost = round((($stockBefore * (float) $product->cost_price) + ($weight * $costPerKg)) / $newStock, 2);

                $margin = ($product->sale_price > 0 && $avgCost > 0)
                    ? round(((float) $product->sale_price / $avgCost - 1) * 100, 2)
                    : $product->margin_percentage;

                $product->update([
                    'cost_price'        => $avgCost,
                    'margin_percentage' => $margin,
                ]);

                $product->increment('stock', $weight);

                $item->update(['unit_cost' => $costPerKg]);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'user_id'        => $purchase->user_id,
                    'type'           => 'in',
                    'quantity'       => $weight,
                    'stock_before'   => $stockBefore,
                    'stock_after'    => $newStock,
                    'notes'          => "Despiece #{$purchase->id}",
                    'reference_type' => CarcassPurchase::class,
                    'reference_id'   => $purchase->id,
                ]);

                if ($item->expiration_date) {
                    ProductBatch::create([
                        'product_id'          => $product->id,
                        'quantity'            => $weight,
                        'remaining_quantity'  => $weight,
                        'received_at'         => $purchase->purchase_date,
                        'expires_at'          => $item->expiration_date,
                        'source_type'         => CarcassPurchase::class,
                        'source_id'           => $purchase->id,
                        'status'              => 'active',
                    ]);
                }
            }

            $purchase->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });
    }
}
