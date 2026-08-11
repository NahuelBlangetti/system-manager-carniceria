<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del proveedor')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre / Razón social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('cuit')
                            ->label('CUIT')
                            ->maxLength(20)
                            ->placeholder('XX-XXXXXXXX-X'),
                        TextInput::make('contact_person')
                            ->label('Contacto')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Select::make('payment_terms')
                            ->label('Condición de pago')
                            ->options([
                                'contado'      => 'Contado',
                                '15_dias'      => '15 días',
                                '30_dias'      => '30 días',
                                '60_dias'      => '60 días',
                                'consignacion' => 'Consignación',
                            ])
                            ->default('contado')
                            ->required(),
                        Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
