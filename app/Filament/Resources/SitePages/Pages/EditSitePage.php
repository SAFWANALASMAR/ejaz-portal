<?php

namespace App\Filament\Resources\SitePages\Pages;

use App\Actions\Content\PageEditor\PublishPageDraft;
use App\Actions\Content\PageEditor\SavePageDraft;
use App\Enums\PageRevisionStatus;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\EditablePages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * شاشة الصفحة في «محرر الصفحات»: النموذج يعرض المسودة إن وُجدت، وإلا المنشور. والحفظ مسودة لا تمس ما يُعرض (SavePageDraft)،
 * ثم «معاينة» برابط موقّع قصير، ثم «نشر» (PublishPageDraft). ونسخ الصفحة والرجوع إليها تحت النموذج (RevisionsRelationManager).
 */
class EditSitePage extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = SitePageResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('page_editor.edit_title', ['page' => __('page_editor.pages.'.$this->pageKey())]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();
        $revision = $this->draft() ?? PageRevision::query()->where('page_id', $page->id)->status(PageRevisionStatus::Published)->first();

        return $revision !== null && is_array($revision->content) ? EditablePages::normalize($revision->content) : EditablePages::liveContent($page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, ['image'], function () use ($actor, $record, $data): Page {
            app(SavePageDraft::class)->handle($actor, $record, $data);

            return $record;
        });
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('page_editor.notifications.draft_saved');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('page_editor.actions.save_draft'));
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('page_editor.actions.preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->draft() !== null)
                ->url(fn (): ?string => $this->draft()?->previewUrl(), shouldOpenInNewTab: true),
            Action::make('publish')
                ->label(__('page_editor.actions.publish'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->requiresConfirmation()
                ->modalDescription(__('page_editor.actions.publish_confirm'))
                ->visible(fn (): bool => $this->draft() !== null)
                ->action(function (): void {
                    /** @var Page $page */
                    $page = $this->getRecord();
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        app(PublishPageDraft::class)->handle($actor, $page);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('page_editor.notifications.published'))->send();
                    $this->redirect(SitePageResource::getUrl('edit', ['record' => $page]));
                }),
            Action::make('view_live')
                ->label(__('page_editor.actions.view_live'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route(EditablePages::PAGES[$this->pageKey()][1]), shouldOpenInNewTab: true),
        ];
    }

    private function pageKey(): string
    {
        /** @var Page $page */
        $page = $this->getRecord();

        return (string) EditablePages::keyForSlug($page->slug);
    }

    private function draft(): ?PageRevision
    {
        /** @var Page $page */
        $page = $this->getRecord();

        return PageRevision::query()->where('page_id', $page->id)->status(PageRevisionStatus::Draft)->first();
    }
}
