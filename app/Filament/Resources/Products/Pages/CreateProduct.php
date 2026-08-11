<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductBatch;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected ?string $batchExpirationDate = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->batchExpirationDate = $data['batch_expiration_date'] ?? null;
        unset($data['batch_expiration_date']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->batchExpirationDate || (float) $this->record->stock <= 0) {
            return;
        }

        ProductBatch::create([
            'product_id'         => $this->record->id,
            'quantity'           => $this->record->stock,
            'remaining_quantity' => $this->record->stock,
            'received_at'        => now(),
            'expires_at'         => $this->batchExpirationDate,
            'status'             => 'active',
        ]);
    }
}
