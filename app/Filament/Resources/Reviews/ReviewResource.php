<?php

namespace App\Filament\Resources\Reviews;

use App\Enums\QuestionType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionQuestion;
use App\Models\SubmissionReview;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * التحكيم: المحكّم يرى المشاركات المسندة إليه وحدها، بالرقم المرجعي والفئة والإجابات والمقاطع، بلا
 * اسم المتقدم ولا بريده ولا جواله، ويُدخل درجة كل معيار. والحفظ عبر ScoreSubmission.
 */
class ReviewResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'competitions.reviews';

    protected static string $navigationGroupKey = 'competitions';

    protected static ?string $model = SubmissionReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    /**
     * المحكّم يرى ما أُسند إليه فقط، ومن له submissions.view يرى الكل للمتابعة.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with('submission.competition')
            ->when(! $user->can('submissions.view'), fn (Builder $query) => $query->where('reviewer_id', $user->id));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('competitions.reviews.submission'))->columnSpanFull()->schema(fn (?SubmissionReview $record): array => $record === null ? [] : [
                TextEntry::make('reference')->label(__('competitions.entry.reference'))->state($record->submission->reference),
                TextEntry::make('competition')->label(__('competitions.model'))->state($record->submission->competition->title),
                TextEntry::make('category')->label(__('competitions.entry.category'))->state($record->submission->category)->placeholder('—'),
                ...$record->submission->competition->questions->map(fn (CompetitionQuestion $question, int $index) => $question->type === QuestionType::Video
                    ? View::make('filament.reviews.video')->viewData([
                        'label' => __('competitions.entry.question', ['number' => $index + 1]).': '.$question->prompt,
                        'video' => $record->submission->videos->firstWhere('question_id', $question->id),
                        'review' => $record,
                    ])
                    : TextEntry::make('answer_'.$question->id)
                        ->label(__('competitions.entry.question', ['number' => $index + 1]).': '.$question->prompt)
                        ->state(self::answerText($record, $question))
                        ->placeholder(__('competitions.entry.no_answer')))->all(),
            ]),
            Section::make(__('competitions.reviews.scores'))->columnSpanFull()->columns(2)->schema(fn (?SubmissionReview $record): array => $record === null ? [] : [
                ...$record->submission->competition->criteria->map(fn (CompetitionCriterion $criterion) => TextInput::make('scores.'.$criterion->id)
                    ->label(__('competitions.reviews.criterion', ['name' => $criterion->name, 'max' => $criterion->max_score, 'weight' => $criterion->weight]))
                    ->integer()
                    ->minValue(0)
                    ->maxValue($criterion->max_score))->all(),
                Toggle::make('complete')->label(__('competitions.reviews.complete'))->helperText(__('competitions.reviews.complete_hint'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('submission.reference')->label(__('competitions.entry.reference'))->searchable(),
                TextColumn::make('submission.competition.title')->label(__('competitions.model'))->wrap(),
                TextColumn::make('submission.category')->label(__('competitions.entry.category')),
                TextColumn::make('reviewer.name')->label(__('competitions.reviews.reviewer'))
                    ->visible(fn (): bool => auth()->user()?->can('submissions.view') ?? false),
                IconColumn::make('completed_at')->label(__('competitions.reviews.completed'))->boolean()->state(fn (SubmissionReview $record): bool => $record->completed_at !== null),
                TextColumn::make('total')->label(__('competitions.reviews.total'))->numeric(2),
            ])
            ->filters([
                TernaryFilter::make('completed_at')->label(__('competitions.reviews.completed'))->nullable(),
            ])
            ->recordActions([
                EditAction::make()->label(__('competitions.reviews.open')),
            ]);
    }

    private static function answerText(SubmissionReview $review, CompetitionQuestion $question): ?string
    {
        $answer = $review->submission->answer($question);

        if ($answer === null || $question->type !== QuestionType::MultipleChoice) {
            return $answer;
        }

        return collect($question->optionList())->firstWhere('key', $answer)['label'] ?? null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }
}
