<?php

namespace App\Filament\Resources\Activities;

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
                TextColumn::make('event')->label(__('activity.fields.event'))->searchable(),
                TextColumn::make('causer.name')->label(__('activity.fields.causer'))->placeholder(__('activity.fields.system')),
                TextColumn::make('subject_type')->label(__('activity.fields.subject'))
                    ->formatStateUsing(fn (?string $state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('activity.fields.log_name'))
                    ->options(collect(['default', 'security', 'users', 'content'])->mapWithKeys(fn (string $log) => [$log => __('activity.logs.'.$log)])),
                SelectFilter::make('event')->label(__('activity.fields.event'))
                    ->options(fn () => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event', 'event')),
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
                TextEntry::make('causer.name')->label(__('activity.fields.causer'))->placeholder(__('activity.fields.system')),
                TextEntry::make('event')->label(__('activity.fields.event')),
                TextEntry::make('subject_type')->label(__('activity.fields.subject'))
                    ->formatStateUsing(fn (?string $state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                KeyValueEntry::make('attribute_changes.old')->label(__('activity.fields.old'))->columnSpanFull(),
                KeyValueEntry::make('attribute_changes.attributes')->label(__('activity.fields.new'))->columnSpanFull(),
                KeyValueEntry::make('properties')->label(__('activity.fields.properties'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
