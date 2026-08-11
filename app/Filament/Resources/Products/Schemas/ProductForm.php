<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\ProductBarcode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Códigos')
                    ->description('El código de barras es el interno de la carnicería. El SKU es el código del proveedor.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('barcode')
                            ->label('Código de barras')
                            ->placeholder('Apuntá el escáner al producto y escaneá...')
                            ->autofocus()
                            ->maxLength(ProductBarcode::MAX_LENGTH)
                            ->dehydrateStateUsing(fn (?string $state): ?string => ProductBarcode::normalize($state))
                            ->rule(fn (?\Illuminate\Database\Eloquent\Model $record): ProductBarcode => new ProductBarcode($record?->getKey())),
                        TextInput::make('sku')
                            ->label('SKU del proveedor')
                            ->placeholder('Código del proveedor')
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
                    ]),

                Section::make('Información general')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                            ]),
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin proveedor'),
                        Select::make('unit')
                            ->label('Unidad de medida')
                            ->options([
                                'unidad' => 'Unidad',
                                'kg'     => 'Kilogramo',
                                'g'      => 'Gramo',
                                'litro'  => 'Litro',
                                'caja'   => 'Caja',
                                'par'    => 'Par',
                            ])
                            ->default('unidad')
                            ->required(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('Imagen')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->columnSpanFull(),
                    ]),

                Section::make('Precios y margen')
                    ->description('Al cambiar el costo o el margen, el precio de venta se calcula automáticamente.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('Precio de costo (proveedor)')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->live(debounce: 600)
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                $cost   = (float) $state;
                                $margin = (float) ($get('margin_percentage') ?? 0);
                                if ($cost > 0 && $margin > 0) {
                                    $set('sale_price', round($cost * (1 + $margin / 100), 2));
                                }
                            }),
                        TextInput::make('margin_percentage')
                            ->label('Margen (%)')
                            ->required()
                            ->numeric()
                            ->default(30)
                            ->suffix('%')
                            ->live(debounce: 600)
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                $cost   = (float) ($get('cost_price') ?? 0);
                                $margin = (float) $state;
                                if ($cost > 0) {
                                    $set('sale_price', round($cost * (1 + $margin / 100), 2));
                                }
                            }),
                        TextInput::make('sale_price')
                            ->label('Precio de venta')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->columnSpanFull()
                            ->live(debounce: 600)
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                $cost = (float) ($get('cost_price') ?? 0);
                                $sale = (float) $state;
                                if ($cost > 0 && $sale > 0) {
                                    $set('margin_percentage', round(($sale / $cost - 1) * 100, 1));
                                }
                            }),
                    ]),

                Section::make('Stock')
                    ->columns(1)
                    ->schema([
                        TextInput::make('stock')
                            ->label('Stock actual')
                            ->required()
                            ->numeric()
                            ->step(0.001)
                            ->default(0)
                            ->minValue(0),
                        TextInput::make('min_stock')
                            ->label('Stock mínimo (punto de pedido)')
                            ->required()
                            ->numeric()
                            ->step(0.001)
                            ->default(0)
                            ->minValue(0),
                        DatePicker::make('batch_expiration_date')
                            ->label('Fecha de vencimiento (opcional)')
                            ->helperText('Si la cargás, se crea un lote para alertar antes de que venza. No se guarda en el producto.'),
                    ]),
            ]);
    }
}
