<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessImportFile;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductImport;
use App\Models\Supplier;
use App\Support\ProductBarcode;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

class CargarProductos extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?string $navigationLabel = 'Cargar Productos';

    protected static ?string $title = 'Cargar Productos';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.cargar-productos';

    public function getSubheading(): ?string
    {
        return match ($this->tab) {
            'rapida'  => 'Escaneá, completá y guardá productos en segundos',
            'archivo' => 'Subí una lista de precios en PDF o Excel y revisá los productos antes de guardarlos',
            default   => '',
        };
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // ── Tab ────────────────────────────────────────────────────────────────
    public string $tab = 'rapida'; // rapida | archivo

    // ── Carga Rápida ───────────────────────────────────────────────────────
    public string $barcodeInput = '';
    public string $productName  = '';
    public string $salePrice    = '';
    public string $stockInput   = '';
    public string $expirationDateInput = '';
    public bool   $showForm     = false;
    public bool   $manualMode   = false;
    public ?string $lookupSource = null;
    public int    $sessionCount  = 0;
    public array  $recentProducts = [];

    // ── Importar desde archivo ─────────────────────────────────────────────
    public $importFile = null;
    public string $state = 'idle'; // idle | queued | error
    public string $errorMessage = '';
    public array  $supplierOptions = [];
    public ?int   $importSupplierId = null;
    public string $importedFileName = '';
    public string $importedFileSize = '';
    public bool   $supplierAutoDetected = false;
    public array  $pendingImports = [];
    public ?array $duplicateImport = null;
    public string $pendingFileHash = '';

    private const MAX_FILE_MB = 25;

    // Badge en el menú lateral con la cantidad de importaciones listas para validar
    public static function getNavigationBadge(): ?string
    {
        $count = ProductImport::where('user_id', auth()->id())
            ->where('status', 'done')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public function mount(): void
    {
        $this->supplierOptions = Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
        $this->loadPendingImports();
    }

    public function loadPendingImports(): void
    {
        $this->pendingImports = ProductImport::where('user_id', auth()->id())
            ->where('status', 'done')
            ->orderByDesc('processed_at')
            ->get(['id', 'filename', 'product_count', 'processed_at'])
            ->toArray();
    }

    public function discardImport(int $importId): void
    {
        $import = ProductImport::query()
            ->where('id', $importId)
            ->where('user_id', auth()->id())
            ->where('status', 'done')
            ->first();

        if (! $import) {
            Notification::make()
                ->title('Importación no encontrada')
                ->warning()
                ->send();

            $this->loadPendingImports();

            return;
        }

        $import->cancel();
        $this->loadPendingImports();

        Notification::make()
            ->title('Importación eliminada')
            ->body('No se guardó ningún producto del archivo.')
            ->success()
            ->send();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Carga Rápida
    // ══════════════════════════════════════════════════════════════════════

    public function scanBarcode(): void
    {
        $code = ProductBarcode::normalize($this->barcodeInput);

        if ($code === null) {
            return;
        }

        if ($error = ProductBarcode::errorMessage($code)) {
            Notification::make()
                ->title('Código inválido')
                ->body($error)
                ->danger()
                ->send();

            $this->barcodeInput = '';
            $this->dispatch('focus-barcode');

            return;
        }

        $this->barcodeInput = $code;

        $existing = Product::whereRaw('UPPER(barcode) = ?', [$code])
            ->orWhereRaw('UPPER(sku) = ?', [$code])
            ->first();

        if ($existing) {
            Notification::make()
                ->title("'{$existing->name}' ya está cargado")
                ->body("Código: {$code}")
                ->warning()
                ->send();

            $this->barcodeInput = '';
            $this->dispatch('focus-barcode');
            return;
        }

        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->expirationDateInput = '';
        $this->lookupSource = null;
        $this->manualMode   = false;

        $apiName = $this->lookupFromApi($code);

        if ($apiName) {
            $this->productName  = $apiName;
            $this->lookupSource = 'api';
        }

        $this->showForm = true;
        $this->dispatch('focus-name');
    }

    public function startManualEntry(): void
    {
        $this->barcodeInput = '';
        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->expirationDateInput = '';
        $this->lookupSource = null;
        $this->manualMode   = true;
        $this->showForm     = true;
        $this->dispatch('focus-name');
    }

    public function saveProduct(): void
    {
        $name  = trim($this->productName);
        $price = (float) str_replace(',', '.', $this->salePrice);

        if (empty($name)) {
            Notification::make()->title('Escribí el nombre del producto')->warning()->send();
            $this->dispatch('focus-name');
            return;
        }

        if ($price <= 0) {
            Notification::make()->title('El precio debe ser mayor a $0')->warning()->send();
            $this->dispatch('focus-price');
            return;
        }

        $barcode = ProductBarcode::normalize($this->barcodeInput);

        if ($error = ProductBarcode::errorMessage($barcode)) {
            Notification::make()
                ->title('Código inválido')
                ->body($error)
                ->danger()
                ->send();
            $this->dispatch('focus-barcode');

            return;
        }

        $stock = max(0, (float) str_replace(',', '.', $this->stockInput));

        $product = Product::create([
            'barcode'    => $barcode,
            'name'       => $name,
            'sale_price' => $price,
            'cost_price' => 0,
            'stock'      => $stock,
            'min_stock'  => 0,
            'unit'       => 'unidad',
            'active'     => true,
        ]);

        if ($stock > 0 && filled($this->expirationDateInput)) {
            ProductBatch::create([
                'product_id'         => $product->id,
                'quantity'           => $stock,
                'remaining_quantity' => $stock,
                'received_at'        => now(),
                'expires_at'         => $this->expirationDateInput,
                'status'             => 'active',
            ]);
        }

        $this->sessionCount++;
        array_unshift($this->recentProducts, [
            'id'    => $product->id,
            'name'  => $product->name,
            'price' => (float) $product->sale_price,
            'stock' => (float) $product->stock,
        ]);
        $this->recentProducts = array_slice($this->recentProducts, 0, 10);

        $this->resetRapidaForm();

        Notification::make()
            ->title("'{$product->name}' guardado")
            ->success()
            ->duration(1800)
            ->send();
    }

    public function removeRecent(int $id): void
    {
        $index = collect($this->recentProducts)->search(fn ($p) => ($p['id'] ?? null) === $id);

        if ($index === false) {
            return;
        }

        $name = $this->recentProducts[$index]['name'] ?? 'Producto';

        Product::where('id', $id)->delete();

        unset($this->recentProducts[$index]);
        $this->recentProducts = array_values($this->recentProducts);
        $this->sessionCount   = max(0, $this->sessionCount - 1);

        Notification::make()
            ->title("'{$name}' eliminado")
            ->body('Se deshizo la carga.')
            ->success()
            ->duration(1800)
            ->send();

        $this->dispatch('focus-barcode');
    }

    public function cancelForm(): void
    {
        $this->resetRapidaForm();
    }

    private function resetRapidaForm(): void
    {
        $this->barcodeInput = '';
        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->expirationDateInput = '';
        $this->showForm     = false;
        $this->lookupSource = null;
        $this->manualMode   = false;
        $this->dispatch('focus-barcode');
    }

    private function lookupFromApi(string $barcode): ?string
    {
        try {
            $response = Http::timeout(3)->get('https://api.upcitemdb.com/prod/trial/lookup', [
                'upc' => $barcode,
            ]);

            if ($response->successful()) {
                $title = $response->json('items.0.title');
                return $title ? mb_convert_case(mb_strtolower($title), MB_CASE_TITLE) : null;
            }
        } catch (\Throwable) {
            // API no disponible
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Importar desde archivo
    // ══════════════════════════════════════════════════════════════════════

    public function createSupplier(string $name): void
    {
        $name = trim($name);

        if (empty($name)) {
            return;
        }

        // Búsqueda case-insensitive: evita duplicados por mayúsculas/minúsculas.
        $existing = Supplier::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();

        if ($existing) {
            $this->importSupplierId = $existing->id;
            $this->dispatch('supplier-created', id: $existing->id, name: $existing->name);
            Notification::make()
                ->title("Se seleccionó '{$existing->name}' (ya existía)")
                ->info()
                ->send();
            return;
        }

        $supplier = Supplier::create(['name' => $name, 'active' => true]);

        $this->supplierOptions[$supplier->id] = $supplier->name;
        $this->importSupplierId = $supplier->id;

        $this->dispatch('supplier-created', id: $supplier->id, name: $supplier->name);
    }

    public function updatedImportFile(): void
    {
        $this->supplierAutoDetected = false;
        $this->duplicateImport = null;
        $this->pendingFileHash = '';

        if (! $this->importFile) {
            $this->importedFileName = '';
            $this->importedFileSize = '';
            return;
        }

        // Capture name and size while the temp file is definitely accessible.
        $this->importedFileName = $this->importFile->getClientOriginalName();
        try {
            $this->importedFileSize = number_format($this->importFile->getSize() / 1024, 0, ',', '.') . ' KB';
        } catch (\Throwable) {
            $this->importedFileSize = '';
        }

        // Compute hash and check for previously processed identical file.
        try {
            $hash = hash_file('sha256', $this->importFile->getRealPath());
            $this->pendingFileHash = $hash;

            $existing = ProductImport::where('file_hash', $hash)
                ->where('user_id', auth()->id())
                ->whereNotIn('status', ['error'])
                ->latest()
                ->first();

            if ($existing) {
                $this->duplicateImport = [
                    'filename'      => $existing->filename,
                    'product_count' => $existing->product_count,
                    'processed_at'  => $existing->processed_at?->format('d/m/Y \a\l\a\s H:i'),
                    'status'        => $existing->status,
                ];
            }
        } catch (\Throwable) {
            // Si falla la lectura del hash, continuamos sin validación.
        }

        $matched = $this->guessSupplierFromFilename($this->importedFileName);

        if ($matched) {
            $this->applyDetectedSupplier($matched);
        }
    }

    public function cancelDuplicate(): void
    {
        $this->clearImportFile();
        $this->duplicateImport = null;
        $this->pendingFileHash = '';
    }

    public function forceProcess(): void
    {
        $this->duplicateImport = null;
        $this->processFile();
    }

    private function applyDetectedSupplier(array $matched): void
    {
        $this->importSupplierId = $matched['id'];
        $this->supplierAutoDetected = true;
        $this->dispatch('supplier-selected', id: $matched['id'], name: $matched['name']);
    }

    /** @return array{id: int, name: string}|null */
    private function guessSupplierFromFilename(string $filename): ?array
    {
        $normalized = $this->normalizeForSupplierMatch(pathinfo($filename, PATHINFO_FILENAME));

        if ($normalized === '') {
            return null;
        }

        $best = null;
        $bestLen = 0;

        foreach ($this->supplierOptions as $id => $name) {
            $supplierNorm = $this->normalizeForSupplierMatch($name);

            if (mb_strlen($supplierNorm) < 3) {
                continue;
            }

            if (str_contains($normalized, $supplierNorm) && mb_strlen($supplierNorm) > $bestLen) {
                $best = ['id' => (int) $id, 'name' => $name];
                $bestLen = mb_strlen($supplierNorm);
            }
        }

        return $best;
    }

    private function normalizeForSupplierMatch(string $text): string
    {
        $text = Str::ascii(mb_strtolower($text));
        $text = preg_replace('/[_\-\.]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    public function processFile(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:pdf,xlsx,xls'],
        ]);

        $originalName = $this->importFile->getClientOriginalName();
        $extension    = strtolower($this->importFile->getClientOriginalExtension());

        Log::channel('imports')->info('Import upload started', [
            'filename'    => $originalName,
            'extension'   => $extension,
            'user_id'     => auth()->id(),
            'supplier_id' => $this->importSupplierId,
            'queue'       => config('queue.default'),
            'memory_mb'   => round(memory_get_usage(true) / 1024 / 1024, 1),
        ]);

        if (! $this->importSupplierId) {
            $matched = $this->guessSupplierFromFilename($originalName);
            if ($matched) {
                $this->applyDetectedSupplier($matched);
            }
        }

        try {
            $hash = $this->pendingFileHash ?: hash_file('sha256', $this->importFile->getRealPath());

            $filePath = $this->importFile->storeAs('imports', uniqid('import_') . '.' . $extension, 'local');
            $this->importFile = null;

            $fullPath = Storage::disk('local')->path($filePath);
            $fileMb   = filesize($fullPath) / 1024 / 1024;

            if ($fileMb > self::MAX_FILE_MB) {
                Storage::disk('local')->delete($filePath);
                throw new \RuntimeException("El archivo pesa " . round($fileMb, 1) . " MB. El límite es " . self::MAX_FILE_MB . " MB.");
            }

            $import = ProductImport::create([
                'user_id'     => auth()->id(),
                'supplier_id' => $this->importSupplierId,
                'filename'    => $originalName,
                'file_path'   => $filePath,
                'file_hash'   => $hash,
                'status'      => 'pending',
            ]);

            ProcessImportFile::dispatch($import->id);

            Log::channel('imports')->info('Import queued', [
                'import_id' => $import->id,
                'filename'  => $originalName,
                'file_path' => $filePath,
                'file_mb'   => round($fileMb, 2),
                'file_hash' => $hash,
                'queue'     => config('queue.default'),
            ]);

            $this->state = 'queued';

        } catch (\Throwable $e) {
            Log::channel('imports')->error('Import upload failed', [
                'filename'  => $originalName,
                'user_id'   => auth()->id(),
                'error'     => $e->getMessage(),
                'exception' => $e::class,
                'file'      => basename($e->getFile()) . ':' . $e->getLine(),
            ]);

            $this->state        = 'error';
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startOver(): void
    {
        $this->reset(['importFile', 'errorMessage', 'importedFileName', 'importedFileSize', 'supplierAutoDetected', 'duplicateImport', 'pendingFileHash']);
        $this->state = 'idle';
    }

    public function clearImportFile(): void
    {
        $this->importFile = null;
        $this->importedFileName = '';
        $this->importedFileSize = '';
        $this->supplierAutoDetected = false;
        $this->duplicateImport = null;
        $this->pendingFileHash = '';
        $this->resetValidation('importFile');
    }
}
