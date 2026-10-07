<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\ApproveResults;
use App\Actions\Competitions\AssignReviewers;
use App\Actions\Competitions\DistributeSubmissions;
use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use App\Filament\Exports\SubmissionExporter;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * مشاركات المسابقة لمن له submissions.view: بحالتها ودرجتيها وعدد محكّميها. ومنها الإسناد اليدوي
 * والتوزيع الآلي (submissions.assign)، واعتماد النتائج (competitions.approve). والعمل كله في الإجراءات.
 */
class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.submissions.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', CompetitionSubmission::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('reviews'))
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('competitions.entry.reference'))->searchable()->placeholder('—'),
                TextColumn::make('user.name')->label(__('competitions.submissions.applicant')),
                TextColumn::make('status')->label(__('competitions.fields.status'))->badge(),
                TextColumn::make('category')->label(__('competitions.entry.category'))->toggleable(),
                TextColumn::make('region')->label(__('competitions.entry.region'))->toggleable(),
                TextColumn::make('submitted_at')->label(__('competitions.entry.submitted_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('auto_score')->label(__('competitions.submissions.auto_score'))->numeric(2)->sortable(),
                TextColumn::make('reviews_count')->label(__('competitions.submissions.reviewers')),
                TextColumn::make('final_score')->label(__('competitions.submissions.final_score'))->numeric(2)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('competitions.fields.status'))->options(SubmissionStatus::class),
                SelectFilter::make('region')->label(__('competitions.entry.region'))->options(SaudiRegion::class),
            ])
            ->headerActions([
                // التصدير الكامل في الطابور، بمشاركات هذه المسابقة وحدها
                ExportAction::make()
                    ->label(__('competitions.export.action'))
                    ->exporter(SubmissionExporter::class)
                    ->visible(fn (): bool => $this->allows('submissions.export'))
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('competition_id', $this->competition()->id)->with('user')),
                Action::make('distribute')
                    ->label(__('competitions.reviews.distribute'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (): bool => $this->allows('submissions.assign') && ! $this->competition()->resultsApproved())
                    ->schema([
                        TextInput::make('per_submission')->label(__('competitions.reviews.per_submission_label'))->integer()->minValue(1)->default(1)->required(),
                    ])
                    ->action(fn (array $data) => $this->run(function () use ($data): string {
                        $count = app(DistributeSubmissions::class)->handle($this->user(), $this->competition(), (int) $data['per_submission']);

                        return __('competitions.reviews.assigned', ['count' => $count]);
                    })),
                Action::make('approve')
                    ->label(__('competitions.reviews.approve'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('competitions.reviews.approve_confirm'))
                    ->visible(fn (): bool => $this->allows('competitions.approve') && ! $this->competition()->resultsApproved())
                    ->action(fn () => $this->run(function (): string {
                        app(ApproveResults::class)->handle($this->user(), $this->competition());

                        return __('competitions.reviews.approved');
                    })),
            ])
            ->toolbarActions([
                BulkAction::make('assign')
                    ->label(__('competitions.reviews.assign'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->allows('submissions.assign') && ! $this->competition()->resultsApproved())
                    ->schema([
                        Select::make('reviewers')
                            ->label(__('competitions.fields.reviewers'))
                            ->multiple()
                            ->required()
                            ->options(fn (): array => $this->competition()->reviewers()->orderBy('name')->pluck('name', 'users.id')->all()),
                    ])
                    ->action(fn (Collection $records, array $data) => $this->run(function () use ($records, $data): string {
                        $count = app(AssignReviewers::class)->handle(
                            $this->user(),
                            $this->competition(),
                            array_map('intval', $records->modelKeys()),
                            array_map('intval', $data['reviewers']),
                        );

                        return __('competitions.reviews.assigned', ['count' => $count]);
                    })),
            ]);
    }

    /**
     * ينفّذ الإجراء ويعرض نتيجته، ورفضه برسالته (لا تقابل حقول النافذة).
     *
     * @param  Closure(): string  $action
     */
    private function run(Closure $action): void
    {
        try {
            Notification::make()->success()->title($action())->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
        }
    }

    private function competition(): Competition
    {
        /** @var Competition */
        return $this->getOwnerRecord();
    }

    private function allows(string $permission): bool
    {
        return auth()->user()?->can($permission) ?? false;
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
