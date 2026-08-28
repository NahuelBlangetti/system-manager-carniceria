<?php

namespace App\Console\Commands;

use App\Services\ScaleService;
use Illuminate\Console\Command;

class ReadScaleWeight extends Command
{
    protected $signature = 'scale:read {connection? : Nombre de la conexión en config/scale.php}';

    protected $description = 'Lee el peso actual desde la balanza configurada';

    public function handle(ScaleService $scaleService): int
    {
        $result = $scaleService->readWeight($this->argument('connection'));

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info("Raw: {$result['raw']}");
        $this->info(sprintf('Weight: %.2f %s', $result['weight'], $result['unit']));

        return self::SUCCESS;
    }
}
