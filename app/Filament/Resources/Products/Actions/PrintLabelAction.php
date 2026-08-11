<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use App\Services\Labels\ProductLabelEscPosBuilder;
use App\Support\ProductBarcode;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class PrintLabelAction
{
    public static function make(): Action
    {
        return Action::make('printLabel')
            ->label('Imprimir etiqueta')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading('Imprimir etiqueta de código de barras')
            ->modalDescription('Se envía a la impresora de tickets configurada en este navegador.')
            ->modalSubmitActionLabel('Imprimir')
            ->modalWidth('sm')
            ->disabled(fn (Product $record): bool => blank($record->barcode))
            ->tooltip(fn (Product $record): ?string => blank($record->barcode)
                ? 'Asigná un código de barras antes de imprimir'
                : null)
            ->schema([
                TextInput::make('copies')
                    ->label('Cantidad de etiquetas')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
            ])
            ->action(function (Product $record, array $data, HasTable $livewire): void {
                if ($error = ProductBarcode::errorMessage($record->barcode, $record->id)) {
                    Notification::make()
                        ->title('No se puede imprimir: código inválido')
                        ->body($error . ' Corregilo con “Asignar código” antes de imprimir.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $ticket = app(ProductLabelEscPosBuilder::class)->build($record, (int) $data['copies']);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se pudo generar la etiqueta')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $livewire->dispatch('print-escpos-ticket', content: $ticket);
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('printLabels')
            ->label('Imprimir etiquetas')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading('Imprimir etiquetas de código de barras')
            ->modalDescription(fn (Collection $records): string => self::scopeDescription($records->count()))
            ->modalSubmitActionLabel('Imprimir')
            ->modalWidth('sm')
            ->schema([
                TextInput::make('copies')
                    ->label('Copias por producto')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data, HasTable $livewire): void {
                $withBarcode = $records->filter(fn (Product $product): bool => filled($product->barcode));
                $withoutBarcodeCount = $records->count() - $withBarcode->count();
                $invalid = $withBarcode->filter(
                    fn (Product $product): bool => ProductBarcode::errorMessage($product->barcode, $product->id) !== null
                );
                $printable = $withBarcode->reject(
                    fn (Product $product): bool => ProductBarcode::errorMessage($product->barcode, $product->id) !== null
                );

                if ($printable->isEmpty()) {
                    Notification::make()
                        ->title($invalid->isNotEmpty()
                            ? 'Ningún producto tiene un código de barras válido para imprimir'
                            : 'Ningún producto seleccionado tiene código de barras')
                        ->warning()
                        ->send();

                    return;
                }

                $builder = app(ProductLabelEscPosBuilder::class);
                $copies = (int) $data['copies'];

                try {
                    $ticket = $printable
                        ->map(fn (Product $product): string => $builder->build($product, $copies))
                        ->implode('');
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se pudo generar la etiqueta')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $livewire->dispatch('print-escpos-ticket', content: $ticket);

                $warnings = [];
                if ($withoutBarcodeCount > 0) {
                    $warnings[] = "{$withoutBarcodeCount} sin código";
                }
                if ($invalid->isNotEmpty()) {
                    $warnings[] = "{$invalid->count()} con código inválido";
                }

                if ($warnings !== []) {
                    Notification::make()
                        ->title('Se omitieron productos: ' . implode(' · ', $warnings))
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function scopeDescription(int $count): string
    {
        return $count === 1
            ? 'Se imprimirá la etiqueta de 1 producto.'
            : "Se imprimirán las etiquetas de {$count} productos.";
    }
}
