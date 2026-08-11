<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScrapeProductos extends Command
{
    protected $signature = 'scrape:productos {--dry-run : Mostrar productos sin guardar}';
    protected $description = 'Importa productos desde asistel.tiendanegocio.com con sus imágenes';

    private Client $client;

    public function handle(): int
    {
        $this->client = new Client(['timeout' => 30, 'verify' => false]);

        $this->info('Obteniendo listado de productos...');
        $products = $this->fetchProductList();

        if (empty($products)) {
            $this->error('No se encontraron productos.');
            return self::FAILURE;
        }

        $this->info("Se encontraron {$products->count()} productos.");

        if ($this->option('dry-run')) {
            $this->table(['Título', 'Precio', 'Stock', 'Imagen'], $products->map(fn ($p) => [
                $p['title'], '$' . number_format($p['price'], 0, ',', '.'), $p['stock'], $p['thumbnail'],
            ]));
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $created = 0;
        $updated = 0;

        foreach ($products as $data) {
            $slug = $data['hash'];
            $name = trim($data['title']);

            // Obtener categoría desde la página individual (con fallback estático)
            $category = $this->fetchCategory($slug, (int) $data['id']);

            // Descargar imagen
            $imagePath = $this->downloadImage($data['thumbnail'], $slug);

            $exists = Product::withTrashed()->where('name', $name)->first();

            $attributes = [
                'category_id' => $category?->id,
                'sale_price'  => $data['price'],
                'stock'       => is_numeric($data['stock']) ? (int) $data['stock'] : 0,
                'image'       => $imagePath,
                'active'      => $data['status'] == 1,
                'deleted_at'  => null,
            ];

            if ($exists) {
                $exists->restore();
                $exists->update($attributes);
                $updated++;
            } else {
                Product::create(array_merge(['name' => $name], $attributes));
                $created++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Importación completa: {$created} creados, {$updated} actualizados.");
        $this->info('Imágenes guardadas en: storage/app/public/products/');

        return self::SUCCESS;
    }

    private function fetchProductList(): \Illuminate\Support\Collection
    {
        $html = (string) $this->client->get('https://asistel.tiendanegocio.com/productos')->getBody();
        $html = str_replace('&q;', '"', $html);

        // El JSON de productos está embebido en el HTML como state de Angular
        $priceIdx = strpos($html, '"priceFormat"');
        if ($priceIdx === false) {
            return collect();
        }

        $before = substr($html, 0, $priceIdx);
        $startIdx = strrpos($before, '[{');
        if ($startIdx === false) {
            return collect();
        }

        // Avanzar hasta el cierre del array
        $depth = 0;
        $endIdx = $startIdx;
        for ($i = $startIdx; $i < strlen($html); $i++) {
            if ($html[$i] === '[') $depth++;
            elseif ($html[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    $endIdx = $i + 1;
                    break;
                }
            }
        }

        $json = substr($html, $startIdx, $endIdx - $startIdx);

        try {
            return collect(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            $this->error("Error parseando JSON: {$e->getMessage()}");
            return collect();
        }
    }

    /**
     * Mapa estático id_producto => nombre_categoría.
     * Se usa como fallback cuando la página del producto no incluye datos de categoría
     * (la mayoría no los tiene en el HTML server-side de tiendanegocio.com).
     */
    private array $categoryFallbackMap = [
        // Auriculares
        3481828 => 'Auriculares', 3481815 => 'Auriculares', 3481808 => 'Auriculares',
        3481799 => 'Auriculares', 3481781 => 'Auriculares',
        // Baterías / Power Banks
        3481765 => 'Accesorios', 3481760 => 'Accesorios', 3481758 => 'Accesorios',
        // Botellas
        3439654 => 'Botellas y Vasos',
        // Soportes TV
        3439647 => 'Accesorios', 3439629 => 'Accesorios', 3434781 => 'Accesorios',
        // Luces Led
        3434766 => 'Luces Led', 2680953 => 'Luces Led',
        // Accesorios varios
        3434744 => 'Accesorios', 3434716 => 'Accesorios', 3434590 => 'Accesorios',
        2680936 => 'Accesorios', 2676418 => 'Accesorios', 2676394 => 'Accesorios',
        // Parlantes
        2678950 => 'Parlantes', 2678938 => 'Parlantes', 2678926 => 'Parlantes', 2678919 => 'Parlantes',
    ];

    private function fetchCategory(string $slug, int $productId): ?Category
    {
        // 1. Intentar extraer categoría desde el HTML del producto (SSR de tiendanegocio.com)
        try {
            $html = (string) $this->client->get("https://asistel.tiendanegocio.com/producto/{$slug}")->getBody();
            $html = str_replace('&q;', '"', $html);

            if (preg_match('/"categories":\s*\[(\{[^\[]+?\}(?:,\{[^\[]+?\})*)\]/', $html, $matches)) {
                $categories = json_decode('[' . $matches[1] . ']', true) ?? [];
                $parent = collect($categories)->firstWhere('category_father_id', null) ?? $categories[0] ?? null;
                if ($parent && !empty($parent['category_title'])) {
                    return Category::firstOrCreate(
                        ['slug' => Str::slug($parent['category_title'])],
                        ['name' => $parent['category_title'], 'active' => true]
                    );
                }
            }
        } catch (\Throwable) {
            // continuar con fallback
        }

        // 2. Fallback: usar mapa estático por ID
        $categoryName = $this->categoryFallbackMap[$productId] ?? null;
        if (!$categoryName) return null;

        return Category::firstOrCreate(
            ['slug' => Str::slug($categoryName)],
            ['name' => $categoryName, 'active' => true]
        );
    }

    private function downloadImage(string $url, string $slug): ?string
    {
        try {
            // Usar imagen en tamaño completo (sin ?class=sm)
            $imageUrl = preg_replace('/\?class=\w+$/', '', $url);
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "products/{$slug}.{$extension}";

            if (Storage::disk('public')->exists($filename)) {
                return $filename;
            }

            $response = $this->client->get($imageUrl);
            Storage::disk('public')->put($filename, (string) $response->getBody());

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}
