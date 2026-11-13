<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\GrantCompetitionRetake;
use App\Actions\Competitions\IssueCompetitionCertificates;
use App\Actions\Competitions\QualifyAttempts;
use App\Actions\Competitions\RevokeCompetitionRetake;
use App\Actions\Competitions\RunTieDraw;
use App\Enums\AttemptStatus;
use App\Enums\StudentGender;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionDraw;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRetake;
use App\Models\CompetitionRound;
use App\Support\CompetitionAccess;
use App\Support\NationalIdExport;
use App\Support\StudentResultsSheet;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * نتائج مسابقة الطلاب (لمن له submissions.view): ترتيب بالدرجة، وتصفية بالمرحلة والفئة والصف والمنطقة، وتصدير
 * إكسل (رقم الهوية مخفياً، وكاملاً في «التصدير الكامل» لمديري النظام بكلمة المرور)، والتأهيل للمرحلة التالية، والسحب بين المتساوين، وإصدار
 * الشهادات. و«نشر النتائج» في المراحل. والعمل كله في الإجراءات.
 */
class ResultsRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'attempts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.results');
    }

    protected static function canViewStudentPart(): bool
    {
        return auth()->user()?->can('viewAny', CompetitionAttempt::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('competitions.student.admin.result_model'))
            ->pluralModelLabel(__('competitions.student.admin.results'))
            // والتسجيل الملغى (CorrectStudentRegistration::cancel) خارج النتائج، كما في السحب والترتيب التراكمي
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['round', 'submission.studentCategory', 'submission.studentRegion'])
                ->whereHas('submission', fn (Builder $query) => $query->whereNull('deleted_at'))
                // إعادة لم تُستعمل لكل صف في الاستعلام نفسه، لزرّي المنح والإلغاء (docs/decisions/2026-11-13-feature-competition-retake.md)
                ->addSelect(['open_retake_id' => CompetitionRetake::query()->select('id')
                    ->whereColumn('round_id', 'competition_attempts.round_id')->whereColumn('submission_id', 'competition_attempts.submission_id')
                    ->whereNull('used_at')->latest('id')->limit(1)]))
            ->defaultSort('score', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('round.name')->label(__('competitions.student.question_attributes.round_id')),
                TextColumn::make('submission.reference')->label(__('competitions.entry.reference'))->copyable(),
                TextColumn::make('reference')->label(__('competitions.student.attempt_reference'))->copyable()->placeholder('—')->toggleable(),
                TextColumn::make('submission.gender')->label(__('competitions.student.attributes.gender'))->badge()->placeholder('—'),
                TextColumn::make('submission.full_name')->label(__('competitions.student.attributes.full_name'))->wrap(),
                TextColumn::make('submission.studentCategory.name')->label(__('competitions.student.attributes.category_id')),
                TextColumn::make('submission.grade')->label(__('competitions.student.attributes.grade'))->placeholder('—'),
                TextColumn::make('submission.studentRegion.name')->label(__('competitions.student.attributes.region_id'))->toggleable(),
                TextColumn::make('status')->label(__('competitions.fields.status'))->badge(),
                TextColumn::make('score')->label(__('competitions.student.admin.score'))->sortable()
                    ->state(fn (CompetitionAttempt $record): ?string => $record->isInProgress() ? null : StudentResultsSheet::score($record))->placeholder('—'),
                TextColumn::make('submitted_at')->label(__('competitions.entry.submitted_at'))->dateTime('Y-m-d H:i')->sortable()->placeholder('—'),
                IconColumn::make('auto_submitted')->label(__('competitions.student.admin.auto_submitted'))->boolean()->toggleable(),
                IconColumn::make('qualified_at')->label(__('competitions.student.admin.qualified'))->boolean()->state(fn (CompetitionAttempt $record): bool => $record->qualified_at !== null),
            ])
            ->filters([
                SelectFilter::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions()),
                SelectFilter::make('status')->label(__('competitions.fields.status'))->options(AttemptStatus::class),
                Filter::make('student')
                    ->schema([
                        Select::make('gender')->label(__('competitions.student.attributes.gender'))->options(StudentGender::class),
                        Select::make('category_id')->label(__('competitions.student.attributes.category_id'))->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all()),
                        Select::make('grade')->label(__('competitions.student.attributes.grade'))->options(fn (): array => $this->gradeOptions()),
                        Select::make('region_id')->label(__('competitions.student.attributes.region_id'))->options(fn (): array => CompetitionRegion::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['gender'] ?? null) || filled($data['category_id'] ?? null) || filled($data['grade'] ?? null) || filled($data['region_id'] ?? null),
                        fn (Builder $query) => $query->whereHas('submission', fn (Builder $query) => $query
                            ->when(filled($data['gender'] ?? null), fn (Builder $query) => $query->where('gender', $data['gender'] instanceof StudentGender ? $data['gender']->value : (string) $data['gender']))
                            ->when(filled($data['category_id'] ?? null), fn (Builder $query) => $query->where('category_id', (int) $data['category_id']))
                            ->when(filled($data['grade'] ?? null), fn (Builder $query) => $query->where('grade', (string) $data['grade']))
                            ->when(filled($data['region_id'] ?? null), fn (Builder $query) => $query->where('region_id', (int) $data['region_id']))),
                    )),
                TernaryFilter::make('qualified')->label(__('competitions.student.admin.qualified'))->nullable()->attribute('qualified_at'),
            ])
            // إعادة الاختبار لطالب بعينه: للمدير التنفيذي والمسؤول التقني وحدهما بالربط (CompetitionAccess)، والعمل في الإجراء
            // (docs/decisions/2026-11-13-feature-competition-retake.md)
            ->recordActions([
                Action::make('allow_retake')
                    ->label(__('competitions.student.retake.action'))
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading(__('competitions.student.retake.heading'))
                    ->modalDescription(__('competitions.student.retake.description'))
                    ->visible(fn (CompetitionAttempt $record): bool => $record->status === AttemptStatus::Submitted && $record->getAttribute('open_retake_id') === null && ! $record->round->resultsPublished() && CompetitionAccess::allows($this->actor(), GrantCompetitionRetake::PERMISSION))
                    ->schema(fn (CompetitionAttempt $record): array => [
                        Textarea::make('reason')->label(__('competitions.student.retake.reason'))->helperText(__('competitions.student.retake.reason_hint'))
                            ->required()->minLength(3)->maxLength(500),
                        DateTimePicker::make('until')->label(__('competitions.student.retake.until'))->helperText(__('competitions.student.retake.until_hint'))
                            ->seconds(false)->after('now')->maxDate(now()->addDays(GrantCompetitionRetake::MAX_DAYS))->required(fn (): bool => ! $record->round->isRunning()),
                    ])
                    ->action(fn (CompetitionAttempt $record, array $data) => $this->attempt(function () use ($record, $data): void {
                        app(GrantCompetitionRetake::class)->handle($this->actor(), $record, (string) $data['reason'], filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null);
                        Notification::make()->success()->title(__('competitions.student.retake.granted'))->send();
                    })),
                Action::make('revoke_retake')
                    ->label(__('competitions.student.retake.revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('competitions.student.retake.revoke_confirm'))
                    ->visible(fn (CompetitionAttempt $record): bool => $record->getAttribute('open_retake_id') !== null && CompetitionAccess::allows($this->actor(), GrantCompetitionRetake::PERMISSION))
                    ->action(fn (CompetitionAttempt $record) => $this->attempt(function () use ($record): void {
                        app(RevokeCompetitionRetake::class)->handle($this->actor(), CompetitionRetake::query()->findOrFail((int) $record->getAttribute('open_retake_id')));
                        Notification::make()->success()->title(__('competitions.student.retake.revoked'))->send();
                    })),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('competitions.student.admin.export_results'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => $this->managesResults())
                    ->action(fn (): StreamedResponse => StudentResultsSheet::download($this->actor(), $this->competition(), $this->getFilteredSortedTableQuery())),
                // رقم الهوية وجوال ولي الأمر كاملين: لمديري النظام وحدهم (NationalIdExport)، بكلمة المرور في كل مرة
                Action::make('export_full')
                    ->label(__('competitions.student.admin.export_results_full'))
                    ->icon('heroicon-o-identification')
                    ->color('danger')
                    ->visible(fn (): bool => $this->managesResults() && NationalIdExport::allows($this->actor()))
                    ->modalDescription(__('competitions.student.admin.export_full_hint'))
                    ->schema([self::passwordField()])
                    ->action(fn (array $data, Action $action): ?StreamedResponse => $this->attempt(
                        fn (): StreamedResponse => StudentResultsSheet::download($this->actor(), $this->competition(), $this->getFilteredSortedTableQuery(), full: true, password: (string) ($data['password'] ?? '')),
                        fn () => $action->halt(),
                    )),
                Action::make('tie_draw')
                    ->label(__('competitions.student.admin.tie_draw'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (): bool => $this->managesResults())
                    ->modalDescription(__('competitions.student.admin.tie_draw_hint'))
                    ->schema([
                        Select::make('round_id')->label(__('competitions.student.question_attributes.round_id'))->options(fn (): array => $this->roundOptions())->required(),
                        Select::make('category_id')->label(__('competitions.student.attributes.category_id'))
                            ->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all())
                            ->placeholder(__('competitions.student.admin.all_categories')),
                        TextInput::make('places')->label(__('competitions.student.admin.places'))->integer()->minValue(1)->required(),
                    ])
                    ->action(fn (array $data) => $this->attempt(function () use ($data): void {
                        [$draw, $fresh] = app(RunTieDraw::class)->handle(
                            $this->actor(),
                            $this->round((int) $data['round_id']),
                            filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
                            (int) $data['places'],
                        );

                        Notification::make()->success()
                            ->title($fresh ? __('competitions.student.admin.draw_done') : __('competitions.student.admin.draw_existing'))
                            ->body(self::drawSummary($draw))
                            ->persistent()
                            ->send();
                    })),
                Action::make('certificates')
                    ->label(__('competitions.student.admin.issue_certificates'))
                    ->icon('heroicon-o-academic-cap')
                    ->requiresConfirmation()
                    ->modalDescription(__('competitions.student.admin.issue_certificates_confirm'))
                    ->visible(fn (): bool => $this->managesResults())
                    ->action(fn () => $this->attempt(function (): void {
                        $count = app(IssueCompetitionCertificates::class)->handle($this->actor(), $this->competition());
                        Notification::make()->success()->title(__('competitions.student.admin.certificates_queued', ['count' => $count]))->send();
                    })),
            ])
            ->toolbarActions([
                BulkAction::make('qualify')
                    ->label(__('competitions.student.admin.qualify'))
                    ->icon('heroicon-o-arrow-trending-up')
                    ->visible(fn (): bool => $this->managesResults())
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->qualify($records, true)),
                BulkAction::make('unqualify')
                    ->label(__('competitions.student.admin.unqualify'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (): bool => $this->managesResults())
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->qualify($records, false)),
            ]);
    }

    /**
     * @param  Collection<int, CompetitionAttempt>  $records
     */
    private function qualify(Collection $records, bool $qualified): void
    {
        $this->attempt(function () use ($records, $qualified): void {
            $count = 0;

            foreach ($records->groupBy('round_id') as $roundId => $attempts) {
                $count += app(QualifyAttempts::class)->handle($this->actor(), $this->round((int) $roundId), array_map('intval', $attempts->modelKeys()), $qualified);
            }

            Notification::make()->success()->title(__('competitions.student.admin.qualified_count', ['count' => $count]))->send();
        });
    }

    public static function drawSummary(CompetitionDraw $draw): string
    {
        return __('competitions.student.admin.draw_summary', [
            'score' => $draw->tie_score,
            'entrants' => implode('، ', $draw->entrants),
            'winners' => implode('، ', $draw->winners),
            'seed' => $draw->seed,
            'hash' => $draw->entrants_hash,
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
     * @return array<string, string>
     */
    private function gradeOptions(): array
    {
        $grades = $this->competition()->openCategories()->get()->flatMap->gradeList()->unique()->values()->all();

        return array_combine($grades, $grades);
    }
}
