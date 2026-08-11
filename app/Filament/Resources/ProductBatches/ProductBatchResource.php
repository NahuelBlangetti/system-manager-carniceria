<?php

namespace App\Filament\Resources\ProductBatches;

use App\Filament\Resources\ProductBatches\Pages\ListProductBatches;
use App\Filament\Resources\ProductBatches\Tables\ProductBatchesTable;
use App\Models\ProductBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductBatchResource extends Resource
{
    protected static ?string $model = ProductBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Lotes y vencimientos';
    protected static ?string $modelLabel = 'lote';
    protected static ?string $pluralModelLabel = 'lotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 5;

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
        return ProductBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductBatches::route('/'),
        ];
    }
}
