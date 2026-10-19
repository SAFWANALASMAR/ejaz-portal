<?php

namespace App\Filament\Resources\Activities;

use App\Enums\ActivityEvent;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

/**
 * سجل العمليات للقراءة فقط: من، وماذا، ومتى، والقيم قبل وبعد.
 * الصلاحية: activity.view (المشرف العام) — ActivityPolicy.
 */
class ActivityResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'activity';

    protected static string $navigationGroupKey = 'users';

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    /**
     * السجلات التي تكتب فيها الشيفرة (activity('...') وuseLogName)، للتسمية والتصفية. ويحرسها اختبار، فلا
     * يظهر سجل جديد بمفتاحه الخام ولا يغيب عن التصفية.
     *
     * @var list<string>
     */
    public const LOGS = ['default', 'security', 'users', 'content', 'requests', 'programs', 'competitions', 'surveys', 'payments', 'assembly', 'documents', 'job_files', 'registers', 'assistant'];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('causer');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('activity.fields.created_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('log_name')->label(__('activity.fields.log_name'))->badge()
                    ->formatStateUsing(fn (?string $state) => __('activity.logs.'.($state ?? 'default'))),
                TextColumn::make('event')->label(__('activity.fields.event'))
                    ->formatStateUsing(fn (?string $state) => self::eventLabel($state)),
                TextColumn::make('causer.name')->label(__('activity.fields.causer'))
                    ->placeholder(fn (?Activity $record): string => self::causerPlaceholder($record)),
                TextColumn::make('subject_type')->label(__('activity.fields.subject'))
                    ->formatStateUsing(fn (?string $state, Activity $record) => self::subjectLabel($record)),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('activity.fields.log_name'))
                    ->options(collect(self::LOGS)->mapWithKeys(fn (string $log) => [$log => __('activity.logs.'.$log)])),
                // الخيارات من التعداد، لا من استعلام DISTINCT على سجل يكبر بلا حد
                SelectFilter::make('event')->label(__('activity.fields.event'))
                    ->options(ActivityEvent::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('created_at')->label(__('activity.fields.created_at'))->dateTime('Y-m-d H:i:s'),
                TextEntry::make('causer.name')->label(__('activity.fields.causer'))
                    ->placeholder(fn (?Activity $record): string => self::causerPlaceholder($record)),
                TextEntry::make('event')->label(__('activity.fields.event'))
                    ->formatStateUsing(fn (?string $state) => self::eventLabel($state)),
                TextEntry::make('subject_type')->label(__('activity.fields.subject'))
                    ->formatStateUsing(fn (?string $state, Activity $record) => self::subjectLabel($record)),
                KeyValueEntry::make('attribute_changes.old')->label(__('activity.fields.old'))->columnSpanFull(),
                KeyValueEntry::make('attribute_changes.attributes')->label(__('activity.fields.new'))->columnSpanFull(),
                KeyValueEntry::make('properties')->label(__('activity.fields.properties'))->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * حدث بلا منفّذ: محاولة دخول من مجهول، أو عملية آلية من النظام.
     */
    public static function causerPlaceholder(?Activity $record): string
    {
        $anonymous = [ActivityEvent::LoginFailed->value, ActivityEvent::LoginLockout->value];

        return __(in_array($record?->event, $anonymous, true) ? 'activity.fields.unknown' : 'activity.fields.system');
    }

    public static function eventLabel(?string $event): string
    {
        return ActivityEvent::tryFrom((string) $event)?->getLabel() ?? (string) $event;
    }

    /**
     * نوع السجل المتأثر بالعربية ورقمه، مثل «حساب #5».
     */
    public static function subjectLabel(Activity $record): string
    {
        if ($record->subject_type === null) {
            return '—';
        }

        $type = class_basename($record->subject_type);

        return (Lang::has('activity.subjects.'.$type) ? __('activity.subjects.'.$type) : $type).' #'.$record->subject_id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
