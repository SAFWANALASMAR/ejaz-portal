<?php

namespace App\Filament\Resources\Competitions\Schemas;

use App\Actions\Competitions\SaveCompetition;
use App\Enums\QuestionMedia;
use App\Enums\QuestionType;
use App\Enums\RankingScope;
use App\Enums\RoleName;
use App\Enums\WorkType;
use App\Filament\Forms\ContentFields;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionQuestion;
use App\Models\User;
use App\Support\CompetitionMedia;
use App\Support\CompetitionPage;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
                        ->nestedRecursiveRules(['string', 'max:60'])
                        // مسابقة الطلاب فئاتها من القائمة في تبويبها (الفئات المفتوحة في هذا الإطلاق)
                        ->hidden(fn (Get $get): bool => (bool) $get('student_mode')),
                    DateTimePicker::make('opens_at')->label(__('competitions.fields.opens_at'))->helperText(__('competitions.fields.dates_hint'))->seconds(false)->required()
                        // معطّل ويُرسل كما هو، فيُحفظ غيره، والإجراء يرفض تغييره (SaveCompetition)
                        ->disabled(fn (?Competition $record): bool => $record?->scheduleLocked() ?? false)->dehydrated(),
                    DateTimePicker::make('closes_at')->label(__('competitions.fields.closes_at'))->seconds(false)->required()->after('opens_at')
                        ->disabled(fn (?Competition $record): bool => $record?->scheduleLocked() ?? false)->dehydrated(),
                    Textarea::make('description')->label(__('competitions.fields.description'))->rows(4)->maxLength(5000)->columnSpanFull(),
                    Textarea::make('terms')->label(__('competitions.fields.terms'))->rows(5)->maxLength(5000)->columnSpanFull(),
                    Textarea::make('prizes')->label(__('competitions.fields.prizes'))->rows(3)->maxLength(2000)->columnSpanFull(),
                    Toggle::make('shuffle_questions')->label(__('competitions.fields.shuffle_questions')),
                    Toggle::make('shuffle_options')->label(__('competitions.fields.shuffle_options')),
                    Toggle::make('is_published')->label(__('content.is_published'))->helperText(__('competitions.fields.publish_hint'))
                        ->disabled(fn (?Competition $record): bool => $record?->scheduleLocked() ?? false)->dehydrated(),
                ]),

                // الاختبار الإلكتروني ورفع الأعمال (docs/plans/competition-exam.md). والفحص كله في SaveCompetition
                Tab::make(__('competitions.exam_admin.tab'))->schema([
                    // مسابقة الطلاب (docs/plans/student-competition.md): مراحلها وحقولها وبنكها ونتائجها في صفحة
                    // المسابقة. ولا يتغير النوع بعد أول تسجيل أو مرحلة (SaveCompetition)
                    Toggle::make('student_mode')->label(__('competitions.student.admin.student_mode'))->helperText(__('competitions.student.admin.student_mode_hint'))->live()
                        ->hidden(fn (Get $get): bool => (bool) $get('exam_mode'))
                        ->disabled(fn (?Competition $record): bool => $record !== null && ($record->submissions()->withTrashed()->exists() || $record->rounds()->exists()))->dehydrated(),
                    CheckboxList::make('open_categories')
                        ->label(__('competitions.student.admin.open_categories'))
                        ->helperText(__('competitions.student.admin.open_categories_hint'))
                        ->options(fn (): array => CompetitionCategory::query()->ordered()->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => (bool) $get('student_mode'))
                        ->columns(2),
                    // صورة خلفية الشهادة (A4 أفقي) في القرص الخاص، قابلة للتبديل (GenerateCompetitionCertificate)
                    FileUpload::make('certificate_background')
                        ->label(__('competitions.student.admin.certificate_background'))
                        ->helperText(__('competitions.student.admin.certificate_background_hint'))
                        ->visible(fn (Get $get): bool => (bool) $get('student_mode'))
                        ->disk((string) config('filesystems.private_disk', 'local'))
                        ->directory(SaveCompetition::CERTIFICATE_DIRECTORY)
                        ->visibility('private')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->maxSize((int) config('competitions.student.certificate_background_max_kb'))
                        ->previewable(false),
                    // لا يتغير بعد أول مشاركة: معطّل ويُرسل كما هو، والإجراء يرفض تغييره (SaveCompetition)
                    Toggle::make('exam_mode')
                        ->hidden(fn (Get $get): bool => (bool) $get('student_mode'))->label(__('competitions.exam_admin.exam_mode'))->helperText(__('competitions.exam_admin.exam_mode_hint'))->live()
                        ->disabled(fn (?Competition $record): bool => $record?->submissions()->withTrashed()->exists() ?? false)->dehydrated(),
                    // في القرص الخاص، ويُقدَّم للمشارك في مرحلته وحدها (ExamController)
                    FileUpload::make('intro_video_path')
                        ->label(__('competitions.exam_admin.intro_video'))
                        ->helperText(__('competitions.exam_admin.intro_video_hint', ['max' => (int) config('competitions.exam.media_max_mb')]))
                        ->visible(fn (Get $get): bool => (bool) $get('exam_mode'))
                        ->disk(CompetitionMedia::disk())
                        ->directory(CompetitionMedia::DIRECTORY)
                        ->visibility('private')
                        ->acceptedFileTypes(QuestionMedia::Video->mimeTypes())
                        ->maxSize((int) config('competitions.exam.media_max_mb') * 1024)
                        ->previewable(false),
                    CheckboxList::make('work_types')
                        ->label(__('competitions.exam_admin.work_types'))
                        ->helperText(__('competitions.exam_admin.work_types_hint'))
                        ->options(WorkType::class)
                        ->hidden(fn (Get $get): bool => (bool) $get('exam_mode') || (bool) $get('student_mode'))
                        ->columns(3),
                ]),

                // مسابقة الطلاب: نصوص الصفحة ودليل الدخول (مطوية «البراهين العلمية»). والفحص والتحويل في SaveCompetition
                Tab::make(__('competitions.student.admin.page_tab'))->visible(fn (Get $get): bool => (bool) $get('student_mode'))->schema([
                    TextInput::make('page_content.'.CompetitionPage::PARTNER)->label(__('competitions.student.page.partner'))->maxLength(200)
                        ->helperText(__('competitions.student.admin.page_hint')),
                    ...array_map(fn (string $key): Textarea => Textarea::make('page_content.'.$key)->label(__('competitions.student.page.'.$key))
                        ->rows(3)->maxLength(CompetitionPage::PARAGRAPH_MAX), CompetitionPage::PARAGRAPHS),
                    ...array_map(fn (string $key): Textarea => self::listField($key), CompetitionPage::LISTS),
                    ...array_map(fn (string $key): Textarea => self::listField($key)->helperText(__('competitions.student.admin.guide_hint').' '.__('competitions.student.admin.list_hint')), CompetitionPage::GUIDE),
                ]),

                Tab::make(__('competitions.student.admin.prizes_tab'))->visible(fn (Get $get): bool => (bool) $get('student_mode'))->schema([
                    Select::make('ranking_scope')->label(__('competitions.student.admin.ranking_scope'))->helperText(__('competitions.student.admin.ranking_scope_hint'))
                        ->options(RankingScope::class)->default(RankingScope::All->value)->selectablePlaceholder(false)
                        ->disabled(fn (?Competition $record): bool => $record?->cumulativeLocked() ?? false)->dehydrated(),
                    Repeater::make('prize_table')
                        ->label(__('competitions.student.admin.prize_table'))
                        ->helperText(__('competitions.student.admin.prize_table_hint'))
                        ->columns(2)
                        ->maxItems(50)
                        ->defaultItems(0)
                        ->reorderable()
                        ->itemNumbers()
                        ->disabled(fn (?Competition $record): bool => $record?->cumulativeLocked() ?? false)->dehydrated()
                        ->schema([
                            TextInput::make('male')->label(__('competitions.student.admin.prize_male'))->integer()->minValue(0)->maxValue(10000000)->required(),
                            TextInput::make('female')->label(__('competitions.student.admin.prize_female'))->integer()->minValue(0)->maxValue(10000000)->required(),
                        ]),
                ]),

                Tab::make(__('competitions.tabs.criteria'))->hidden(fn (Get $get): bool => (bool) $get('student_mode'))->schema([
                    // بعد أول إسناد تُقفل المعايير، فلا يُرسل الحقل ولا يُحفظ (SaveCompetition)
                    Repeater::make('criteria')
                        ->label(__('competitions.fields.criteria'))
                        ->disabled(fn (?Competition $record): bool => $record?->criteriaLocked() ?? false)
                        ->helperText(fn (?Competition $record): string => $record?->criteriaLocked() ? __('competitions.fields.criteria_locked') : __('competitions.fields.criteria_hint'))
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

                Tab::make(__('competitions.tabs.reviewers'))->hidden(fn (Get $get): bool => (bool) $get('student_mode'))->schema([
                    Select::make('reviewers')
                        ->label(__('competitions.fields.reviewers'))
                        ->helperText(__('competitions.fields.reviewers_hint'))
                        ->multiple()
                        // النشطون بدور المحكّم، ومعهم المرتبطون بالمسابقة ولو عُطّلت حساباتهم
                        ->options(fn (?Competition $record): array => User::query()
                            ->where(fn ($query) => $query->where('is_active', true)->role(RoleName::Reviewer->value))
                            ->when($record, fn ($query) => $query->orWhereIn('id', $record->reviewers()->select('users.id')))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()),
                ]),

                // بنك مسابقة الطلاب في مراحلها (صفحة المسابقة)، فتبويب البنك هنا لغيرها
                Tab::make(__('competitions.tabs.questions'))->hidden(fn (Get $get): bool => (bool) $get('student_mode'))->schema([
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
                            Select::make('type')->label(__('competitions.fields.type'))->options(collect(QuestionType::classic())->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->getLabel()])->all())->required()->live(),
                            Toggle::make('is_required')->label(__('competitions.fields.is_required'))->default(true)->inline(false),
                            Textarea::make('context_text')->label(__('competitions.exam_admin.context_text'))->rows(2)->maxLength(3000)->columnSpanFull()
                                ->visible(fn (Get $get): bool => (bool) $get('../../exam_mode')),
                            Textarea::make('prompt')->label(__('competitions.fields.prompt'))->required()->rows(2)->maxLength(2000)->columnSpanFull(),
                            Select::make('media_type')->label(__('competitions.exam_admin.media_type'))->options(QuestionMedia::class)->live()
                                ->visible(fn (Get $get): bool => (bool) $get('../../exam_mode')),
                            FileUpload::make('media_path')
                                ->label(__('competitions.exam_admin.media_file'))
                                ->helperText(__('competitions.exam_admin.media_file_hint', ['video' => (int) config('competitions.exam.media_max_mb')]))
                                ->visible(fn (Get $get): bool => (bool) $get('../../exam_mode') && filled($get('media_type')))
                                ->required(fn (Get $get): bool => (bool) $get('../../exam_mode') && filled($get('media_type')))
                                ->disk(CompetitionMedia::disk())
                                ->directory(CompetitionMedia::DIRECTORY)
                                ->visibility('private')
                                ->acceptedFileTypes(array_merge(...array_map(fn (QuestionMedia $media): array => $media->mimeTypes(), QuestionMedia::cases())))
                                ->maxSize(fn (Get $get): int => (QuestionMedia::tryFrom((string) $get('media_type')) ?? QuestionMedia::Video)->maxKb())
                                ->previewable(false),
                            Repeater::make('options')
                                ->label(__('competitions.fields.options'))
                                ->visible(fn (Get $get): bool => self::type($get) === QuestionType::MultipleChoice)
                                ->columns(2)
                                ->minItems(2)
                                ->maxItems(10)
                                ->defaultItems(2)
                                ->schema([
                                    TextInput::make('label')->label(__('competitions.fields.option_label'))->required()->maxLength(300),
                                    // الإجابة الصحيحة لمن يدير المسابقة وحده، لا لمن يطّلع (عضو المجلس في صفحة العرض)
                                    Toggle::make('is_correct')->label(__('competitions.fields.is_correct'))->inline(false)
                                        ->visible(fn (): bool => auth()->user()?->can('competitions.update') ?? false),
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
     * @return array{reviewers: list<int>, criteria: list<array<string, mixed>>, questions: list<array<string, mixed>>}
     */
    public static function relationsState(Competition $competition): array
    {
        $manages = auth()->user()?->can('competitions.update') ?? false;

        return [
            'open_categories' => $competition->openCategories()->pluck('competition_categories.id')->all(),
            'reviewers' => $competition->reviewers()->pluck('users.id')->all(),
            'criteria' => $competition->criteria->map(fn (CompetitionCriterion $criterion): array => $criterion->only(['id', 'name', 'weight', 'max_score']))->values()->all(),
            'questions' => $competition->questions->map(fn (CompetitionQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'is_required' => $question->is_required,
                'options' => array_map(fn (array $option): array => ['label' => $option['label'], 'is_correct' => $manages && $option['key'] === $question->correct_option], $question->optionList()),
                'points' => $question->points,
                'max_video_seconds' => $question->max_video_seconds,
                'max_video_mb' => $question->max_video_mb,
                'context_text' => $question->context_text,
                'media_type' => $question->media_type?->value,
                'media_path' => $question->media_path,
            ])->values()->all(),
        ];
    }

    /**
     * قائمة بنود من نصوص الصفحة في حقل نص: سطر لكل بند، وتُخزَّن مصفوفة (SaveCompetition).
     */
    private static function listField(string $key): Textarea
    {
        return Textarea::make('page_content.'.$key)
            ->label(__('competitions.student.page.'.$key))
            ->helperText(__('competitions.student.admin.list_hint'))
            ->rows(4)
            ->formatStateUsing(fn (mixed $state): ?string => is_array($state) ? implode("\n", CompetitionPage::items($state)) : (is_string($state) ? $state : null));
    }

    private static function type(Get $get): ?QuestionType
    {
        $value = $get('type');

        return $value instanceof QuestionType ? $value : QuestionType::tryFrom((string) $value);
    }
}
