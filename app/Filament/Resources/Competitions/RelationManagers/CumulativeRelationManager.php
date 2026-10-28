<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\RunCumulativeTieDraw;
use App\Enums\RankingScope;
use App\Enums\StudentGender;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Support\CumulativeRanking;
use App\Support\CumulativeRankingSheet;
use App\Support\NationalIdExport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الترتيب التراكمي في مسابقة الطلاب (مطوية «البراهين العلمية»): مجموع الفروع لكل طالب، والترتيب لمن شارك في كلها لكل
 * جنس (ولكل فئة إن كان النطاق كذلك)، والتصدير، والسحب للمتساوين على حد المراكز. والحساب في CumulativeRanking والسحب
 * في RunCumulativeTieDraw.
 */
class CumulativeRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'submissions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.cumulative');
    }

    protected static function canViewStudentPart(): bool
    {
        return auth()->user()?->can('viewAny', CompetitionAttempt::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $rounds = $this->competition()->rounds()->count();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => CumulativeRanking::apply($query, $this->competition())->with(['studentCategory']))
            ->description(fn (): ?string => CumulativeRanking::hasMixedMax($this->competition()) ? __('competitions.student.cumulative_mixed_max') : null)
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('cumulative_rank ASC NULLS LAST')->orderByDesc('cumulative_total'))
            // على الجوال: الترتيب والاسم والجنس والمجموع، فلا يتجاوز رأس الجدول عرض الشاشة (axe: scrollable-region-focusable)
            ->columns([
                TextColumn::make('cumulative_rank')->label(__('competitions.student.admin.cumulative_rank'))->placeholder('—')->weight('bold'),
                TextColumn::make('reference')->label(__('competitions.entry.reference'))->searchable()->copyable()->visibleFrom('md'),
                TextColumn::make('full_name')->label(__('competitions.student.attributes.full_name'))->searchable()->wrap(),
                TextColumn::make('gender')->label(__('competitions.student.attributes.gender'))->badge()->placeholder('—'),
                TextColumn::make('studentCategory.name')->label(__('competitions.student.attributes.category_id'))->visibleFrom('md'),
                TextColumn::make('rounds_done')->label(__('competitions.student.admin.rounds_done'))->visibleFrom('md')
                    ->state(fn (CompetitionSubmission $record): string => Number::format((int) $record->getAttribute('rounds_done')).' / '.Number::format($rounds)),
                TextColumn::make('cumulative_total')->label(__('competitions.student.admin.cumulative_total'))
                    ->state(fn (CompetitionSubmission $record): string => Number::format((float) $record->getAttribute('cumulative_total'), maxPrecision: 2).' / '.Number::format((float) $record->getAttribute('cumulative_max'), maxPrecision: 2)),
            ])
            ->filters([
                SelectFilter::make('gender')->label(__('competitions.student.attributes.gender'))->options(StudentGender::class),
                SelectFilter::make('category_id')->label(__('competitions.student.attributes.category_id'))->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all()),
                TernaryFilter::make('eligible')->label(__('competitions.student.admin.eligible'))->default(true)
                    ->queries(true: fn (Builder $query) => $query->where('eligible', true), false: fn (Builder $query) => $query->where('eligible', false), blank: fn (Builder $query) => $query),
            ])
            ->headerActions([
                Action::make('export_cumulative')
                    ->label(__('competitions.student.admin.export_cumulative'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => $this->managesResults())
                    ->action(fn (): StreamedResponse => CumulativeRankingSheet::download($this->actor(), $this->competition(), $this->getFilteredSortedTableQuery())),
                // رقم الهوية كاملاً: لمديري النظام وحدهم (NationalIdExport)، بكلمة المرور في كل مرة
                Action::make('export_cumulative_full')
                    ->label(__('competitions.student.admin.export_cumulative_full'))
                    ->icon('heroicon-o-identification')
                    ->color('danger')
                    ->visible(fn (): bool => $this->managesResults() && NationalIdExport::allows($this->actor()))
                    ->modalDescription(__('competitions.student.admin.export_full_hint'))
                    ->schema([self::passwordField()])
                    ->action(fn (array $data, Action $action): ?StreamedResponse => $this->attempt(
                        fn (): StreamedResponse => CumulativeRankingSheet::download($this->actor(), $this->competition(), $this->getFilteredSortedTableQuery(), full: true, password: (string) ($data['password'] ?? '')),
                        fn () => $action->halt(),
                    )),
                Action::make('cumulative_draw')
                    ->label(__('competitions.student.admin.cumulative_draw'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (): bool => $this->managesResults())
                    ->modalDescription(__('competitions.student.admin.cumulative_draw_hint'))
                    ->schema([
                        Select::make('gender')->label(__('competitions.student.attributes.gender'))->options(StudentGender::class)->required(),
                        Select::make('category_id')->label(__('competitions.student.attributes.category_id'))
                            ->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all())
                            ->visible(fn (): bool => $this->competition()->ranking_scope === RankingScope::Category)
                            ->required(fn (): bool => $this->competition()->ranking_scope === RankingScope::Category),
                        TextInput::make('places')->label(__('competitions.student.admin.places'))->integer()->minValue(1)->required()
                            ->default(fn (): ?int => count($this->competition()->prizeRows()) ?: null),
                    ])
                    ->action(fn (array $data) => $this->attempt(function () use ($data): void {
                        $gender = $data['gender'] instanceof StudentGender ? $data['gender'] : StudentGender::from((string) $data['gender']);
                        [$draw, $fresh] = app(RunCumulativeTieDraw::class)->handle(
                            $this->actor(),
                            $this->competition(),
                            $gender,
                            filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
                            (int) $data['places'],
                        );

                        Notification::make()->success()
                            ->title($fresh ? __('competitions.student.admin.draw_done') : __('competitions.student.admin.draw_existing'))
                            ->body(ResultsRelationManager::drawSummary($draw))
                            ->persistent()
                            ->send();
                    })),
            ]);
    }
}
