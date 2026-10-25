<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\SaveCompetitionRound;
use App\Enums\AttemptStatus;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionRound;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
                ->default(fn (): string => __('competitions.student.admin.round_default', ['number' => $this->competition()->rounds()->count() + 1])),
            TextInput::make('sort_order')->label(__('competitions.student.round_attributes.sort_order'))->integer()->minValue(0)->maxValue(1000)
                ->default(fn (): int => (int) $this->competition()->rounds()->max('sort_order') + 1),
            DateTimePicker::make('starts_at')->label(__('competitions.student.round_attributes.starts_at'))->seconds(false)->required()
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            DateTimePicker::make('ends_at')->label(__('competitions.student.round_attributes.ends_at'))->seconds(false)->required()->after('starts_at'),
            TextInput::make('question_count')->label(__('competitions.student.round_attributes.question_count'))->integer()->required()
                ->minValue(1)->maxValue((int) config('competitions.student.max_questions'))
                ->helperText(__('competitions.student.admin.question_count_hint'))
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            TextInput::make('duration_minutes')->label(__('competitions.student.round_attributes.duration_minutes'))->integer()->required()
                ->minValue($min)->maxValue($max)
                ->helperText(__('competitions.student.admin.duration_hint'))
                ->disabled(fn (?CompetitionRound $record): bool => $record?->locked() ?? false)->dehydrated(),
            Toggle::make('qualified_only')->label(__('competitions.student.round_attributes.qualified_only'))->helperText(__('competitions.student.admin.qualified_only_hint'))->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'attempts as started_count',
                    'attempts as submitted_count' => fn (Builder $query) => $query->where('status', AttemptStatus::Submitted),
                    'questions',
                ]))
            ->defaultSort('sort_order')
            // متابعة حية في أيام النوافذ: الأعداد تتجدد كل نصف دقيقة
            ->poll('30s')
            ->columns([
                TextColumn::make('name')->label(__('competitions.student.round_attributes.name'))->weight('bold'),
                TextColumn::make('starts_at')->label(__('competitions.student.round_attributes.starts_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label(__('competitions.student.round_attributes.ends_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('question_count')->label(__('competitions.student.round_attributes.question_count'))->numeric(),
                TextColumn::make('questions_count')->label(__('competitions.student.admin.bank_size'))->numeric(),
                TextColumn::make('duration_minutes')->label(__('competitions.student.round_attributes.duration_minutes'))->numeric(),
                IconColumn::make('qualified_only')->label(__('competitions.student.round_attributes.qualified_only'))->boolean(),
                TextColumn::make('started_count')->label(__('competitions.student.admin.started'))->numeric(),
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
                    ->visible(fn (CompetitionRound $record): bool => $this->allows('competitions.approve') && ! $record->resultsPublished())
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
