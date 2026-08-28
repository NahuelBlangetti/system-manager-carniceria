<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\ProductImport;
use App\Models\Supplier;
use App\Support\ProductBarcode;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValidarImport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Importaciones';

    protected static ?string $title = 'Validar importación';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.validar-import';

    public const ALLOWED_UNITS = ['unidad', 'kg', 'g', 'litro', 'caja', 'par'];

    private const UNIT_SYNONYMS = [
        'uni' => 'unidad', 'un' => 'unidad', 'und' => 'unidad', 'unid' => 'unidad',
        'u' => 'unidad', 'pza' => 'unidad', 'pieza' => 'unidad',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
        'g' => 'g', 'gr' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'lt' => 'litro', 'lts' => 'litro', 'l' => 'litro', 'litros' => 'litro',
        'cja' => 'caja', 'cj' => 'caja', 'cajas' => 'caja', 'box' => 'caja',
        'pares' => 'par',
    ];

    // ── Estado ────────────────────────────────────────────────────────────
    public ?int    $importId          = null;
    public string  $importedFileName  = '';
    public array   $products          = [];
    public array   $categoryOptions   = [];
    public array   $supplierOptions   = [];
    public ?int    $importSupplierId  = null;
    public bool    $supplierAutoDetected = false;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $id = (int) request()->query('id');

        if (! $id) {
            $this->redirectRoute('filament.admin.pages.cargar-productos');
            return;
        }

        $import = ProductImport::where('id', $id)
            ->where('user_id', auth()->user()?->getAuthIdentifier())
            ->where('status', 'done')
            ->first();

        if (! $import) {
            // La notificación que linkea acá puede haber quedado vieja (importación
            // ya validada o eliminada): la limpiamos para que no siga apareciendo.
            $this->dismissImportNotification($id);

            Notification::make()
                ->title('Esta importación ya fue procesada')
                ->info()
                ->send();

            $this->redirectRoute('filament.admin.pages.cargar-productos');
            return;
        }

        $this->importId         = $import->id;
        $this->importedFileName = $import->filename;
        $this->importSupplierId = $import->supplier_id;
        $this->products         = $import->products ?? [];

        $this->categoryOptions = Category::query()->orderBy('name')->pluck('name', 'id')->all();
        $this->supplierOptions = Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    // ── Proveedor ─────────────────────────────────────────────────────────

    public function createSupplier(string $name): void
    {
        $name = trim($name);
        if (empty($name)) {
            return;
        }

        $existing = Supplier::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();
        if ($existing) {
            $this->importSupplierId = $existing->id;
            $this->dispatch('supplier-created', id: $existing->id, name: $existing->name);
            Notification::make()->title("Se seleccionó '{$existing->name}' (ya existía)")->info()->send();
            return;
        }

        $supplier = Supplier::create(['name' => $name, 'active' => true]);
        $this->supplierOptions[$supplier->id] = $supplier->name;
        $this->importSupplierId = $supplier->id;
        $this->dispatch('supplier-created', id: $supplier->id, name: $supplier->name);
    }

    // ── Guardar productos ─────────────────────────────────────────────────

    public function createProducts(): void
    {
        set_time_limit(0);

        $rows = collect($this->products)->filter(fn (array $p) => $p['selected'] ?? false);

        if ($rows->isEmpty()) {
            Notification::make()->title('No seleccionaste ningún producto')->warning()->send();
            return;
        }

        $supplierId = $this->importSupplierId ?: null;
        $now        = now();

        $toInsert = [];
        $toUpdate = [];
        $invalidBarcodes = 0;

        foreach ($rows as $row) {
            if (trim($row['name'] ?? '') === '') {
                continue;
            }

            $cost = (float) $row['cost_price'];
            $sale = (float) $row['sale_price'];
            $barcode = ProductBarcode::normalize($row['barcode'] ?? null);
            $ignoreId = (($row['action'] ?? 'create') === 'update' && ! empty($row['existing_product_id']))
                ? (int) $row['existing_product_id']
                : null;

            if ($barcode !== null && ProductBarcode::errorMessage($barcode, $ignoreId) !== null) {
                $barcode = null;
                $invalidBarcodes++;
            }

            $data = [
                'category_id'       => $row['category_id'] ?: null,
                'supplier_id'       => $supplierId,
                'name'              => $row['name'],
                'sku'               => filled($row['sku'] ?? null) ? trim((string) $row['sku']) : null,
                'barcode'           => $barcode,
                'unit'              => $this->normalizeUnit($row['unit'] ?? null),
                'cost_price'        => $cost,
                'sale_price'        => $sale,
                'margin_percentage' => $cost > 0 ? round(($sale / $cost - 1) * 100, 2) : 0,
                'stock'             => round((float) $row['stock'], 3),
                'min_stock'         => round((float) ($row['min_stock'] ?? 0), 3),
                'active'            => true,
            ];

            if (($row['action'] ?? 'create') === 'update' && ! empty($row['existing_product_id'])) {
                $toUpdate[] = ['id' => $row['existing_product_id'], 'data' => $data];
            } else {
                $toInsert[] = array_merge($data, ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        $created = count($toInsert);
        $updated = count($toUpdate);

        try {
            DB::transaction(function () use ($toInsert, $toUpdate): void {
                // Bulk insert — un solo query para todos los productos nuevos
                foreach (array_chunk($toInsert, 200) as $chunk) {
                    DB::table('products')->insert($chunk);
                }

                // Updates individuales (generalmente pocos)
                foreach ($toUpdate as $item) {
                    DB::table('products')
                        ->where('id', $item['id'])
                        ->update(array_merge($item['data'], ['updated_at' => now()]));
                }
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al guardar los productos')
                ->body(Str::limit($e->getMessage(), 300))
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        // Marcar importación como validada
        if ($this->importId) {
            ProductImport::where('id', $this->importId)->update(['status' => 'validated']);
            $this->dismissImportNotification($this->importId);
        }

        $parts = [];
        if ($created > 0) $parts[] = "{$created} " . ($created === 1 ? 'creado' : 'creados');
        if ($updated > 0) $parts[] = "{$updated} " . ($updated === 1 ? 'actualizado' : 'actualizados');

        Notification::make()
            ->title('Productos: ' . implode(' · ', $parts))
            ->body($invalidBarcodes > 0
                ? "Se omitieron {$invalidBarcodes} código(s) inválido(s). Podés asignarlos después escaneando el producto."
                : null)
            ->success()
            ->send();

        $this->redirectRoute('filament.admin.pages.cargar-productos');
    }

    public function cancelImport(): void
    {
        if (! $this->importId) {
            return;
        }

        $import = ProductImport::query()
            ->where('id', $this->importId)
            ->where('user_id', auth()->user()?->getAuthIdentifier())
            ->where('status', 'done')
            ->first();

        if (! $import) {
            return;
        }

        $import->cancel();

        Notification::make()
            ->title('Importación cancelada')
            ->body('No se guardó ningún producto.')
            ->success()
            ->send();

        $this->redirectRoute('filament.admin.pages.cargar-productos');
    }

    // Elimina la notificación persistente "Revisar y guardar →" de esta importación
    // para que deje de aparecer una vez que ya fue validada (o ya no existe).
    private function dismissImportNotification(int $importId): void
    {
        $import = ProductImport::find($importId);

        if ($import) {
            $import->dismissReviewNotifications();

            return;
        }

        $url = self::getUrl(['id' => $importId]);

        auth()->user()?->notifications()
            ->whereJsonContains('data->actions', ['url' => $url])
            ->delete();
    }

    private function normalizeUnit(?string $raw): string
    {
        if (! $raw) {
            return 'unidad';
        }

        $normalized = mb_strtolower(trim($raw));

        if (in_array($normalized, self::ALLOWED_UNITS, true)) {
            return $normalized;
        }

        return self::UNIT_SYNONYMS[$normalized] ?? 'unidad';
    }
}
