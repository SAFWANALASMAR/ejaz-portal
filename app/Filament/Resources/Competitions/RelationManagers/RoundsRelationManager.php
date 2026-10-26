<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\SaveCompetitionRound;
use App\Enums\AttemptStatus;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionRound;
use App\Support\AttemptLayout;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * مراحل مسابقة الطلاب (قرار المدير التنفيذي: أربع مراحل مبدئياً، وعددها يزيد وينقص من اللوحة، ولكل مرحلة
 * نافذتها وعدد أسئلتها ومدتها). ومنها المتابعة الحية (من بدأ ومن سلّم) و«نشر النتائج». والحفظ في الإجراءات.
 */
class RoundsRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'rounds';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.rounds');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $min = (int) config('competitions.student.min_minutes');
        $max = (int) config('competitions.student.max_minutes');

        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('competitions.student.round_attributes.name'))->required()->maxLength(100)
                ->default(function (): string {
                    $number = $this->competition()->rounds()->count() + 1;

                    return __('competitions.student.admin.round_default', ['number' => __('competitions.student.ordinals')[$number] ?? $number]);
                }),
            TextInput::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->integer()->minValue(0)->maxValue(1000)
                ->default(fn (): int => (int) $this->competition()->rounds()->max('sort_order') + 1),
            DateTimePicker::make('starts_at')->label(__('competitions.student.round_attributes.starts_at'))->seconds(false)->required()
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            DateTimePicker::make('ends_at')->label(__('competitions.student.round_attributes.ends_at'))->seconds(false)->required()->after('starts_at'),
            TextInput::make('question_count')->label(__('competitions.student.round_attributes.question_count'))->integer()->required()
                ->minValue((int) config('competitions.student.min_questions'))->maxValue((int) config('competitions.student.max_questions'))
                ->default((int) config('competitions.student.default_questions'))
                ->helperText(__('competitions.student.admin.question_count_hint', ['min' => config('competitions.student.min_questions'), 'max' => config('competitions.student.max_questions')]))
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            TextInput::make('duration_minutes')->label(__('competitions.student.round_attributes.duration_minutes'))->integer()->required()
                ->minValue($min)->maxValue($max)->default((int) config('competitions.student.default_minutes'))
                ->helperText(__('competitions.student.admin.duration_hint', ['min' => $min, 'max' => $max]))
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            Toggle::make('qualified_only')->label(__('competitions.student.round_attributes.qualified_only'))->helperText(__('competitions.student.admin.qualified_only_hint'))->columnSpanFull()
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            TextInput::make('branch')->label(__('competitions.student.round_attributes.branch'))->helperText(__('competitions.student.admin.branch_hint'))->maxLength(200)->columnSpanFull(),
            FileUpload::make('image_path')->label(__('competitions.student.round_attributes.image_path'))
                ->disk('public')->directory(CompetitionRound::IMAGE_DIRECTORY)->visibility('public')
                ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'attempts as started_count',
                    // يختبرون الآن: جارية لم ينته وقتها
                    'attempts as running_count' => fn (Builder $query) => $query->where('status', AttemptStatus::InProgress)->where('deadline_at', '>', now()),
                    'attempts as submitted_count' => fn (Builder $query) => $query->where('status', AttemptStatus::Submitted),
                    'questions',
                ]))
            ->defaultSort('sort_order')
            // متابعة حية في أيام النوافذ: المسجلون، ومن يختبر الآن، ومن سلّم، تتجدد كل خمس عشرة ثانية
            ->poll('15s')
            ->description(fn (): string => __('competitions.student.admin.registered_count', ['count' => $this->competition()->submissions()->whereNotNull('registered_at')->count()]))
            ->columns([
                TextColumn::make('name')->label(__('competitions.student.round_attributes.name'))->weight('bold'),
                TextColumn::make('branch')->label(__('competitions.student.round_attributes.branch'))->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('starts_at')->label(__('competitions.student.round_attributes.starts_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label(__('competitions.student.round_attributes.ends_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('question_count')->label(__('competitions.student.round_attributes.question_count'))->numeric(),
                TextColumn::make('questions_count')->label(__('competitions.student.admin.bank_size'))->numeric()
                    // بنك كل فئة مفتوحة بعدد أسئلة المرحلة تماماً، وإلا لا يبدأ طلابها (AttemptLayout وStartAttempt)
                    // فحص الجاهزية قبل النافذة: «جاهز» حين تكتمل البنوك وتتوحّد الدرجة القصوى بين الفئات (حين يُرتَّب المجموع لكل
                    // الفئات معاً، StartAttempt يرفض غيرها)، وإلا ما ينقص وحده، بلا رسالتين متناقضتين
                    ->description(function (CompetitionRound $record): string {
                        $short = AttemptLayout::shortfalls($record->setRelation('competition', $this->competition()));
                        $mixed = AttemptLayout::mixedMaxScores($record);

                        return implode(' ', array_filter([
                            $short !== [] ? __('competitions.student.bank_short', ['details' => collect($short)->map(fn (int $count, string $name): string => $count < 0
                                ? __('competitions.student.bank_short_points', ['category' => $name])
                                : __('competitions.student.bank_short_count', ['category' => $name, 'count' => $count]))->implode('، ')]) : null,
                            $mixed !== [] ? __('competitions.student.bank_mixed_max', ['details' => collect($mixed)->map(fn (float $max, string $name): string => $name.' '.Number::format($max, maxPrecision: 2))->implode('، ')]) : null,
                        ])) ?: __('competitions.student.bank_ready');
                    }),
                TextColumn::make('duration_minutes')->label(__('competitions.student.round_attributes.duration_minutes'))->numeric(),
                IconColumn::make('qualified_only')->label(__('competitions.student.round_attributes.qualified_only'))->boolean(),
                TextColumn::make('started_count')->label(__('competitions.student.admin.started'))->numeric(),
                TextColumn::make('running_count')->label(__('competitions.student.admin.running'))->numeric(),
                TextColumn::make('submitted_count')->label(__('competitions.student.admin.submitted'))->numeric(),
                TextColumn::make('results_published_at')->label(__('competitions.student.admin.results_published_at'))->dateTime('Y-m-d H:i')->placeholder(__('competitions.student.admin.not_published')),
            ])
            ->headerActions([
                CreateAction::make()->label(__('competitions.student.admin.add_round'))
                    ->using(fn (array $data, CreateAction $action): Model => $this->attempt(
                        fn (): CompetitionRound => app(SaveCompetitionRound::class)->handle($this->actor(), $this->competition(), null, $data),
                        fn () => $action->halt(),
                    )),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('competitions.student.admin.publish_results'))
                    ->icon('heroicon-o-megaphone')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('competitions.student.admin.publish_confirm'))
                    ->visible(fn (CompetitionRound $record): bool => $this->managesResults() && ! $record->resultsPublished())
                    ->action(fn (CompetitionRound $record) => $this->attempt(function () use ($record): void {
                        app(PublishRoundResults::class)->handle($this->actor(), $record);
                        Notification::make()->success()->title(__('competitions.student.admin.published'))->send();
                    })),
                EditAction::make()
                    ->using(fn (CompetitionRound $record, array $data, EditAction $action): Model => $this->attempt(
                        fn (): CompetitionRound => app(SaveCompetitionRound::class)->handle($this->actor(), $this->competition(), $record, $data),
                        fn () => $action->halt(),
                    )),
                DeleteAction::make()
                    ->visible(fn (CompetitionRound $record): bool => $this->allows('competitions.update') && ! $record->locked())
                    ->using(fn (CompetitionRound $record): bool => (bool) $this->attempt(function () use ($record): bool {
                        app(DeleteStudentCompetitionItem::class)->handle($this->actor(), $record);

                        return true;
                    })),
            ]);
    }
}
