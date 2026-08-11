<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected string $originalStatus = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Eliminar')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('¿Eliminar esta venta?')
                ->modalDescription(
                    fn () => $this->record->status === 'completed'
                        ? 'Se revertirá el stock de todos los productos de esta venta. Esta acción no se puede deshacer.'
                        : 'Esta acción no se puede deshacer.'
                )
                ->action(function () {
                    DB::transaction(function () {
                        if ($this->record->status === 'completed') {
                            foreach ($this->record->items as $item) {
                                $product = \App\Models\Product::lockForUpdate()->find($item->product_id);

                                if ($product) {
                                    $stockBefore = $product->stock;
                                    $product->increment('stock', $item->quantity);

                                    StockMovement::create([
                                        'product_id'     => $product->id,
                                        'user_id'        => Auth::id(),
                                        'type'           => 'in',
                                        'quantity'       => $item->quantity,
                                        'stock_before'   => $stockBefore,
                                        'stock_after'    => $stockBefore + $item->quantity,
                                        'notes'          => "Reversión por eliminación de venta {$this->record->sale_number}",
                                        'reference_type' => Sale::class,
                                        'reference_id'   => $this->record->id,
                                    ]);
                                }
                            }
                        }

                        $this->record->delete();
                    });

                    Notification::make()
                        ->title('Venta eliminada')
                        ->body($this->record->status === 'completed' ? 'El stock fue revertido automáticamente.' : null)
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Capturamos el estado original antes de que el modelo se actualice.
        $this->originalStatus = $this->record->status;

        // Ventas completadas: solo se pueden modificar notas y estado.
        if ($this->record->status === 'completed') {
            $data['payment_method']   = $this->record->payment_method;
            $data['user_id']          = $this->record->user_id;
            $data['cash_register_id'] = $this->record->cash_register_id;
            $data['subtotal']         = $this->record->subtotal;
            $data['discount']         = $this->record->discount;
            $data['total']            = $this->record->total;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->originalStatus !== 'completed' || $this->record->status !== 'cancelled') {
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->record->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if (! $product) {
                        continue;
                    }

                    $stockBefore = $product->stock;
                    $product->increment('stock', $item->quantity);

                    StockMovement::create([
                        'product_id'     => $product->id,
                        'user_id'        => Auth::id(),
                        'type'           => 'in',
                        'quantity'       => $item->quantity,
                        'stock_before'   => $stockBefore,
                        'stock_after'    => $stockBefore + $item->quantity,
                        'notes'          => "Reversión por cancelación de venta {$this->record->sale_number}",
                        'reference_type' => Sale::class,
                        'reference_id'   => $this->record->id,
                    ]);
                }
            });

            Notification::make()
                ->title('Stock revertido')
                ->body('El stock de todos los productos fue reintegrado por la cancelación.')
                ->success()
                ->send();
        } catch (\Throwable) {
            Notification::make()
                ->title('Cancelación guardada, pero el stock no pudo revertirse')
                ->body('Revisá el stock de los productos de esta venta manualmente.')
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
