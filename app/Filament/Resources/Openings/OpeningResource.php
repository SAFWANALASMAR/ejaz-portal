<?php

namespace App\Filament\Resources\Openings;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Openings\Pages\CreateOpening;
use App\Filament\Resources\Openings\Pages\EditOpening;
use App\Filament\Resources\Openings\Pages\ListOpenings;
use App\Filament\Resources\Openings\Schemas\OpeningForm;
use App\Filament\Resources\Openings\Tables\OpeningsTable;
use App\Models\Opening;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * فرص الخدمات الإلكترونية: الانضمام والوظائف والتطوع بحقول نماذجها. الصلاحيات: openings.* —
 * OpeningPolicy. الحذف ناعم ويُستعاد.
 */
class OpeningResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'services.openings';

    protected static string $navigationGroupKey = 'requests';

    protected static ?string $model = Opening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return OpeningForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpeningsTable::configure($table);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenings::route('/'),
            'create' => CreateOpening::route('/create'),
            'edit' => EditOpening::route('/{record}/edit'),
        ];
    }
}
