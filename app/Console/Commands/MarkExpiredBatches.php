<?php

namespace App\Console\Commands;

use App\Models\ProductBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkExpiredBatches extends Command
{
    protected $signature = 'batches:mark-expired';

    protected $description = 'Marca como vencidos los lotes activos cuya fecha de vencimiento ya pasó';

    public function handle(): int
    {
        $count = ProductBatch::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::today())
            ->update(['status' => 'expired']);

        $this->info("{$count} lote(s) marcado(s) como vencido(s).");

        return self::SUCCESS;
    }
}
