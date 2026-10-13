<?php

namespace App\Filament\Resources\Initiatives;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Initiatives\Pages\CreateInitiative;
use App\Filament\Resources\Initiatives\Pages\EditInitiative;
use App\Filament\Resources\Initiatives\Pages\ListInitiatives;
use App\Filament\Resources\Initiatives\Schemas\InitiativeForm;
use App\Filament\Resources\Initiatives\Tables\InitiativesTable;
use App\Models\Initiative;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * الصلاحيات: initiatives.* — InitiativePolicy.
 */
class InitiativeResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'initiatives';

    protected static string $navigationGroupKey = 'content';

    protected static ?string $model = Initiative::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return InitiativeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InitiativesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInitiatives::route('/'),
            'create' => CreateInitiative::route('/create'),
            'edit' => EditInitiative::route('/{record}/edit'),
        ];
    }
}
