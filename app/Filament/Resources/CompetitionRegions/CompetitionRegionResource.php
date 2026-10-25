<?php

namespace App\Filament\Resources\CompetitionRegions;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\CompetitionRegions\Pages\ManageCompetitionRegions;
use App\Models\CompetitionRegion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * مناطق مسابقة الطلاب (قرار المدير التنفيذي: لكل المناطق، ومبدئياً منطقة مكة المكرمة): قائمة من اللوحة يُفعّل
 * منها ما يُراد، ويختار الطالب من المفعّلة وحدها. والمستعملة في تسجيل لا تُحذف. الصلاحيات: CompetitionRegionPolicy.
 */
class CompetitionRegionResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'competitions.student.regions';

    protected static string $navigationGroupKey = 'competitions';

    protected static ?string $model = CompetitionRegion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('competitions.student.regions.name'))->required()->maxLength(60),
            TextInput::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->integer()->minValue(0)->maxValue(1000)->default(0),
            Toggle::make('is_active')->label(__('competitions.student.regions.is_active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('competitions.student.regions.name')),
                // التفعيل من الجدول مباشرة لمن يعدّل (CompetitionRegionPolicy)
                ToggleColumn::make('is_active')->label(__('competitions.student.regions.is_active'))
                    ->disabled(fn (): bool => ! (auth()->user()?->can('competitions.update') ?? false)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CompetitionRegion $record): bool => ! $record->inUse()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompetitionRegions::route('/'),
        ];
    }
}
