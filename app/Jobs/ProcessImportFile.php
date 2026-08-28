<?php

namespace App\Jobs;

use App\Filament\Pages\ValidarImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\DiscordNotifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

class ProcessImportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int  $timeout      = 600; // 10 minutos máximo
    public int  $tries        = 1;   // sin reintentos — las llamadas a OpenAI son costosas
    public bool $failOnTimeout = true;

    private const OPENAI_INPUT_COST_PER_MILLION_TOKENS = 0.40;
    private const CHUNK_SIZE = 4000;
    private const MAX_CHUNKS = 12;

    private const ALLOWED_UNITS = ['unidad', 'kg', 'g', 'litro', 'caja', 'par'];

    private const UNIT_SYNONYMS = [
        'uni' => 'unidad', 'un' => 'unidad', 'und' => 'unidad', 'unid' => 'unidad',
        'u' => 'unidad', 'pza' => 'unidad', 'pieza' => 'unidad',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
        'g' => 'g', 'gr' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'lt' => 'litro', 'lts' => 'litro', 'l' => 'litro', 'litros' => 'litro',
        'cja' => 'caja', 'cj' => 'caja', 'cajas' => 'caja', 'box' => 'caja',
        'pares' => 'par',
    ];

    public function __construct(private int $importId) {}

    public function handle(): void
    {
        $startedAt = microtime(true);
        $import    = ProductImport::findOrFail($this->importId);

        $this->logInfo('Job started', [
            'status'      => $import->status,
            'filename'    => $import->filename,
            'file_path'   => $import->file_path,
            'user_id'     => $import->user_id,
            'supplier_id' => $import->supplier_id,
            'diagnostics' => $this->serverDiagnostics($import),
        ]);

        $import->update(['status' => 'processing']);

        try {
            set_time_limit(0);

            $fullPath  = Storage::disk('local')->path($import->file_path);
            $extension = strtolower(pathinfo($import->filename, PATHINFO_EXTENSION));
            $fileExists = is_file($fullPath);
            $fileSize   = $fileExists ? filesize($fullPath) : null;

            $this->logInfo('Preparing file extraction', [
                'full_path'  => $fullPath,
                'extension'  => $extension,
                'exists'     => $fileExists,
                'size_bytes' => $fileSize,
                'readable'   => $fileExists ? is_readable($fullPath) : false,
            ]);

            if (! $fileExists) {
                throw new \RuntimeException("El archivo no existe en el servidor: {$import->file_path}");
            }

            $extractStarted = microtime(true);
            $text = $extension === 'pdf'
                ? $this->extractTextFromPdf($fullPath)
                : $this->extractTextFromSpreadsheet($fullPath);

            $this->logInfo('Text extracted', [
                'extension'     => $extension,
                'chars'         => mb_strlen($text),
                'lines'         => substr_count($text, "\n") + 1,
                'duration_ms'   => (int) ((microtime(true) - $extractStarted) * 1000),
                'memory_peak_mb'=> round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                'preview'       => Str::limit(preg_replace('/\s+/', ' ', $text) ?? '', 180),
            ]);

            if (trim($text) === '') {
                throw new \RuntimeException('No se pudo extraer texto del archivo. ¿Es un PDF escaneado (imagen) o una planilla vacía?');
            }

            $text   = $this->filterText($text);
            $chunks = $this->chunkText($text);

            $this->logInfo('Text prepared for OpenAI', [
                'chars_after_filter' => mb_strlen($text),
                'chunks'             => count($chunks),
                'chunk_sizes'        => array_map(fn (string $chunk) => mb_strlen($chunk), $chunks),
                'openai_model'       => config('services.openai.model'),
                'openai_key_set'     => filled(config('services.openai.key')),
            ]);

            $allExtracted = [];
            foreach ($chunks as $i => $chunk) {
                $context = count($chunks) > 1
                    ? "{$import->filename} (parte " . ($i + 1) . ' de ' . count($chunks) . ')'
                    : $import->filename;

                $this->logInfo('Calling OpenAI', [
                    'chunk'       => $i + 1,
                    'chunks_total'=> count($chunks),
                    'chunk_chars' => mb_strlen($chunk),
                    'context'     => $context,
                ]);

                $chunkStarted = microtime(true);
                $chunkProducts = $this->callOpenAiApi($chunk, $context);
                $allExtracted = array_merge($allExtracted, $chunkProducts);

                $this->logInfo('OpenAI chunk completed', [
                    'chunk'         => $i + 1,
                    'products'      => count($chunkProducts),
                    'duration_ms'   => (int) ((microtime(true) - $chunkStarted) * 1000),
                    'memory_peak_mb'=> round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                ]);
            }

            $products = $this->detectDuplicates($this->mapToCategories($allExtracted));
            $count    = count($products);

            $import->update([
                'status'        => 'done',
                'products'      => $products,
                'product_count' => $count,
                'processed_at'  => now(),
            ]);

            $this->logInfo('Import completed', [
                'product_count' => $count,
                'duration_ms'   => (int) ((microtime(true) - $startedAt) * 1000),
                'memory_peak_mb'=> round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            ]);

            Notification::make()
                ->title('Importación lista ✓')
                ->body(
                    ($count > 0
                        ? "{$count} " . ($count === 1 ? 'producto extraído' : 'productos extraídos')
                        : 'No se detectaron productos')
                    . " de \"{$import->filename}\". Revisalos antes de guardar."
                )
                ->success()
                ->persistent()
                ->actions([
                    Action::make('validar')
                        ->label('Revisar y guardar →')
                        ->url(ValidarImport::getUrl(['id' => $import->id]))
                        ->button(),
                ])
                ->sendToDatabase($import->user);

        } catch (\Throwable $e) {
            $this->failImport($import, $e);
        } finally {
            if (Storage::disk('local')->exists($import->file_path)) {
                Storage::disk('local')->delete($import->file_path);
                $this->logInfo('Temporary import file deleted', [
                    'file_path' => $import->file_path,
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->logError('Job failed() callback', $exception, [
            'import_id' => $this->importId,
        ]);

        $import = ProductImport::with('user')->find($this->importId);

        if (! $import) {
            return;
        }

        if ($import->status === 'done') {
            return;
        }

        $this->failImport($import, $exception, notifyDiscord: ! ($exception instanceof MaxAttemptsExceededException));
    }

    private function failImport(ProductImport $import, \Throwable $e, bool $notifyDiscord = true): void
    {
        $import->loadMissing('user');

        $message = $e instanceof MaxAttemptsExceededException
            ? 'El procesamiento tardó demasiado o el servidor se quedó sin memoria. Probá con un archivo más chico.'
            : $e->getMessage();

        $diagnostics = $this->serverDiagnostics($import);

        $this->logError('Import failed', $e, [
            'import_id'    => $import->id,
            'filename'     => $import->filename,
            'file_path'    => $import->file_path,
            'status'       => $import->status,
            'user_id'      => $import->user_id,
            'message'      => $message,
            'diagnostics'  => $diagnostics,
        ]);

        $import->update([
            'status'        => 'error',
            'error_message' => $message,
        ]);

        if ($notifyDiscord) {
            (new DiscordNotifier())->notify(
                '❌ Error al procesar importación',
                sprintf(
                    "**Archivo:** %s\n**Usuario:** %s\n**Error:** %s\n**Clase:** %s\n**Línea:** %s:%d\n**Cola:** %s\n**Memoria peak:** %s MB\n**OpenAI key:** %s\n**pdftotext:** %s",
                    $import->filename,
                    $import->user->email ?? "ID {$import->user_id}",
                    $message,
                    $e::class,
                    basename($e->getFile()),
                    $e->getLine(),
                    $diagnostics['queue'] ?? 'n/a',
                    $diagnostics['memory_peak_mb'] ?? 'n/a',
                    ($diagnostics['openai_key_set'] ?? false) ? 'sí' : 'no',
                    ($diagnostics['pdftotext_available'] ?? false) ? 'sí' : 'no'
                ),
                0xED4245
            );
        }

        if ($import->user) {
            Notification::make()
                ->title('No se pudo procesar el archivo')
                ->body("Ocurrió un error analizando \"{$import->filename}\". Por favor intentá de nuevo más tarde. Si el problema persiste, contactá a soporte.")
                ->danger()
                ->persistent()
                ->sendToDatabase($import->user);
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::channel('imports')->info($message, array_merge([
            'import_id' => $this->importId,
        ], $context));
    }

    private function logError(string $message, \Throwable $e, array $context = []): void
    {
        Log::channel('imports')->error($message, array_merge([
            'import_id' => $this->importId,
            'exception' => $e::class,
            'error'     => $e->getMessage(),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => collect(explode("\n", $e->getTraceAsString()))->take(12)->all(),
        ], $context));
    }

    private function serverDiagnostics(ProductImport $import): array
    {
        $fullPath = $import->file_path
            ? Storage::disk('local')->path($import->file_path)
            : null;

        return [
            'app_env'              => config('app.env'),
            'queue'                => config('queue.default'),
            'php_version'          => PHP_VERSION,
            'memory_limit'         => ini_get('memory_limit'),
            'memory_usage_mb'      => round(memory_get_usage(true) / 1024 / 1024, 1),
            'memory_peak_mb'       => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            'max_execution_time'   => ini_get('max_execution_time'),
            'openai_model'         => config('services.openai.model'),
            'openai_key_set'       => filled(config('services.openai.key')),
            'pdftotext_available'  => $this->commandExists('pdftotext'),
            'file_exists'          => $fullPath ? is_file($fullPath) : false,
            'file_size_bytes'      => ($fullPath && is_file($fullPath)) ? filesize($fullPath) : null,
            'disk_free_mb'         => $fullPath ? @round(disk_free_space(dirname($fullPath)) / 1024 / 1024, 1) : null,
        ];
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v ' . escapeshellarg($command));
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    // ══════════════════════════════════════════════════════════════════════
    // Extracción de texto
    // ══════════════════════════════════════════════════════════════════════

    private function extractTextFromPdf(string $path): string
    {
        if (! $this->commandExists('pdftotext')) {
            throw new \RuntimeException('El servidor no tiene instalado pdftotext (poppler-utils). No se pueden leer PDFs.');
        }

        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->logError('pdftotext failed', new \RuntimeException(trim($process->getErrorOutput()) ?: 'unknown'), [
                'exit_code' => $process->getExitCode(),
                'stderr'    => Str::limit($process->getErrorOutput(), 500),
                'stdout'    => Str::limit($process->getOutput(), 200),
            ]);

            throw new \RuntimeException('No se pudo leer el PDF: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    private function extractTextFromSpreadsheet(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->logError('PhpSpreadsheet load failed', $e, [
                'path' => $path,
            ]);

            throw new \RuntimeException('No se pudo abrir la planilla Excel: ' . $e->getMessage(), 0, $e);
        }

        $lines = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $lines[] = "--- Hoja: {$sheet->getTitle()} ---";

            // formatData=false evita fallar con imágenes embebidas (Drawing) u otros
            // objetos no convertibles a string (p. ej. logos en celdas).
            try {
                foreach ($sheet->toArray(null, true, false, false) as $row) {
                    $row = array_map(fn ($cell) => $this->cellToPlainText($cell), $row);

                    if (implode('', $row) === '') {
                        continue;
                    }

                    $lines[] = implode(' | ', $row);
                }
            } catch (\Throwable $e) {
                $this->logError('PhpSpreadsheet toArray failed', $e, [
                    'sheet' => $sheet->getTitle(),
                ]);

                throw new \RuntimeException(
                    "No se pudo leer la hoja \"{$sheet->getTitle()}\": {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return implode("\n", $lines);
    }

    private function cellToPlainText(mixed $cell): string
    {
        if ($cell === null || is_bool($cell)) {
            return '';
        }

        if (is_scalar($cell)) {
            return trim((string) $cell);
        }

        if ($cell instanceof \Stringable) {
            return trim((string) $cell);
        }

        if ($cell instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return trim($cell->getPlainText());
        }

        // Imágenes, dibujos y demás objetos no aportan texto útil a la IA.
        return '';
    }

    // ══════════════════════════════════════════════════════════════════════
    // Llamada a OpenAI
    // ══════════════════════════════════════════════════════════════════════

    private function callOpenAiApi(string $text, string $context): array
    {
        $instructions = <<<PROMPT
        Sos un asistente que extrae productos de listas de precios de carnicería a partir de texto plano sacado de un PDF o de una planilla Excel (puede tener columnas, secciones por rubro, precios con o sin IVA, etc.).

        Devolvé ÚNICAMENTE un objeto JSON con esta estructura exacta:

        {
          "products": [
            {
              "name": "nombre del producto",
              "sku": "código o SKU del proveedor si figura en el archivo, o null si no figura",
              "barcode": "código de barras (EAN/UPC o código interno escaneable), o null si no figura. NO uses el SKU del proveedor acá",
              "unit": "unidad de venta, tiene que ser EXACTAMENTE uno de estos valores: unidad, kg, g, litro, caja, par. Elegí el más parecido a lo que figura en el texto, 'unidad' si no hay forma de saberlo",
              "cost_price": numero con el precio de costo (sin simbolos ni separadores de miles, punto como decimal),
              "sale_price": numero con el precio de venta sugerido si figura, 0 si no figura,
              "category": "rubro o categoría a la que pertenece según cómo esté organizada la lista, o null"
            }
          ]
        }

        Reglas:
        - No inventes productos que no estén en el texto.
        - Ignorá encabezados, totales, pies de página y líneas que no sean productos.
        - Los números deben ser numéricos, sin "$" ni separadores de miles.
        - Si el archivo trae un código/SKU del proveedor, guardalo en "sku". Si trae un código de barras distinto, guardalo en "barcode". Nunca copies el SKU del proveedor en "barcode".
        - NUNCA extraigas stock, cantidad, bulto, presentación ni unidades por caja. Esas columnas NO son stock del local: ignorálas por completo.
        - Si en el nombre aparece algo como "x 12", "caja x 24" o "1 lt", eso forma parte del nombre del producto, no es stock.
        PROMPT;

        $payload = [
            'model'           => config('services.openai.model'),
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user',   'content' => $text],
            ],
        ];

        $response = Http::withToken(config('services.openai.key'))
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(3, 8000, function (\Throwable $e): bool {
                // Reintentar solo en timeouts y errores de red, no en 4xx (auth, rate limit, etc.)
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $status = $response->status();
            $body   = $response->json('error.message') ?? $response->body();

            $this->logError(
                'OpenAI API request failed',
                new \RuntimeException((string) $body),
                [
                    'context'       => $context,
                    'http_status'   => $status,
                    'response_body' => Str::limit((string) $body, 500),
                    'model'         => config('services.openai.model'),
                ]
            );

            throw new \RuntimeException("Error en la API de OpenAI ({$status}): " . Str::limit($body, 300));
        }

        $promptTokens     = (int) $response->json('usage.prompt_tokens', 0);
        $completionTokens = (int) $response->json('usage.completion_tokens', 0);
        $estimatedCost    = $promptTokens / 1_000_000 * self::OPENAI_INPUT_COST_PER_MILLION_TOKENS;

        (new DiscordNotifier())->notifyOpenAiUsage($context, $promptTokens, $completionTokens, $estimatedCost);

        $content = (string) $response->json('choices.0.message.content', '');
        $data    = json_decode($content, true);

        if (! is_array($data) || ! isset($data['products']) || ! is_array($data['products'])) {
            throw new \RuntimeException('La IA no devolvió un JSON válido.');
        }

        return $data['products'];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Procesamiento de texto
    // ══════════════════════════════════════════════════════════════════════

    private function filterText(string $text): string
    {
        $lines     = explode("\n", $text);
        $frequency = [];
        $result    = [];

        foreach ($lines as $line) {
            $key = mb_strtolower(trim($line));
            if ($key !== '') {
                $frequency[$key] = ($frequency[$key] ?? 0) + 1;
            }
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (mb_strlen($trimmed) < 4) {
                continue;
            }

            if (preg_match('/^[\-=_\.·\*\#]{3,}$/', $trimmed)) {
                continue;
            }

            $key = mb_strtolower($trimmed);
            if (($frequency[$key] ?? 0) > 4) {
                continue;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    private function chunkText(string $text): array
    {
        $chunks    = [];
        $remaining = trim($text);

        while (mb_strlen($remaining) > 0 && count($chunks) < self::MAX_CHUNKS) {
            if (mb_strlen($remaining) <= self::CHUNK_SIZE) {
                $chunks[] = $remaining;
                break;
            }

            $slice       = mb_substr($remaining, 0, self::CHUNK_SIZE);
            $lastNewline = mb_strrpos($slice, "\n");

            if ($lastNewline !== false && $lastNewline > self::CHUNK_SIZE * 0.6) {
                $slice = mb_substr($remaining, 0, $lastNewline + 1);
            }

            $chunks[]  = trim($slice);
            $remaining = trim(mb_substr($remaining, mb_strlen($slice)));
        }

        return array_values(array_filter($chunks));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Mapeo y detección de duplicados
    // ══════════════════════════════════════════════════════════════════════

    private function mapToCategories(array $extracted): array
    {
        $categories = Category::query()->pluck('id', 'name');

        return collect($extracted)->map(function (array $item) use ($categories) {
            $categoryName = $item['category'] ?? null;
            $categoryId   = null;

            if ($categoryName) {
                foreach ($categories as $name => $id) {
                    if (str_contains(mb_strtolower($name), mb_strtolower($categoryName))
                        || str_contains(mb_strtolower($categoryName), mb_strtolower($name))) {
                        $categoryId = $id;
                        break;
                    }
                }
            }

            return [
                'selected'            => true,
                'action'              => 'create',
                'name'                => $item['name'] ?? '',
                'sku'                 => $item['sku'] ?? null,
                'barcode'             => $item['barcode'] ?? null,
                'unit'                => $this->normalizeUnit($item['unit'] ?? null),
                'cost_price'          => (float) ($item['cost_price'] ?? 0),
                'sale_price'          => (float) ($item['sale_price'] ?? 0),
                'stock'               => 0,
                'min_stock'           => 0,
                'category_raw'        => $categoryName,
                'category_id'         => $categoryId,
                'duplicate'           => null,
                'existing_product_id' => null,
                'existing_cost'       => null,
                'existing_sale'       => null,
                'price_direction'     => null,
            ];
        })->values()->all();
    }

    private function detectDuplicates(array $rows): array
    {
        $existingProducts = Product::query()
            ->select(['id', 'name', 'barcode', 'cost_price', 'sale_price'])
            ->get();

        $existingByKey = $existingProducts->reduce(function (array $carry, Product $product) {
            $entry = [
                'id'         => $product->id,
                'name'       => $product->name,
                'cost_price' => (float) $product->cost_price,
                'sale_price' => (float) $product->sale_price,
            ];

            if ($product->barcode) {
                $carry['barcode'][mb_strtolower(trim($product->barcode))] = $entry;
            }

            $carry['name'][mb_strtolower(trim($product->name))] = $entry;

            return $carry;
        }, ['barcode' => [], 'name' => []]);

        $seenBarcodes = [];
        $seenNames    = [];

        foreach ($rows as &$row) {
            $barcode = $row['barcode'] ? mb_strtolower(trim($row['barcode'])) : null;
            $name    = mb_strtolower(trim($row['name']));

            $existingEntry = null;
            $reason        = null;

            if ($barcode && isset($existingByKey['barcode'][$barcode])) {
                $existingEntry = $existingByKey['barcode'][$barcode];
                $reason        = "Ya existe con este código: \"{$existingEntry['name']}\"";
            } elseif (isset($existingByKey['name'][$name])) {
                $existingEntry = $existingByKey['name'][$name];
                $reason        = 'Ya existe un producto con este nombre';
            } elseif ($barcode && isset($seenBarcodes[$barcode])) {
                $reason = 'Código repetido dentro de este archivo';
            } elseif (isset($seenNames[$name])) {
                $reason = 'Nombre repetido dentro de este archivo';
            }

            if ($barcode) {
                $seenBarcodes[$barcode] = true;
            }
            $seenNames[$name] = true;

            $row['duplicate'] = $reason;

            if ($existingEntry) {
                $existingCost = $existingEntry['cost_price'];
                $existingSale = $existingEntry['sale_price'];
                $newCost      = (float) $row['cost_price'];

                $row['existing_product_id'] = $existingEntry['id'];
                $row['existing_cost']       = $existingCost;
                $row['existing_sale']       = $existingSale;
                $row['action']              = 'update';
                $row['selected']            = true;

                if ($existingCost > 0 && abs($newCost - $existingCost) > 0.001) {
                    $row['price_direction'] = $newCost > $existingCost ? 'up' : 'down';
                } else {
                    $row['price_direction'] = 'same';
                }
            } elseif ($reason) {
                $row['selected'] = false;
            }
        }

        return $rows;
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
