<?php

namespace App\Filament\Resources\SitePages\RelationManagers;

use App\Actions\Content\PageEditor\RevertPageRevision;
use App\Enums\PageRevisionStatus;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\PageRevision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * نسخ الصفحة: من كتب كل نسخة ومتى، ومن نشرها ومتى، ومعاينتها، والرجوع إليها مسودةً جديدة (RevertPageRevision). ولا تعديل لنسخة
 * ولا حذف (PageRevisionPolicy)، وكل حفظ ونشر ورجوع في سجل العمليات.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('page_editor.revisions.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('page_editor.revisions.model'))
            ->pluralModelLabel(__('page_editor.revisions.title'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['author', 'publisher']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('page_editor.revisions.number'))->prefix('#'),
                TextColumn::make('status')->label(__('page_editor.revisions.status'))->badge(),
                TextColumn::make('author.name')->label(__('page_editor.revisions.author'))->placeholder(__('page_editor.revisions.original')),
                TextColumn::make('updated_at')->label(__('page_editor.revisions.updated_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('publisher.name')->label(__('page_editor.revisions.publisher')),
                TextColumn::make('published_at')->label(__('page_editor.revisions.published_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('reverted_from_id')->label(__('page_editor.revisions.reverted_from'))->prefix('#'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('page_editor.actions.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (PageRevision $record): string => $record->previewUrl(), shouldOpenInNewTab: true),
                Action::make('revert')
                    ->label(__('page_editor.actions.revert'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription(__('page_editor.actions.revert_confirm'))
                    ->visible(fn (PageRevision $record): bool => $record->status !== PageRevisionStatus::Draft)
                    ->action(function (PageRevision $record): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        try {
                            app(RevertPageRevision::class)->handle($actor, $record);
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();

                            return;
                        }

                        Notification::make()->success()->title(__('page_editor.notifications.reverted'))->send();
                        // النموذج فوق الجدول يعرض المسودة الجديدة
                        $this->redirect(SitePageResource::getUrl('edit', ['record' => $this->getOwnerRecord()]));
                    }),
            ]);
    }
}
