<?php

namespace App\Filament\Resources\LiveStreams;

use App\Actions\Livestream\ArchiveLiveStream;
use App\Enums\LiveStreamProvider;
use App\Enums\LiveStreamStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\LiveStreams\Pages\CreateLiveStream;
use App\Filament\Resources\LiveStreams\Pages\EditLiveStream;
use App\Filament\Resources\LiveStreams\Pages\ListLiveStreams;
use App\Filament\Resources\LiveStreams\Schemas\LiveStreamForm;
use App\Models\LibraryItem;
use App\Models\LiveStream;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

/**
 * البث المباشر في مجموعة المركز الإعلامي. الصلاحيات: livestreams.* — LiveStreamPolicy. والحفظ عبر
 * SaveLiveStream، ونقل التسجيل إلى المكتبة عبر ArchiveLiveStream، والحذف ناعم.
 */
class LiveStreamResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'livestream';

    protected static string $navigationGroupKey = 'media';

    protected static ?string $model = LiveStream::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    public static function form(Schema $schema): Schema
    {
        return LiveStreamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('livestream.fields.title'))->searchable()->wrap(),
                TextColumn::make('provider')->label(__('livestream.fields.provider'))->badge(),
                TextColumn::make('status')->label(__('livestream.fields.status'))->badge()
                    ->state(fn (LiveStream $record): LiveStreamStatus => $record->status()),
                TextColumn::make('starts_at')->label(__('livestream.fields.starts_at'))->dateTime('Y-m-d H:i')->sortable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                self::archiveAction(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * نقل التسجيل إلى المكتبة المرئية: لبث منتهٍ لم يُنقل، لمن يعدّل البث وينشئ عناصر المكتبة.
     */
    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('livestream.archive.action'))
            ->icon('heroicon-o-film')
            ->visible(function (LiveStream $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && $record->archived_at === null
                    && $record->status() === LiveStreamStatus::Ended
                    && $user->can('update', $record)
                    && $user->can('create', LibraryItem::class);
            })
            ->modalDescription(__('livestream.archive.confirm'))
            ->schema([
                TextInput::make('recording_url')->label(__('livestream.archive.recording_url'))
                    ->helperText(__('livestream.archive.recording_url_hint'))
                    ->maxLength(500)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->required(fn (LiveStream $record): bool => $record->provider === LiveStreamProvider::Teams),
            ])
            ->action(function (LiveStream $record, array $data): void {
                /** @var User $user */
                $user = auth()->user();

                try {
                    app(ArchiveLiveStream::class)->handle($user, $record, isset($data['recording_url']) ? (string) $data['recording_url'] : null);
                    Notification::make()->success()->title(__('livestream.archive.done'))->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                }
            });
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLiveStreams::route('/'),
            'create' => CreateLiveStream::route('/create'),
            'edit' => EditLiveStream::route('/{record}/edit'),
        ];
    }
}
