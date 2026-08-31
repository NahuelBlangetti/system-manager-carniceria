<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\ButcherPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pone en caja una base ya existente (ej. producción) contra la lista de
 * precios oficial de App\Support\ButcherPriceList: crea lo que falta,
 * corrige precio/precio x2kg/unidad de lo que ya existe, y da de baja
 * (soft delete, nunca borrado físico) cualquier producto activo que no
 * pertenezca a la lista — típicamente catálogo demo o de pruebas.
 *
 * No toca costo, stock, mínimo ni ningún otro dato operativo de un
 * producto que ya existe: solo corrige lo que definió esta conversación
 * (precio, precio por 2kg y unidad).
 */
class SyncProductPriceList extends Command
{
    protected $signature = 'products:sync-price-list
        {--dry-run : Mostrar los cambios sin guardarlos}
        {--force : No pedir confirmación antes de dar de baja productos}';

    protected $description = 'Sincroniza el catálogo de productos con la lista de precios oficial de la carnicería';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulación: no se va a guardar nada.');
        }

        [$canonicalKeys, $created, $updated, $unchanged] = $this->syncCanonicalProducts($dryRun);

        $strays = Product::with('category')
            ->get()
            ->reject(fn (Product $product): bool => in_array(
                $this->keyFor($product->category_id, $product->name),
                $canonicalKeys,
                true,
            ));

        $removed = $this->removeStrays($strays, $dryRun);

        $this->newLine();
        $this->info("Creados: {$created} · Actualizados: {$updated} · Sin cambios: {$unchanged} · Dados de baja: {$removed}");

        return self::SUCCESS;
    }

    /** @return array{0: array<int, string>, 1: int, 2: int, 3: int} */
    private function syncCanonicalProducts(bool $dryRun): array
    {
        $categoryCache = [];
        $canonicalKeys = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach (ButcherPriceList::PRODUCTS as [$catName, $name, $price, $price2kg]) {
            if (! isset($categoryCache[$catName])) {
                $categoryCache[$catName] = $dryRun
                    ? Category::firstWhere('slug', Str::slug($catName))
                    : Category::firstOrCreate(
                        ['slug' => Str::slug($catName)],
                        ['name' => $catName, 'active' => true]
                    );
            }

            $category = $categoryCache[$catName];
            $unit = ButcherPriceList::unitFor($catName);

            if ($category) {
                $canonicalKeys[] = $this->keyFor($category->id, $name);
            }

            $product = $category
                ? Product::where('name', $name)->where('category_id', $category->id)->first()
                : null;

            if (! $product) {
                $created++;
                $this->line("  + crear      {$catName} / {$name} — \$" . number_format($price, 0, ',', '.') .
                    ($price2kg ? ' (2kg=$' . number_format($price2kg, 0, ',', '.') . ')' : '') . " [{$unit}]");

                if (! $dryRun) {
                    Product::create([
                        'name'           => $name,
                        'category_id'    => $category->id,
                        'sale_price'     => $price,
                        'bulk_price_2kg' => $price2kg,
                        'unit'           => $unit,
                        'cost_price'     => 0,
                        'stock'          => 0,
                        'min_stock'      => 0,
                        'active'         => true,
                    ]);
                }

                continue;
            }

            $changes = $this->diff($product, $price, $price2kg, $unit);

            if ($changes === []) {
                $unchanged++;

                continue;
            }

            $updated++;
            $this->line("  ~ actualizar {$catName} / {$name}: " . implode(', ', $changes));

            if (! $dryRun) {
                $product->update([
                    'sale_price'     => $price,
                    'bulk_price_2kg' => $price2kg,
                    'unit'           => $unit,
                ]);
            }
        }

        return [$canonicalKeys, $created, $updated, $unchanged];
    }

    /** @return array<int, string> Descripciones de los campos que cambian, para el log. */
    private function diff(Product $product, int $price, ?int $price2kg, string $unit): array
    {
        $changes = [];

        if ((float) $product->sale_price !== (float) $price) {
            $changes[] = "precio \${$product->sale_price} → \${$price}";
        }

        $currentBulk = $product->bulk_price_2kg !== null ? (float) $product->bulk_price_2kg : null;
        $desiredBulk = $price2kg !== null ? (float) $price2kg : null;

        if ($currentBulk !== $desiredBulk) {
            $changes[] = 'precio 2kg $' . ($currentBulk ?? '—') . ' → $' . ($desiredBulk ?? '—');
        }

        if ($product->unit !== $unit) {
            $changes[] = "unidad {$product->unit} → {$unit}";
        }

        return $changes;
    }

    private function removeStrays(Collection $strays, bool $dryRun): int
    {
        if ($strays->isEmpty()) {
            return 0;
        }

        $this->newLine();
        $this->warn("Productos que NO están en la lista oficial ({$strays->count()}):");

        foreach ($strays as $product) {
            $this->line("  - {$product->category?->name} / {$product->name}");
        }

        if ($dryRun) {
            return $strays->count();
        }

        if (! $this->option('force') && ! $this->confirm('¿Dar de baja (soft delete) estos productos?', false)) {
            $this->comment('Baja cancelada. El resto de la sincronización ya se guardó.');

            return 0;
        }

        foreach ($strays as $product) {
            $product->delete();
        }

        return $strays->count();
    }

    private function keyFor(int $categoryId, string $name): string
    {
        return $categoryId . '|' . mb_strtolower(trim($name));
    }
}
