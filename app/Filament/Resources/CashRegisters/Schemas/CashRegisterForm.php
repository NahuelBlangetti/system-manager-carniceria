<?php

namespace App\Filament\Resources\CashRegisters\Schemas;

use App\Models\CashRegister;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CashRegisterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Apertura')
                    ->description('Indicá con cuánto efectivo inicia el turno.')
                    ->visible(fn (?CashRegister $record): bool => $record === null)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => auth()->id())
                            ->required(),

                        Toggle::make('use_previous_closing')
                            ->label('Usar el efectivo del cierre anterior')
                            ->default(fn (): bool => CashRegister::lastClosed() !== null)
                            ->live()
                            ->visible(fn (): bool => CashRegister::lastClosed() !== null)
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->helperText(function (): ?string {
                                $last = CashRegister::lastClosed();

                                if (! $last) {
                                    return null;
                                }

                                return 'Cierre del ' . $last->closed_at->format('d/m/Y H:i')
                                    . ': ' . CashRegister::formatMoney((float) $last->closing_amount)
                                    . '. Desactivá esta opción si el efectivo inicial es otro.';
                            })
                            ->afterStateUpdated(function (bool $state, Set $set): void {
                                $set('opening_amount', $state
                                    ? CashRegister::suggestedOpeningAmount()
                                    : 0);
                            }),

                        TextInput::make('opening_amount')
                            ->label('Monto apertura')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(fn (): float => CashRegister::suggestedOpeningAmount())
                            ->prefix('$')
                            ->helperText(fn (Get $get): string => $get('use_previous_closing')
                                ? 'Monto tomado del cierre anterior. Desactivá la opción de arriba para ingresar otro valor.'
                                : 'Efectivo inicial en el cajón. Podés ingresar $0 si arrancás sin efectivo.')
                            ->disabled(fn (Get $get): bool => (bool) $get('use_previous_closing')
                                && CashRegister::lastClosed() !== null)
                            ->dehydrated(),

                        DateTimePicker::make('opened_at')
                            ->label('Apertura')
                            ->required()
                            ->default(now())
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),

                Section::make('Turno en curso')
                    ->description('Al cerrar solo se arquea el efectivo físico. Transferencias y tarjetas quedan registradas en el turno.')
                    ->visible(fn (?CashRegister $record): bool => $record?->status === 'open')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->disabled(),

                        TextInput::make('opening_amount')
                            ->label('Monto apertura')
                            ->prefix('$')
                            ->disabled(),

                        DateTimePicker::make('opened_at')
                            ->label('Apertura')
                            ->disabled(),

                        Text::make(fn (?CashRegister $record): string => 'Ventas en efectivo: '
                            . CashRegister::formatMoney($record?->cashSalesTotal() ?? 0))
                            ->columnSpanFull(),

                        Text::make(fn (?CashRegister $record): string => 'Ventas en transferencia: '
                            . CashRegister::formatMoney($record?->transferSalesTotal() ?? 0))
                            ->visible(fn (?CashRegister $record): bool => ($record?->transferSalesTotal() ?? 0) > 0)
                            ->columnSpanFull(),

                        Text::make(fn (?CashRegister $record): string => 'Ventas con tarjeta: '
                            . CashRegister::formatMoney($record?->cardSalesTotal() ?? 0))
                            ->visible(fn (?CashRegister $record): bool => ($record?->cardSalesTotal() ?? 0) > 0)
                            ->columnSpanFull(),

                        Text::make(fn (?CashRegister $record): string => 'Monto esperado al cierre (solo efectivo): '
                            . CashRegister::formatMoney($record?->calculateExpectedAmount() ?? 0))
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cierre')
                    ->description('Resumen del turno cerrado.')
                    ->visible(fn (?CashRegister $record): bool => $record?->status === 'closed')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->disabled(),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'open'   => 'Abierta',
                                'closed' => 'Cerrada',
                            ])
                            ->disabled(),

                        TextInput::make('opening_amount')
                            ->label('Monto apertura')
                            ->prefix('$')
                            ->disabled(),

                        TextInput::make('closing_amount')
                            ->label('Monto cierre')
                            ->prefix('$')
                            ->disabled(),

                        TextInput::make('expected_amount')
                            ->label('Monto esperado')
                            ->prefix('$')
                            ->disabled(),

                        TextInput::make('difference')
                            ->label('Diferencia')
                            ->prefix('$')
                            ->disabled(),

                        DateTimePicker::make('opened_at')
                            ->label('Apertura')
                            ->disabled(),

                        DateTimePicker::make('closed_at')
                            ->label('Cierre')
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
