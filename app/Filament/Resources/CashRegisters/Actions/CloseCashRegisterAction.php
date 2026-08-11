<?php

namespace App\Filament\Resources\CashRegisters\Actions;

use App\Models\CashRegister;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

class CloseCashRegisterAction
{
    public static function make(): Action
    {
        return Action::make('closeCashRegister')
            ->label('Cerrar caja')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->modalHeading('Cerrar caja')
            ->modalDescription(function (?CashRegister $record = null): string {
                $register = self::resolveRegister($record);

                if (! $register) {
                    return 'No hay una caja abierta.';
                }

                return "Turno de {$register->user?->name} · abierta el {$register->opened_at->format('d/m/Y H:i')}";
            })
            ->modalSubmitActionLabel('Confirmar cierre')
            ->modalWidth('md')
            ->schema(function (?CashRegister $record = null): array {
                $register = self::resolveRegister($record);

                $fields = [
                    TextInput::make('opening_amount_preview')
                        ->label('Monto apertura')
                        ->default($register ? CashRegister::formatMoney((float) $register->opening_amount) : null)
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('cash_sales_preview')
                        ->label('Ventas en efectivo')
                        ->default($register ? CashRegister::formatMoney($register->cashSalesTotal()) : null)
                        ->disabled()
                        ->dehydrated(false),
                ];

                if ($register && $register->transferSalesTotal() > 0) {
                    $fields[] = TextInput::make('transfer_sales_preview')
                        ->label('Ventas en transferencia')
                        ->default(CashRegister::formatMoney($register->transferSalesTotal()))
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('No forma parte del arqueo en efectivo.');
                }

                if ($register && $register->cardSalesTotal() > 0) {
                    $fields[] = TextInput::make('card_sales_preview')
                        ->label('Ventas con tarjeta')
                        ->default(CashRegister::formatMoney($register->cardSalesTotal()))
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('No forma parte del arqueo en efectivo.');
                }

                $fields[] = TextInput::make('closing_amount')
                        ->label('Efectivo contado')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->live(debounce: 300)
                        ->helperText(function (Get $get) use ($register): string {
                            if (! $register) {
                                return '';
                            }

                            $expected = $register->calculateExpectedAmount();
                            $closing  = (float) ($get('closing_amount') ?? 0);
                            $diff     = $closing - $expected;

                            $base = 'Arqueo solo de efectivo. Monto esperado: ' . CashRegister::formatMoney($expected);

                            if ($get('closing_amount') === null || $get('closing_amount') === '') {
                                return $base;
                            }

                            $diffLabel = $diff >= 0 ? 'Sobrante' : 'Faltante';

                            return $base . ' · ' . $diffLabel . ': ' . CashRegister::formatMoney(abs($diff));
                        });

                $fields[] = Textarea::make('closing_notes')
                        ->label('Notas de cierre')
                        ->rows(2)
                        ->placeholder('Opcional: observaciones al cerrar el turno.');

                return $fields;
            })
            ->visible(function (?CashRegister $record = null): bool {
                if ($record) {
                    return $record->status === 'open';
                }

                return CashRegister::where('status', 'open')->exists();
            })
            ->action(function (array $data, ?CashRegister $record = null): void {
                $register = self::resolveRegister($record);

                if (! $register || $register->status !== 'open') {
                    Notification::make()
                        ->title('No hay caja abierta')
                        ->danger()
                        ->send();

                    return;
                }

                $register->close(
                    (float) $data['closing_amount'],
                    $data['closing_notes'] ?? null,
                );

                $diff = (float) $register->difference;

                Notification::make()
                    ->title('Caja cerrada')
                    ->body(
                        'Esperado: ' . CashRegister::formatMoney((float) $register->expected_amount)
                        . ' · Diferencia: ' . CashRegister::formatMoney(abs($diff))
                        . ($diff >= 0 ? ' (sobrante)' : ' (faltante)')
                    )
                    ->success()
                    ->send();
            });
    }

    private static function resolveRegister(?CashRegister $record): ?CashRegister
    {
        if ($record) {
            return $record->fresh(['user']);
        }

        return CashRegister::query()
            ->where('status', 'open')
            ->with('user')
            ->first();
    }
}
