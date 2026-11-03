<?php

namespace App\Filament\Resources\Meetings;

use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\Resources\Meetings\RelationManagers\AgendaItemsRelationManager;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Models\Meeting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * اجتماعات الجمعية العمومية. يديرها السكرتير، ويطّلع عليها المجلس ويعتمد المحاضر (meetings.* وminutes.* —
 * MeetingPolicy). والمجدول وحده يُعدَّل، وما بعده من صفحة الاجتماع بإجراءاتها.
 */
class MeetingResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assembly.meeting';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = Meeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MeetingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('title')->label(__('assembly.fields.title'))->columnSpanFull(),
                TextEntry::make('type')->label(__('assembly.fields.type'))->badge(),
                TextEntry::make('body')->label(__('registers.fields.body'))->badge(),
                TextEntry::make('committee.name')->label(__('registers.fields.committee'))->visible(fn (Meeting $record): bool => $record->body === MeetingBody::Committee),
                TextEntry::make('status')->label(__('assembly.fields.status'))->badge(),
                TextEntry::make('quorum_percent')->label(__('assembly.fields.quorum_percent'))->placeholder('—'),
                TextEntry::make('starts_at')->label(__('assembly.fields.starts_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('ends_at')->label(__('assembly.fields.ends_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('location')->label(__('assembly.fields.location'))->placeholder('—'),
                IconEntry::make('is_online')->label(__('assembly.fields.is_online'))->boolean(),
                TextEntry::make('online_join_url')
                    ->label(__('assembly.fields.online_link'))
                    ->placeholder(__('assembly.notices.online_pending'))
                    ->visible(fn (Meeting $record): bool => $record->is_online)
                    ->copyable(),
                // التعذر للمسؤول بسببه ووقته، وإعادة المحاولة في إجراءات الصفحة (MeetingActions::retryOnline)
                TextEntry::make('online_sync_error')
                    ->label(__('assembly.fields.online_sync'))
                    ->badge()
                    ->color('danger')
                    ->helperText(fn (Meeting $record): ?string => $record->online_sync_error === null ? null : __('assembly.online_failure_hints.'.$record->online_sync_error->value))
                    ->hint(fn (Meeting $record): ?string => $record->online_sync_failed_at?->format('Y-m-d H:i'))
                    ->visible(fn (Meeting $record): bool => $record->online_sync_error !== null)
                    ->columnSpanFull(),
                TextEntry::make('attendance')
                    ->label(__('assembly.meetings.attendance'))
                    ->state(fn (Meeting $record): string => self::attendance($record))
                    ->visible(fn (Meeting $record): bool => $record->members_count !== null),
                TextEntry::make('minutes')
                    ->label(__('assembly.fields.minutes'))
                    ->state(fn (Meeting $record): string => self::minutesStatus($record))
                    ->badge(),
            ]),
            Section::make(__('assembly.fields.agenda'))->columnSpanFull()->schema([
                TextEntry::make('agenda')->hiddenLabel()->placeholder('—')->extraAttributes(['class' => 'whitespace-pre-line']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('assembly.fields.title'))->searchable()->wrap(),
                TextColumn::make('type')->label(__('assembly.fields.type'))->badge(),
                TextColumn::make('body')->label(__('registers.fields.body'))->badge()->description(fn (Meeting $record): ?string => $record->committee?->name),
                TextColumn::make('starts_at')->label(__('assembly.fields.starts_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('status')->label(__('assembly.fields.status'))->badge(),
                IconColumn::make('is_online')->label(__('assembly.fields.is_online'))->boolean(),
                TextColumn::make('online_sync')->label(__('assembly.fields.online_sync'))
                    ->state(fn (Meeting $record): ?string => self::onlineStatus($record))
                    ->formatStateUsing(fn (string $state): string => __('assembly.online_statuses.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'linked' => 'success',
                        'manual' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (Meeting $record): ?string => $record->online_sync_error?->getLabel())
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('minutes')->label(__('assembly.fields.minutes'))->state(fn (Meeting $record): string => self::minutesStatus($record))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('assembly.fields.status'))->options(MeetingStatus::class),
                SelectFilter::make('type')->label(__('assembly.fields.type'))->options(MeetingType::class),
                SelectFilter::make('body')->label(__('registers.fields.body'))->options(MeetingBody::class),
                Filter::make('online_failed')->label(__('assembly.filters.online_failed'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('online_sync_error')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function minutesStatus(Meeting $meeting): string
    {
        return __('assembly.minutes_statuses.'.match (true) {
            $meeting->hasApprovedMinutes() => 'approved',
            $meeting->hasDraftMinutes() => 'draft',
            default => 'none',
        });
    }

    /**
     * حال تيمز في القائمة: مرتبط، أو قيد المزامنة (إنشاءً أو إلغاءً)، أو متعذر. ولا شيء لاجتماع في القاعة.
     */
    private static function onlineStatus(Meeting $meeting): ?string
    {
        return match (true) {
            $meeting->online_sync_error !== null => 'failed',
            $meeting->needsOnlineSync() => 'pending',
            $meeting->online_join_manual => 'manual',
            $meeting->online_meeting_id !== null => 'linked',
            default => null,
        };
    }

    private static function attendance(Meeting $meeting): string
    {
        $present = $meeting->attendees()->count();
        $text = __('assembly.meetings.attendance_count', ['present' => number_format($present), 'total' => number_format((int) $meeting->members_count)]);
        $quorum = $meeting->quorumMet($present);

        return $quorum === null ? $text : $text.' — '.__($quorum ? 'assembly.meetings.quorum_met' : 'assembly.meetings.quorum_not_met');
    }

    /**
     * ما يطّلع عليه الحساب وحده: كل جهة لأعضائها، والكل لمن يملك meetings.view_all (requirements/13 §2).
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->with('committee')->scopes(['visibleTo' => [$user]]);
    }

    public static function getRelations(): array
    {
        return [
            AgendaItemsRelationManager::class,
        ];
    }

    /**
     * المحذوفة حذفاً ناعماً تظهر بمرشّحها، لتُستعاد.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeetings::route('/'),
            'create' => CreateMeeting::route('/create'),
            'view' => ViewMeeting::route('/{record}'),
            'edit' => EditMeeting::route('/{record}/edit'),
        ];
    }
}
