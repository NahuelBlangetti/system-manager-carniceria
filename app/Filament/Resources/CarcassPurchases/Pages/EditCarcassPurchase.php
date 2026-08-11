<?php

namespace App\Filament\Resources\CarcassPurchases\Pages;

use App\Filament\Resources\CarcassPurchases\CarcassPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCarcassPurchase extends EditRecord
{
    protected static string $resource = CarcassPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }

    protected function beforeSave(): void
    {
        // Defensa server-side: un despiece confirmado no se puede editar,
        // incluso si el formulario deshabilitado se llegara a saltear.
        if ($this->record->status === 'confirmed') {
            Notification::make()
                ->title('Este despiece ya fue confirmado y no se puede editar')
                ->warning()
                ->send();

            $this->halt();
        }
    }
}
