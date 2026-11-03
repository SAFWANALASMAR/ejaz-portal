<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\SaveCompetitionField;
use App\Enums\CompetitionFieldType;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionField;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * حقول التسجيل المخصصة في مسابقة الطلاب (قرار المدير التنفيذي: تُضاف من اللوحة بلا برمجة): نص، أو رقم، أو
 * اختيار بخياراته. مثل «اسم المدرسة» إن أرادته الجمعية. والحفظ في SaveCompetitionField.
 */
class FieldsRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'fields';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.fields');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('label')->label(__('competitions.student.field_attributes.label'))->required()->maxLength(150),
            Select::make('type')->label(__('competitions.student.field_attributes.type'))->options(CompetitionFieldType::class)->required()->live()
                ->default(CompetitionFieldType::Text->value),
            TagsInput::make('options')->label(__('competitions.student.field_attributes.options'))->helperText(__('competitions.student.admin.field_options_hint'))
                ->visible(fn (Get $get): bool => self::isSelect($get('type')))
                ->required(fn (Get $get): bool => self::isSelect($get('type')))
                ->columnSpanFull(),
            Toggle::make('is_required')->label(__('competitions.student.field_attributes.is_required')),
            TextInput::make('sort_order')->label(__('competitions.student.field_attributes.sort_order'))->integer()->minValue(0)->maxValue(1000)
                ->default(fn (): int => (int) $this->competition()->fields()->max('sort_order') + 1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('competitions.student.admin.field_model'))
            ->pluralModelLabel(__('competitions.student.admin.fields'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')->label(__('competitions.student.field_attributes.label'))->weight('bold'),
                TextColumn::make('type')->label(__('competitions.student.field_attributes.type'))->badge(),
                TextColumn::make('options')->label(__('competitions.student.field_attributes.options'))->badge()->placeholder('—'),
                IconColumn::make('is_required')->label(__('competitions.student.field_attributes.is_required'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label(__('competitions.student.admin.add_field'))
                    ->using(fn (array $data, CreateAction $action): Model => $this->attempt(
                        fn (): CompetitionField => app(SaveCompetitionField::class)->handle($this->actor(), $this->competition(), null, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (CompetitionField $record, array $data, EditAction $action): Model => $this->attempt(
                        fn (): CompetitionField => app(SaveCompetitionField::class)->handle($this->actor(), $this->competition(), $record, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
                DeleteAction::make()
                    ->using(fn (CompetitionField $record): bool => (bool) $this->attempt(function () use ($record): bool {
                        app(DeleteStudentCompetitionItem::class)->handle($this->actor(), $record);

                        return true;
                    })),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $data['type'] = $data['type'] instanceof CompetitionFieldType ? $data['type']->value : ($data['type'] ?? null);

        return $data;
    }

    private static function isSelect(mixed $type): bool
    {
        return ($type instanceof CompetitionFieldType ? $type->value : $type) === CompetitionFieldType::Select->value;
    }
}
