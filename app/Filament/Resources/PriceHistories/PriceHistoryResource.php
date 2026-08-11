<?php

namespace App\Filament\Resources\PriceHistories;

use App\Filament\Resources\PriceHistories\Pages\ListPriceHistories;
use App\Filament\Resources\PriceHistories\Tables\PriceHistoriesTable;
use App\Models\PriceHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceHistoryResource extends Resource
{
    protected static ?string $model = PriceHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Historial de precios';
    protected static ?string $breadcrumb = 'Historial de precios';
    protected static ?string $modelLabel = 'cambio de precio';
    protected static ?string $pluralModelLabel = 'historial de precios';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return PriceHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceHistories::route('/'),
        ];
    }
}
