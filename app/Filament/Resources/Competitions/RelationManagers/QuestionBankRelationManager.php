<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\ImportStudentQuestions;
use App\Actions\Competitions\SaveStudentQuestion;
use App\Enums\QuestionType;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Support\StudentQuestionsSheet;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بنك أسئلة مسابقة الطلاب لكل مرحلة وفئة (قرار المدير التنفيذي: الأسئلة يضيفها المدير من اللوحة): اختيار من
 * متعدد بنص أو مقطع أو بلاهما، وصح وخطأ، والمقالي حين يُفعَّل. واستيراد من إكسل وتصدير إليه. والإجابة الصحيحة
 * لمن يدير المسابقات وحده، والبنك مقفل بعد أول محاولة في المرحلة. والحفظ في SaveStudentQuestion.
 */
class QuestionBankRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'roundQuestions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.bank');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $manages = $this->allows('competitions.update');

        return $schema->columns(2)->components([
            Select::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions())->required()->live()
                ->disabledOn('edit')->dehydrated(),
            Select::make('type')->label(__('competitions.student.question_attributes.type'))->required()->live()
                ->options(collect(QuestionType::student())->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->getLabel()])->all())
                ->default(QuestionType::MultipleChoice->value),
            Select::make('category_id')->label(__('competitions.student.question_attributes.category_id'))->options(fn (): array => PassagesRelationManager::categoryOptions())
                ->placeholder(__('competitions.student.admin.all_categories')),
            Select::make('passage_id')->label(__('competitions.student.question_attributes.passage_id'))
                ->options(fn (Get $get): array => $this->passageOptions($get('round_id'), self::type($get('type'))))
                ->visible(fn (Get $get): bool => self::type($get('type'))?->passageKind() !== null)
                ->required(fn (Get $get): bool => self::type($get('type'))?->passageKind() !== null),
            Textarea::make('prompt')->label(__('competitions.student.question_attributes.prompt'))->required()->rows(3)->maxLength(2000)->columnSpanFull(),
            Repeater::make('options')->label(__('competitions.student.question_attributes.options'))
                ->visible(fn (Get $get): bool => in_array(self::type($get('type')), [QuestionType::MultipleChoice, QuestionType::PassageChoice, QuestionType::VideoChoice], true))
                ->columns(2)->minItems(2)->maxItems(count(SaveStudentQuestion::OPTION_KEYS))->defaultItems(4)
                ->schema([
                    TextInput::make('label')->label(__('competitions.fields.option_label'))->required()->maxLength(500),
                    Toggle::make('is_correct')->label(__('competitions.fields.is_correct'))->inline(false)->visible($manages),
                ])
                ->columnSpanFull(),
            Radio::make('true_false')->label(__('competitions.student.question_attributes.true_false'))->inline()
                ->options(collect(SaveStudentQuestion::TRUE_FALSE_KEYS)->mapWithKeys(fn (string $key): array => [$key => __('competitions.student.true_false.'.$key)])->all())
                ->visible(fn (Get $get): bool => $manages && self::type($get('type')) === QuestionType::TrueFalse)
                ->required(fn (Get $get): bool => self::type($get('type')) === QuestionType::TrueFalse),
            TextInput::make('points')->label(__('competitions.fields.points'))->integer()->required()->minValue(1)->maxValue(100)->default(1),
            TextInput::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->integer()->minValue(0)->maxValue(10000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('competitions.student.admin.question_model'))
            ->pluralModelLabel(__('competitions.student.admin.questions_count'))
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('round_id')->with(['round', 'category', 'passage']))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('round.name')->label(__('competitions.student.question_attributes.round_id'))->sortable(),
                TextColumn::make('type')->label(__('competitions.student.question_attributes.type'))->badge(),
                TextColumn::make('prompt')->label(__('competitions.student.question_attributes.prompt'))->limit(90)->wrap()->searchable(),
                TextColumn::make('category.name')->label(__('competitions.student.question_attributes.category_id'))->default(__('competitions.student.admin.all_categories')),
                TextColumn::make('passage_id')->label(__('competitions.student.question_attributes.passage_id'))
                    ->state(fn (CompetitionQuestion $record): ?string => $record->passage?->label())->placeholder('—')->limit(40),
                TextColumn::make('points')->label(__('competitions.fields.points'))->numeric(),
            ])
            ->filters([
                SelectFilter::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions()),
                SelectFilter::make('category_id')->label(__('competitions.student.question_attributes.category_id'))->options(fn (): array => PassagesRelationManager::categoryOptions()),
                SelectFilter::make('type')->label(__('competitions.student.question_attributes.type'))
                    ->options(collect(QuestionType::student())->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->getLabel()])->all()),
            ])
            ->headerActions([
                CreateAction::make()->label(__('competitions.student.admin.add_question'))
                    ->using(fn (array $data, CreateAction $action): Model => $this->attempt(
                        fn (): CompetitionQuestion => app(SaveStudentQuestion::class)->handle($this->actor(), $this->round((int) $data['round_id']), null, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
                Action::make('import')
                    ->label(__('competitions.student.admin.import'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => $this->allows('competitions.update'))
                    ->modalDescription(__('competitions.student.admin.import_hint'))
                    ->schema([
                        Select::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions())->required(),
                        FileUpload::make('file')->label(__('competitions.student.admin.import_file'))->required()->storeFiles(false)
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(2048),
                    ])
                    ->action(function (array $data): void {
                        $this->attempt(function () use ($data): void {
                            /** @var TemporaryUploadedFile $file */
                            $file = $data['file'];
                            // نسخة محلية مؤقتة: الرفع المؤقت قد يكون على S3 في الإنتاج فلا مسار محلياً له
                            $path = (string) tempnam(sys_get_temp_dir(), 'questions');
                            file_put_contents($path, (string) $file->get());

                            try {
                                $count = app(ImportStudentQuestions::class)->handle($this->actor(), $this->round((int) $data['round_id']), $path);
                            } finally {
                                @unlink($path);
                            }

                            Notification::make()->success()->title(__('competitions.student.admin.imported', ['count' => $count]))->send();
                        });
                    }),
                Action::make('export')
                    ->label(__('competitions.student.admin.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => $this->allows('competitions.update'))
                    ->schema([
                        Select::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions())->required(),
                    ])
                    ->action(fn (array $data): StreamedResponse => StudentQuestionsSheet::download($this->actor(), $this->round((int) $data['round_id']))),
                Action::make('template')
                    ->label(__('competitions.student.admin.import_template'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (): bool => $this->allows('competitions.update'))
                    ->action(fn (): StreamedResponse => StudentQuestionsSheet::template()),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (CompetitionQuestion $record): array => self::formState($record, $this->allows('competitions.update')))
                    ->using(fn (CompetitionQuestion $record, array $data, EditAction $action): Model => $this->attempt(
                        fn (): CompetitionQuestion => app(SaveStudentQuestion::class)->handle($this->actor(), $this->round((int) $record->round_id), $record, self::normalize($data)),
                        fn () => $action->halt(),
                    )),
                DeleteAction::make()
                    ->using(fn (CompetitionQuestion $record): bool => (bool) $this->attempt(function () use ($record): bool {
                        app(DeleteStudentCompetitionItem::class)->handle($this->actor(), $record);

                        return true;
                    })),
            ]);
    }

    /**
     * السؤال بشكل النموذج، والإجابة الصحيحة لمن يدير المسابقات وحده.
     *
     * @return array<string, mixed>
     */
    public static function formState(CompetitionQuestion $question, bool $manages): array
    {
        return [
            'round_id' => $question->round_id,
            'type' => $question->type->value,
            'category_id' => $question->category_id,
            'passage_id' => $question->passage_id,
            'prompt' => $question->prompt,
            'points' => $question->points,
            'sort_order' => $question->sort_order,
            'options' => $question->type === QuestionType::TrueFalse ? [] : array_map(fn (array $option): array => [
                'label' => $option['label'],
                'is_correct' => $manages && $option['key'] === $question->correct_option,
            ], $question->optionList()),
            'true_false' => $manages && $question->type === QuestionType::TrueFalse ? $question->correct_option : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roundOptions(): array
    {
        return $this->competition()->rounds()->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function passageOptions(mixed $roundId, ?QuestionType $type): array
    {
        $kind = $type?->passageKind();

        if ($kind === null || blank($roundId)) {
            return [];
        }

        return CompetitionPassage::query()->where('competition_id', $this->competition()->id)->where('round_id', (int) $roundId)->where('kind', $kind)->get()
            ->mapWithKeys(fn (CompetitionPassage $passage): array => [$passage->id => '#'.$passage->id.' — '.$passage->label()])->all();
    }

    private function round(int $id): CompetitionRound
    {
        return $this->competition()->rounds()->whereKey($id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $data['type'] = self::type($data['type'] ?? null)?->value;

        return $data;
    }

    private static function type(mixed $value): ?QuestionType
    {
        return $value instanceof QuestionType ? $value : QuestionType::tryFrom((string) $value);
    }
}
