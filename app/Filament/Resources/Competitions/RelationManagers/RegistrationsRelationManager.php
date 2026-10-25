<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\RevealStudentIdentity;
use App\Filament\Resources\Competitions\RelationManagers\Concerns\RunsStudentCompetitionActions;
use App\Models\CompetitionField;
use App\Models\CompetitionRegion;
use App\Models\CompetitionSubmission;
use App\Support\SaudiPhone;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * المسجلون في مسابقة الطلاب ببياناتهم (لمن له submissions.view): رقم الهوية وجوال ولي الأمر مخفيان إلا آخر
 * أربعة أرقام، والاطلاع الكامل لمن يملكه وحده ويُسجَّل (RevealStudentIdentity). والحقول المخصصة بقيمها.
 */
class RegistrationsRelationManager extends RelationManager
{
    use RunsStudentCompetitionActions;

    protected static string $relationship = 'submissions';

    /**
     * رقم الهوية وجوال ولي الأمر في نافذة الاطلاع وحدها: يُقرأ ويُسجَّل عند فتحها، ويبقى لمن فتحها حتى يفتح
     * نافذة أخرى. وتُرسم النافذة في طلب لاحق، فالخاصية عامة (تصل إلى متصفح صاحب الصلاحية وحده، وهو ما يراه).
     *
     * @var array{national_id: string|null, guardian_phone: string|null}|null
     */
    public ?array $revealed = null;

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
