<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\SaveCompetitionPassage;
use App\Enums\PassageKind;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionRound;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * النصوص المقروءة ومقاطع يوتيوب في مسابقة الطلاب: لكل مرحلة (وفئة اختيارياً)، وأسئلتها في بنك الأسئلة بنوع
 * «اختيار على نص» أو «اختيار على مقطع». والحفظ في SaveCompetitionPassage، ومقفلة مع البنك بعد أول محاولة.
 */
class PassagesRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'passages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.passages');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions())->required()
                ->disabledOn('edit'),
            Select::make('kind')->label(__('competitions.student.passage_attributes.kind'))->options(PassageKind::class)->required()->live()->default(PassageKind::Text->value),
            Select::make('category_id')->label(__('competitions.student.passage_attributes.category_id'))->options(fn (): array => self::categoryOptions())
                ->placeholder(__('competitions.student.admin.all_categories')),
            TextInput::make('title')->label(__('competitions.student.passage_attributes.title'))->maxLength(200),
            TextInput::make('video_url')->label(__('competitions.student.passage_attributes.video_url'))->url()->maxLength(255)->extraInputAttributes(['dir' => 'ltr'])
                ->helperText(__('competitions.student.admin.video_url_hint'))
                ->visible(fn (Get $get): bool => self::kind($get('kind')) === PassageKind::Video)
                ->required(fn (Get $get): bool => self::kind($get('kind')) === PassageKind::Video)
                ->columnSpanFull(),
            Textarea::make('body')->label(fn (Get $get): string => self::kind($get('kind')) === PassageKind::Video ? __('competitions.student.admin.video_note') : __('competitions.student.passage_attributes.body'))
                ->rows(8)->maxLength(10000)
                ->required(fn (Get $get): bool => self::kind($get('kind')) === PassageKind::Text)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('competitions.student.admin.passage_model'))
            ->pluralModelLabel(__('competitions.student.admin.passages'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['round', 'category'])->withCount('questions'))
            ->columns([
                TextColumn::make('round.name')->label(__('competitions.student.question_attributes.round_id')),
                TextColumn::make('kind')->label(__('competitions.student.passage_attributes.kind'))->badge(),
                TextColumn::make('title')->label(__('competitions.student.passage_attributes.title'))
                    ->state(fn (CompetitionPassage $record): string => $record->label())->wrap(),
                TextColumn::make('category.name')->label(__('competitions.student.passage_attributes.category_id'))->default(__('competitions.student.admin.all_categories')),
                TextColumn::make('questions_count')->label(__('competitions.student.admin.questions_count'))->numeric(),
            ])
            ->filters([
                SelectFilter::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions()),
            ])
            ->headerActions([
                CreateAction::make()->label(__('competitions.student.admin.add_passage'))
                    ->using(fn (array $data, CreateAction $action): Model => $this->attempt(
                        fn (): CompetitionPassage => app(SaveCompetitionPassage::class)->handle($this->actor(), $this->round((int) $data['round_id']), null, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (CompetitionPassage $record, array $data, EditAction $action): Model => $this->attempt(
                        fn (): CompetitionPassage => app(SaveCompetitionPassage::class)->handle($this->actor(), $this->round((int) $record->round_id), $record, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
                DeleteAction::make()
                    ->using(fn (CompetitionPassage $record): bool => (bool) $this->attempt(function () use ($record): bool {
                        app(DeleteStudentCompetitionItem::class)->handle($this->actor(), $record);

                        return true;
                    })),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function roundOptions(): array
    {
        return $this->competition()->rounds()->pluck('name', 'id')->all();
    }

    private function round(int $id): CompetitionRound
    {
        return $this->competition()->rounds()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        return CompetitionCategory::query()->ordered()->pluck('name', 'id')->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $data['kind'] = self::kind($data['kind'] ?? null)?->value;

        return $data;
    }

    private static function kind(mixed $value): ?PassageKind
    {
        return $value instanceof PassageKind ? $value : PassageKind::tryFrom((string) $value);
    }
}
