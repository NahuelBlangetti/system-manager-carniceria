<?php

namespace App\Filament\Resources\CarcassPurchases\Schemas;

use App\Models\CarcassPurchase;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CarcassPurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (?CarcassPurchase $record): bool => $record?->status === 'confirmed')
            ->components([
                Section::make('Datos de la compra')
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin proveedor'),
                        Select::make('animal_type')
                            ->label('Tipo de animal')
                            ->options([
                                'vacuno'  => 'Vacuno',
                                'cerdo'   => 'Cerdo',
                                'pollo'   => 'Pollo',
                                'cordero' => 'Cordero',
                                'otro'    => 'Otro',
                            ])
                            ->required(),
                        DatePicker::make('purchase_date')
                            ->label('Fecha de compra')
                            ->default(now())
                            ->required(),
                        TextInput::make('carcass_weight_kg')
                            ->label('Peso comprado')
                            ->numeric()
                            ->step(0.001)
                            ->suffix('kg')
                            ->required()
                            ->live(debounce: 500),
                        TextInput::make('total_cost')
                            ->label('Costo total')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->live(debounce: 500),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cortes obtenidos')
                    ->description('Cargá cada corte con el peso que rindió. El costo real de cada uno se calcula al confirmar el despiece.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Corte')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('weight_kg')
                                    ->label('Peso obtenido')
                                    ->numeric()
                                    ->step(0.001)
                                    ->suffix('kg')
                                    ->required()
                                    ->live(debounce: 500),
                                DatePicker::make('expiration_date')
                                    ->label('Vencimiento (opcional)'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar corte')
                            ->columnSpanFull(),

                        Placeholder::make('yield_summary')
                            ->label('Resumen')
                            ->content(function (Get $get): string {
                                $carcassWeight = (float) ($get('carcass_weight_kg') ?? 0);
                                $items         = $get('items') ?? [];
                                $obtained      = collect($items)->sum(fn ($item) => (float) ($item['weight_kg'] ?? 0));
                                $shrinkage     = max(0, $carcassWeight - $obtained);
                                $yieldPct      = $carcassWeight > 0 ? round($obtained / $carcassWeight * 100, 1) : 0;

                                return sprintf(
                                    'Peso obtenido: %s kg · Merma: %s kg · Rendimiento: %s%%',
                                    number_format($obtained, 3, ',', '.'),
                                    number_format($shrinkage, 3, ',', '.'),
                                    number_format($yieldPct, 1, ',', '.')
                                );
                            }),
                    ]),
            ]);
    }
}
