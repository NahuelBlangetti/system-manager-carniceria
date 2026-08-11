<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductPriceBulkService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class AdjustProductPricesAction
{
    public static function make(?int $fixedSupplierId = null): Action
    {
        return Action::make('adjustPrices')
            ->label($fixedSupplierId ? 'Ajustar precios' : 'Ajuste masivo de precios')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->modalHeading('Ajuste masivo de precios')
            ->modalDescription('Actualizá los precios de todos los productos de un proveedor. Los cambios quedan registrados en el historial de precios.')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalWidth('md')
            ->schema([
                Select::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(fn ($record): bool => blank($fixedSupplierId) && blank($record))
                    ->hidden(fn ($record): bool => filled($fixedSupplierId) || filled($record))
                    ->dehydrated(fn ($record): bool => blank($fixedSupplierId) && blank($record)),
                TextInput::make('percentage')
                    ->label('Porcentaje de aumento')
                    ->numeric()
                    ->required()
                    ->default(30)
                    ->suffix('%')
                    ->minValue(0.01)
                    ->maxValue(1000)
                    ->step(0.01),
                Select::make('mode')
                    ->label('Aplicar sobre')
                    ->required()
                    ->default(ProductPriceBulkService::MODE_COST_KEEP_MARGIN)
                    ->options([
                        ProductPriceBulkService::MODE_COST_KEEP_MARGIN => 'Precio de costo (recalcular venta manteniendo margen %)',
                        ProductPriceBulkService::MODE_SALE_ONLY => 'Solo precio de venta',
                        ProductPriceBulkService::MODE_BOTH => 'Costo y venta (mismo % en ambos)',
                    ]),
            ])
            ->fillForm(function ($record) use ($fixedSupplierId): array {
                return [
                    'supplier_id' => $fixedSupplierId ?? $record?->id,
                    'percentage' => 30,
                    'mode' => ProductPriceBulkService::MODE_COST_KEEP_MARGIN,
                ];
            })
            ->action(function (array $data, $record = null) use ($fixedSupplierId): void {
                $supplierId = (int) ($fixedSupplierId ?? $record?->id ?? $data['supplier_id']);
                $percentage = (float) $data['percentage'];
                $mode = $data['mode'];

                $updated = app(ProductPriceBulkService::class)->applyPercentage(
                    Product::query()->where('supplier_id', $supplierId),
                    $percentage,
                    $mode,
                );

                if ($updated === 0) {
                    Notification::make()
                        ->title('Sin productos para actualizar')
                        ->body('El proveedor seleccionado no tiene productos asociados.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Precios actualizados')
                    ->body("Se actualizaron {$updated} producto(s) con un aumento del {$percentage}%.")
                    ->success()
                    ->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('adjustPrices')
            ->label('Ajustar precios')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->modalHeading('Ajustar precios de productos seleccionados')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalWidth('md')
            ->schema([
                TextInput::make('percentage')
                    ->label('Porcentaje de aumento')
                    ->numeric()
                    ->required()
                    ->default(30)
                    ->suffix('%')
                    ->minValue(0.01)
                    ->maxValue(1000)
                    ->step(0.01),
                Select::make('mode')
                    ->label('Aplicar sobre')
                    ->required()
                    ->default(ProductPriceBulkService::MODE_COST_KEEP_MARGIN)
                    ->options([
                        ProductPriceBulkService::MODE_COST_KEEP_MARGIN => 'Precio de costo (recalcular venta manteniendo margen %)',
                        ProductPriceBulkService::MODE_SALE_ONLY => 'Solo precio de venta',
                        ProductPriceBulkService::MODE_BOTH => 'Costo y venta (mismo % en ambos)',
                    ]),
            ])
            ->action(function (Collection $records, array $data): void {
                $updated = app(ProductPriceBulkService::class)->applyPercentage(
                    $records,
                    (float) $data['percentage'],
                    $data['mode'],
                );

                Notification::make()
                    ->title('Precios actualizados')
                    ->body("Se actualizaron {$updated} producto(s) con un aumento del {$data['percentage']}%.")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
