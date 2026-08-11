<?php

namespace App\Filament\Resources\CarcassPurchases;

use App\Filament\Resources\CarcassPurchases\Pages\CreateCarcassPurchase;
use App\Filament\Resources\CarcassPurchases\Pages\EditCarcassPurchase;
use App\Filament\Resources\CarcassPurchases\Pages\ListCarcassPurchases;
use App\Filament\Resources\CarcassPurchases\Schemas\CarcassPurchaseForm;
use App\Filament\Resources\CarcassPurchases\Tables\CarcassPurchasesTable;
use App\Models\CarcassPurchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CarcassPurchaseResource extends Resource
{
    protected static ?string $model = CarcassPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Despieces';
    protected static ?string $modelLabel = 'despiece';
    protected static ?string $pluralModelLabel = 'despieces';
    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CarcassPurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CarcassPurchasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCarcassPurchases::route('/'),
            'create' => CreateCarcassPurchase::route('/create'),
            'edit'   => EditCarcassPurchase::route('/{record}/edit'),
        ];
    }
}
