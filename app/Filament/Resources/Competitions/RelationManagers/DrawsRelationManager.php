<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\ApproveDrawVoid;
use App\Actions\Competitions\RejectDrawVoid;
use App\Actions\Competitions\RunDraw;
use App\Actions\Competitions\VoidDraw;
use App\Actions\Competitions\VoidTieDraw;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoidRequest;
use App\Models\User;
use App\Support\CompetitionAccess;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * سجل السحب الإلكتروني للمسابقة: يطّلع عليه من يطّلع على المسابقات، ويُنفّذ منه السحب لمن له
 * competitions.draw. والسجل ثابت: لا تعديل ولا حذف (CompetitionDrawPolicy ومشغّل قاعدة البيانات).
 */
class DrawsRelationManager extends RelationManager
{
    protected static string $relationship = 'draws';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.draws.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', CompetitionDraw::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('executed_at')->label(__('competitions.draws.executed_at'))->dateTime('Y-m-d H:i:s'),
            TextEntry::make('executor.name')->label(__('competitions.draws.executor')),
            TextEntry::make('entrants_count')->label(__('competitions.draws.entrants_count')),
            TextEntry::make('entrants_hash')->label(__('competitions.draws.entrants_hash'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('seed')->label(__('competitions.draws.seed'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('uuid')->label(__('competitions.draws.uuid'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('winners')->label(__('competitions.draws.winners'))->listWithLineBreaks()->bulleted(),
            TextEntry::make('void.reason')->label(__('competitions.draws.void_reason'))->visible(fn (CompetitionDraw $record): bool => $record->void !== null),
            TextEntry::make('void.approver.name')->label(__('competitions.draws.void_approver'))->visible(fn (CompetitionDraw $record): bool => $record->void?->approved_by !== null),
            TextEntry::make('voidRequest.reason')->label(__('competitions.draws.void_pending_label'))->visible(fn (CompetitionDraw $record): bool => $record->void === null && $record->openVoidRequest() !== null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // قائمة المشمولين (قد تبلغ عشرات الآلاف) لا تُقرأ في الجدول، بل في صفحة السحب وحدها
            ->modifyQueryUsing(fn ($query) => $query->with(['executor', 'void.voider', 'void.approver', 'voidRequest'])
                ->select(['id', 'uuid', 'competition_id', 'seed', 'entrants_hash', 'entrants_count', 'winners_count', 'winners', 'executed_by', 'executed_at', 'round_id', 'category_id', 'tie_score', 'gender']))
            ->columns([
                TextColumn::make('executed_at')->label(__('competitions.draws.executed_at'))->dateTime('Y-m-d H:i'),
                // سحب المتساوين في مسابقة الطلاب: مرحلته وفئته ودرجة التساوي (RunTieDraw)
                // وسحب الترتيب التراكمي بلا مرحلة، بجنسه (RunCumulativeTieDraw)
                TextColumn::make('round.name')->label(__('competitions.student.question_attributes.round_id'))
                    ->placeholder(fn (CompetitionDraw $record): string => $record->gender !== null ? __('competitions.student.admin.cumulative_label') : '—')
                    ->description(fn (CompetitionDraw $record): ?string => $record->round_id !== null || $record->gender !== null
                        ? implode(' · ', array_filter([$record->gender?->groupLabel(), $record->category->name ?? __('competitions.student.admin.all_categories'), __('competitions.student.admin.tie_score', ['score' => $record->tie_score])]))
                        : null)
                    ->visible(fn (): bool => (bool) $this->competition()->student_mode),
                TextColumn::make('executor.name')->label(__('competitions.draws.executor')),
                TextColumn::make('entrants_count')->label(__('competitions.draws.entrants_count'))->numeric(),
                TextColumn::make('winners_count')->label(__('competitions.draws.winners_count'))->numeric(),
                TextColumn::make('entrants_hash')->label(__('competitions.draws.entrants_hash'))->limit(16),
                TextColumn::make('status')->label(__('competitions.draws.status'))->badge()
                    ->state(fn (CompetitionDraw $record): string => match (true) {
                        $record->void !== null => __('competitions.draws.voided_label'),
                        $record->openVoidRequest() !== null => __('competitions.draws.void_pending_label'),
                        default => __('competitions.draws.active'),
                    })
                    ->color(fn (CompetitionDraw $record): string => match (true) {
                        $record->void !== null => 'gray',
                        $record->openVoidRequest() !== null => 'warning',
                        default => 'success',
                    })
                    ->description(fn (CompetitionDraw $record): ?string => $record->void->reason ?? $record->openVoidRequest()?->reason),
            ])
            ->headerActions([
                Action::make('draw')
                    ->label(__('competitions.draws.run'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    // مسابقة الطلاب سحبها بين المتساوين من نتائجها (ResultsRelationManager)
                    ->visible(fn (): bool => (auth()->user()?->can('competitions.draw') ?? false) && ! $this->competition()->student_mode
                        && $this->competition()->draws()->whereDoesntHave('void')->doesntExist())
                    ->modalDescription(__('competitions.draws.run_confirm'))
                    ->schema([
                        TextInput::make('winners_count')->label(__('competitions.draws.winners_count'))->integer()->minValue(1)->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $competition = $this->competition();

                        try {
                            app(RunDraw::class)->handle($user, $competition, (int) $data['winners_count']);
                            Notification::make()->success()->title(__('competitions.draws.done'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // للمشرف العام وحده، بسبب إلزامي، والسحب الملغى يبقى ظاهراً (VoidDraw)
                Action::make('void')
                    ->label(__('competitions.draws.void'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (CompetitionDraw $record): bool => $record->round_id === null && ! $this->competition()->student_mode && $record->void === null && $record->openVoidRequest() === null
                        && (auth()->user()?->can('void', $record) ?? false))
                    // بعد الإلغاء الأول في المسابقة يصير الإلغاء طلباً يعتمده مشرف عام آخر (VoidDraw)
                    ->modalDescription(fn (): string => $this->competition()->draws()->whereHas('void')->exists()
                        ? __('competitions.draws.void_request_confirm', ['days' => CompetitionDrawVoidRequest::LAPSE_DAYS])
                        : __('competitions.draws.void_confirm'))
                    ->schema([
                        Textarea::make('reason')->label(__('competitions.draws.void_reason'))->required()->minLength(10)->maxLength(500),
                    ])
                    ->action(function (CompetitionDraw $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            $result = app(VoidDraw::class)->handle($user, $record, (string) $data['reason']);
                            Notification::make()->success()->title($result instanceof CompetitionDrawVoidRequest
                                ? __('competitions.draws.void_requested')
                                : __('competitions.draws.voided'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                // سحب المتساوين في مرحلة: يلغيه من يدير النتائج بسبب مسجّل، مرة لكل مرحلة وفئة (VoidTieDraw)
                Action::make('void_tie')
                    ->label(__('competitions.draws.void'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (CompetitionDraw $record): bool => ($record->round_id !== null || $record->gender !== null) && $record->void === null
                        && CompetitionAccess::managesResults(auth()->user() instanceof User ? auth()->user() : null, $this->competition()))
                    ->modalDescription(__('competitions.student.tie_void_confirm'))
                    ->schema([
                        Textarea::make('reason')->label(__('competitions.draws.void_reason'))->required()->minLength(10)->maxLength(500),
                    ])
                    ->action(function (CompetitionDraw $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            app(VoidTieDraw::class)->handle($user, $record, (string) $data['reason']);
                            Notification::make()->success()->title(__('competitions.draws.voided'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                // اعتماد طلب الإلغاء: لمشرف عام غير طالبه (CompetitionDrawPolicy::approveVoid)
                Action::make('approve_void')
                    ->label(__('competitions.draws.approve_void'))
                    ->icon('heroicon-o-check-badge')
                    ->color('danger')
                    ->visible(fn (CompetitionDraw $record): bool => $record->void === null && (auth()->user()?->can('approveVoid', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalDescription(__('competitions.draws.approve_void_confirm'))
                    ->action(function (CompetitionDraw $record): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            app(ApproveDrawVoid::class)->handle($user, $record);
                            Notification::make()->success()->title(__('competitions.draws.voided'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                // رفض طلب الإلغاء بسبب إلزامي: لمن يعتمده (CompetitionDrawPolicy::rejectVoid)
                Action::make('reject_void')
                    ->label(__('competitions.draws.reject_void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (CompetitionDraw $record): bool => $record->void === null && (auth()->user()?->can('rejectVoid', $record) ?? false))
                    ->modalDescription(__('competitions.draws.reject_void_confirm'))
                    ->schema([
                        Textarea::make('reason')->label(__('competitions.draws.reject_reason'))->required()->minLength(10)->maxLength(500),
                    ])
                    ->action(function (CompetitionDraw $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            app(RejectDrawVoid::class)->handle($user, $record, (string) $data['reason']);
                            Notification::make()->success()->title(__('competitions.draws.void_rejected'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
            ]);
    }

    private function competition(): Competition
    {
        /** @var Competition */
        return $this->getOwnerRecord();
    }
}
