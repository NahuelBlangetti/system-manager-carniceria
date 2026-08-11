<?php

namespace App\Filament\Resources\CashRegisters\Pages;

use App\Filament\Resources\CashRegisters\Actions\CloseCashRegisterAction;
use App\Filament\Resources\CashRegisters\CashRegisterResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCashRegister extends EditRecord
{
    protected static string $resource = CashRegisterResource::class;

    public function getTitle(): string
    {
        if ($this->record->status === 'open') {
            return 'Caja abierta';
        }

        return 'Detalle de caja';
    }

    protected function getFormActions(): array
    {
        if ($this->record->status === 'closed') {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return [
            CloseCashRegisterAction::make()
                ->after(function (): void {
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            Action::make('delete')
                ->label('Eliminar')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('¿Eliminar esta caja?')
                ->modalDescription('Esta acción no se puede deshacer.')
                ->visible(fn (): bool => $this->record->status === 'closed')
                ->action(function () {
                    if ($this->record->sales()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar esta caja')
                            ->body('La caja tiene ventas asociadas. Reasignales una caja antes de eliminarla.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->delete();

                    Notification::make()
                        ->title('Caja eliminada')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status === 'closed') {
            Notification::make()
                ->title('Caja cerrada')
                ->body('Los registros de caja cerrada no se pueden modificar.')
                ->warning()
                ->send();

            $this->halt();
        }
    }
}
