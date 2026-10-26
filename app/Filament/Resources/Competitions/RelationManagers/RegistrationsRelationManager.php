<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\CorrectStudentRegistration;
use App\Actions\Competitions\RevealStudentIdentity;
use App\Enums\StudentGender;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionField;
use App\Models\CompetitionRegion;
use App\Models\CompetitionSubmission;
use App\Support\SaudiPhone;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * المسجلون في مسابقة الطلاب ببياناتهم (لمن له submissions.view): رقم الهوية وجوال ولي الأمر مخفيان إلا آخر
 * أربعة أرقام، والاطلاع الكامل لمن يملكه وحده ويُسجَّل (RevealStudentIdentity). والحقول المخصصة بقيمها.
 */
class RegistrationsRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'submissions';

    /**
     * رقم الهوية وجوال ولي الأمر ما دامت نافذة الاطلاع مفتوحة وحدها: يُقرأ ويُسجَّل عند فتحها، ويُمسح عند إغلاقها
     * (unmountAction)، ومقفل فلا يكتبه المتصفح (مراجعة المشرف). وتُرسم النافذة في طلب بعد فتحها، فلا تكفي خاصية محمية.
     *
     * @var array{national_id: string|null, guardian_phone: string|null}|null
     */
    #[Locked]
    public ?array $revealed = null;

    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        $this->revealed = null;

        parent::unmountAction($cancelParentActions);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.student.admin.registrations');
    }

    protected static function canViewStudentPart(): bool
    {
        return auth()->user()?->can('viewAny', CompetitionSubmission::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $fields = $this->competition()->fields()->get();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('registered_at')->with(['studentCategory', 'studentRegion'])->withCount('attempts'))
            ->defaultSort('registered_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('competitions.entry.reference'))->searchable()->copyable(),
                TextColumn::make('full_name')->label(__('competitions.student.attributes.full_name'))->searchable()->wrap(),
                TextColumn::make('national_id')->label(__('competitions.student.attributes.national_id'))
                    ->state(fn (CompetitionSubmission $record): ?string => CompetitionSubmission::mask($record->national_id))->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('gender')->label(__('competitions.student.attributes.gender'))->badge()->placeholder('—'),
                TextColumn::make('studentCategory.name')->label(__('competitions.student.attributes.category_id')),
                TextColumn::make('grade')->label(__('competitions.student.attributes.grade'))->placeholder('—'),
                TextColumn::make('studentRegion.name')->label(__('competitions.student.attributes.region_id')),
                TextColumn::make('guardian_phone')->label(__('competitions.student.attributes.guardian_phone'))->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (CompetitionSubmission $record): ?string => CompetitionSubmission::mask($record->guardian_phone))->placeholder('—')->extraAttributes(['dir' => 'ltr']),
                ...$fields->map(fn (CompetitionField $field): TextColumn => TextColumn::make('extra.'.$field->id)->label($field->label)->placeholder('—')->toggleable())->all(),
                TextColumn::make('attempts_count')->label(__('competitions.student.admin.attempts'))->numeric(),
                TextColumn::make('registered_at')->label(__('competitions.student.admin.registered_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('gender')->label(__('competitions.student.attributes.gender'))->options(StudentGender::class),
                SelectFilter::make('category_id')->label(__('competitions.student.attributes.category_id'))->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all()),
                SelectFilter::make('region_id')->label(__('competitions.student.attributes.region_id'))->options(fn (): array => CompetitionRegion::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                SelectFilter::make('grade')->label(__('competitions.student.attributes.grade'))->options(fn (): array => $this->gradeOptions()),
            ])
            ->recordActions([
                Action::make('reveal')
                    ->label(__('competitions.student.admin.reveal'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('viewNationalId', $this->competition()) ?? false)
                    ->modalHeading(__('competitions.student.admin.reveal_heading'))
                    ->modalDescription(__('competitions.student.admin.reveal_logged'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('competitions.student.admin.close'))
                    // الرقم يُقرأ ويُسجَّل مرة عند فتح النافذة (mountUsing)، لا في الجدول ولا عند كل رسم
                    ->mountUsing(function (CompetitionSubmission $record): void {
                        $this->revealed = $this->attempt(fn (): array => app(RevealStudentIdentity::class)->handle($this->actor(), $record));
                    })
                    ->schema([
                        TextEntry::make('revealed_national_id')->label(__('competitions.student.attributes.national_id'))
                            ->state(fn (): ?string => $this->revealed['national_id'] ?? null)->copyable()->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make('revealed_guardian_phone')->label(__('competitions.student.attributes.guardian_phone'))
                            ->state(fn (): string => filled($this->revealed['guardian_phone'] ?? null) ? SaudiPhone::display((string) $this->revealed['guardian_phone']) : '—')
                            ->extraAttributes(['dir' => 'ltr']),
                    ]),
                // تصحيح التسجيل: للمدير التنفيذي والمسؤول التقني، بسبب مكتوب مسجّل (CorrectStudentRegistration)
                Action::make('correct')
                    ->label(__('competitions.student.admin.correct'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (): bool => $this->allows('competition.correct_registrations'))
                    ->fillForm(fn (CompetitionSubmission $record): array => [...$record->only(['full_name', 'category_id', 'grade', 'region_id']), 'gender' => $record->gender?->value])
                    ->schema([
                        TextInput::make('full_name')->label(__('competitions.student.attributes.full_name'))->required()->maxLength(150),
                        Select::make('category_id')->label(__('competitions.student.attributes.category_id'))->required()
                            ->options(fn (): array => $this->competition()->openCategories()->pluck('name', 'competition_categories.id')->all()),
                        Select::make('grade')->label(__('competitions.student.attributes.grade'))->options(fn (): array => $this->gradeOptions()),
                        Select::make('region_id')->label(__('competitions.student.attributes.region_id'))->required()
                            ->options(fn (): array => CompetitionRegion::query()->active()->pluck('name', 'id')->all()),
                        Select::make('gender')->label(__('competitions.student.attributes.gender'))->required()->options(StudentGender::class),
                        Textarea::make('reason')->label(__('competitions.student.admin.correction_reason'))->required()->minLength(10)->maxLength(500),
                    ])
                    ->action(fn (CompetitionSubmission $record, array $data, Action $action) => $this->attempt(function () use ($record, $data): void {
                        app(CorrectStudentRegistration::class)->edit($this->actor(), $record, $data);
                        Notification::make()->success()->title(__('competitions.student.admin.corrected'))->send();
                    }, fn () => $action->halt())),
                Action::make('cancel_registration')
                    ->label(__('competitions.student.admin.cancel_registration'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => $this->allows('competition.correct_registrations'))
                    ->modalDescription(__('competitions.student.admin.cancel_registration_confirm'))
                    ->schema([
                        Textarea::make('reason')->label(__('competitions.student.admin.correction_reason'))->required()->minLength(10)->maxLength(500),
                    ])
                    ->action(fn (CompetitionSubmission $record, array $data, Action $action) => $this->attempt(function () use ($record, $data): void {
                        app(CorrectStudentRegistration::class)->cancel($this->actor(), $record, (string) $data['reason']);
                        Notification::make()->success()->title(__('competitions.student.admin.registration_cancelled'))->send();
                    }, fn () => $action->halt())),
            ]);
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
