<?php

namespace App\Filament\Resources\CashRegisters\Pages;

use App\Filament\Resources\CashRegisters\CashRegisterResource;
use App\Models\CashRegister;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCashRegister extends CreateRecord
{
    protected static string $resource = CashRegisterResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Abrir caja';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status']    = 'open';
        $data['user_id'] ??= auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Caja abierta';
    }

    protected function beforeCreate(): void
    {
        if (CashRegister::where('status', 'open')->exists()) {
            Notification::make()
                ->title('Ya hay una caja abierta')
                ->body('Cerrá la caja actual antes de abrir una nueva.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
