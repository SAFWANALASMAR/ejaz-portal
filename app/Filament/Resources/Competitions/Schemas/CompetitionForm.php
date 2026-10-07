<?php

namespace App\Filament\Resources\Competitions\Schemas;

use App\Enums\QuestionType;
use App\Filament\Forms\ContentFields;
use App\Models\Competition;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionQuestion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج المسابقة بتبويبات: البيانات، ومعايير التقييم، وبنك الأسئلة. والمعايير والأسئلة تُحفظ مع
 * المسابقة في SaveCompetition، ويُعاد فحصها كلها هناك.
 */
class CompetitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('competitions.tabs.details'))->columns(2)->schema([
                    TextInput::make('title')->label(__('competitions.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                    ContentFields::slug(),
                    TagsInput::make('categories')
                        ->label(__('competitions.fields.categories'))
                        ->helperText(__('competitions.fields.categories_hint'))
                        ->rules(['array', 'max:20'])
                        ->nestedRecursiveRules(['string', 'max:60']),
                    DateTimePicker::make('opens_at')->label(__('competitions.fields.opens_at'))->helperText(__('competitions.fields.dates_hint'))->seconds(false)->required(),
                    DateTimePicker::make('closes_at')->label(__('competitions.fields.closes_at'))->seconds(false)->required()->after('opens_at'),
                    Textarea::make('description')->label(__('competitions.fields.description'))->rows(4)->maxLength(5000)->columnSpanFull(),
                    Textarea::make('terms')->label(__('competitions.fields.terms'))->rows(5)->maxLength(5000)->columnSpanFull(),
                    Textarea::make('prizes')->label(__('competitions.fields.prizes'))->rows(3)->maxLength(2000)->columnSpanFull(),
                    Toggle::make('shuffle_questions')->label(__('competitions.fields.shuffle_questions')),
                    Toggle::make('shuffle_options')->label(__('competitions.fields.shuffle_options')),
                    Toggle::make('is_published')->label(__('content.is_published'))->helperText(__('competitions.fields.publish_hint')),
                ]),

                Tab::make(__('competitions.tabs.criteria'))->schema([
                    Repeater::make('criteria')
                        ->label(__('competitions.fields.criteria'))
                        ->helperText(__('competitions.fields.criteria_hint'))
                        ->columns(3)
                        ->maxItems(20)
                        ->reorderable()
                        ->defaultItems(0)
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')->label(__('competitions.fields.criterion_name'))->required()->maxLength(150),
                            TextInput::make('weight')->label(__('competitions.fields.weight'))->integer()->required()->minValue(1)->maxValue(100),
                            TextInput::make('max_score')->label(__('competitions.fields.max_score'))->integer()->required()->minValue(1)->maxValue(1000),
                        ]),
                ]),

                Tab::make(__('competitions.tabs.questions'))->schema([
                    // بعد أول مشاركة يُقفل البنك، فلا يُرسل الحقل ولا يُحفظ (SaveCompetition)
                    Repeater::make('questions')
                        ->label(__('competitions.fields.questions'))
                        ->disabled(fn (?Competition $record): bool => $record?->questionsLocked() ?? false)
                        ->helperText(fn (?Competition $record): ?string => $record?->questionsLocked() ? __('competitions.fields.questions_locked') : null)
                        ->columns(2)
                        ->maxItems(200)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => filled($state['prompt'] ?? null) ? mb_strimwidth((string) $state['prompt'], 0, 80, '…') : null)
                        ->schema([
                            Hidden::make('id'),
                            Select::make('type')->label(__('competitions.fields.type'))->options(QuestionType::class)->required()->live(),
                            Toggle::make('is_required')->label(__('competitions.fields.is_required'))->default(true)->inline(false),
                            Textarea::make('prompt')->label(__('competitions.fields.prompt'))->required()->rows(2)->maxLength(2000)->columnSpanFull(),
                            Repeater::make('options')
                                ->label(__('competitions.fields.options'))
                                ->visible(fn (Get $get): bool => self::type($get) === QuestionType::MultipleChoice)
                                ->columns(2)
                                ->minItems(2)
                                ->maxItems(10)
                                ->defaultItems(2)
                                ->schema([
                                    TextInput::make('label')->label(__('competitions.fields.option_label'))->required()->maxLength(300),
                                    Toggle::make('is_correct')->label(__('competitions.fields.is_correct'))->inline(false),
                                ])
                                ->columnSpanFull(),
                            TextInput::make('points')
                                ->label(__('competitions.fields.points'))
                                ->visible(fn (Get $get): bool => self::type($get) === QuestionType::MultipleChoice)
                                ->integer()
                                ->minValue(0)
                                ->maxValue(1000)
                                ->default(1),
                            TextInput::make('max_video_seconds')
                                ->label(__('competitions.fields.max_video_seconds'))
                                ->visible(fn (Get $get): bool => self::type($get) === QuestionType::Video)
                                ->required(fn (Get $get): bool => self::type($get) === QuestionType::Video)
                                ->integer()
                                ->minValue(1)
                                ->maxValue((int) config('competitions.video.max_seconds')),
                            TextInput::make('max_video_mb')
                                ->label(__('competitions.fields.max_video_mb'))
                                ->visible(fn (Get $get): bool => self::type($get) === QuestionType::Video)
                                ->required(fn (Get $get): bool => self::type($get) === QuestionType::Video)
                                ->integer()
                                ->minValue(1)
                                ->maxValue((int) config('competitions.video.max_mb')),
                        ]),
                ]),
            ]),
        ]);
    }

    /**
     * المعايير والأسئلة المحفوظة بشكل النموذج، لصفحة التعديل.
     *
     * @return array{criteria: list<array<string, mixed>>, questions: list<array<string, mixed>>}
     */
    public static function relationsState(Competition $competition): array
    {
        return [
            'criteria' => $competition->criteria->map(fn (CompetitionCriterion $criterion): array => $criterion->only(['id', 'name', 'weight', 'max_score']))->values()->all(),
            'questions' => $competition->questions->map(fn (CompetitionQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'is_required' => $question->is_required,
                'options' => array_map(fn (array $option): array => ['label' => $option['label'], 'is_correct' => $option['key'] === $question->correct_option], $question->optionList()),
                'points' => $question->points,
                'max_video_seconds' => $question->max_video_seconds,
                'max_video_mb' => $question->max_video_mb,
            ])->values()->all(),
        ];
    }

    private static function type(Get $get): ?QuestionType
    {
        $value = $get('type');

        return $value instanceof QuestionType ? $value : QuestionType::tryFrom((string) $value);
    }
}
