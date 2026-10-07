<?php

namespace App\Filament\Resources\Surveys\Schemas;

use App\Enums\SurveyAudience;
use App\Enums\SurveyQuestionType;
use App\Models\Competition;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج الاستبيان بتبويبين: البيانات والأسئلة. والأسئلة تُقفل بعد أول إجابة، ويُعاد فحص كل شيء في
 * SaveSurvey.
 */
class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('surveys.tabs.details'))->columns(2)->schema([
                    TextInput::make('title')->label(__('surveys.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                    Select::make('audience')->label(__('surveys.fields.audience'))->options(SurveyAudience::class)->required(),
                    Select::make('competition_id')
                        ->label(__('surveys.fields.competition'))
                        ->helperText(__('surveys.fields.competition_hint'))
                        ->options(fn (): array => Competition::query()->orderByDesc('opens_at')->pluck('title', 'id')->all())
                        ->searchable(),
                    Textarea::make('description')->label(__('surveys.fields.description'))->rows(3)->maxLength(2000)->columnSpanFull(),
                    DateTimePicker::make('opens_at')->label(__('surveys.fields.opens_at'))->seconds(false),
                    DateTimePicker::make('closes_at')->label(__('surveys.fields.closes_at'))->seconds(false)->after('opens_at'),
                    Toggle::make('allow_guests')->label(__('surveys.fields.allow_guests'))->helperText(__('surveys.fields.allow_guests_hint'))->columnSpanFull(),
                    Toggle::make('is_published')->label(__('content.is_published')),
                    TextEntry::make('link')
                        ->label(__('surveys.fields.link'))
                        ->state(fn (?Survey $record): ?string => $record?->exists ? route('surveys.show', $record->token) : null)
                        ->copyable()
                        ->visible(fn (?Survey $record): bool => $record !== null && $record->exists)
                        ->extraAttributes(['dir' => 'ltr'])
                        ->columnSpanFull(),
                ]),

                Tab::make(__('surveys.tabs.questions'))->schema([
                    Repeater::make('questions')
                        ->label(__('surveys.fields.questions'))
                        ->disabled(fn (?Survey $record): bool => $record?->exists && $record->responses()->exists())
                        ->helperText(fn (?Survey $record): ?string => $record?->exists && $record->responses()->exists() ? __('surveys.fields.questions_locked') : null)
                        ->columns(2)
                        ->maxItems(50)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->schema([
                            Hidden::make('id'),
                            Select::make('type')->label(__('surveys.fields.type'))->options(SurveyQuestionType::class)->required()->live(),
                            Toggle::make('is_required')->label(__('surveys.fields.is_required'))->default(true)->inline(false),
                            Textarea::make('prompt')->label(__('surveys.fields.prompt'))->required()->rows(2)->maxLength(1000)->columnSpanFull(),
                            Repeater::make('options')
                                ->label(__('surveys.fields.options'))
                                ->visible(fn (Get $get): bool => self::type($get) === SurveyQuestionType::Choice)
                                ->minItems(2)
                                ->maxItems(10)
                                ->defaultItems(2)
                                ->schema([TextInput::make('label')->label(__('surveys.fields.option_label'))->required()->maxLength(200)])
                                ->columnSpanFull(),
                        ]),
                ]),
            ]),
        ]);
    }

    /**
     * @return array{competition_id: int|null, questions: list<array<string, mixed>>}
     */
    public static function relationsState(Survey $survey): array
    {
        return [
            'competition_id' => $survey->subject_type === (new Competition)->getMorphClass() ? (int) $survey->subject_id : null,
            'questions' => $survey->questions->map(fn (SurveyQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'is_required' => $question->is_required,
                'options' => array_map(fn (string $label): array => ['label' => $label], $question->optionList()),
            ])->values()->all(),
        ];
    }

    private static function type(Get $get): ?SurveyQuestionType
    {
        $value = $get('type');

        return $value instanceof SurveyQuestionType ? $value : SurveyQuestionType::tryFrom((string) $value);
    }
}
