<?php

namespace App\Filament\Resources\CompetitionCategories;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\CompetitionCategories\Pages\ManageCompetitionCategories;
use App\Models\CompetitionCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * فئات مسابقة الطلاب (قرار المدير التنفيذي: المرحلة المتوسطة، والثانوية، والجامعية، والمهتمون، وقائمة قابلة
 * للإضافة والتعديل من اللوحة)، ولكل فئة صفوفها. وتُفتح لكل مسابقة فئاتها من نموذجها. والفئة المستعملة في تسجيل
 * أو سؤال لا تُحذف (قيد في القاعدة). الصلاحيات: CompetitionCategoryPolicy.
 */
class CompetitionCategoryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'competitions.student.categories';

    protected static string $navigationGroupKey = 'competitions';

    protected static ?string $model = CompetitionCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('competitions.student.categories.name'))->required()->maxLength(100),
            TextInput::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->integer()->minValue(0)->maxValue(1000)->default(0),
            TagsInput::make('grades')->label(__('competitions.student.categories.grades'))->helperText(__('competitions.student.categories.grades_hint'))
                ->rules(['array', 'max:20'])->nestedRecursiveRules(['string', 'max:60'])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('competitions.student.categories.name')),
                TextColumn::make('grades')->label(__('competitions.student.categories.grades'))->badge()->placeholder('—'),
                TextColumn::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CompetitionCategory $record): bool => ! $record->inUse()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompetitionCategories::route('/'),
        ];
    }
}
