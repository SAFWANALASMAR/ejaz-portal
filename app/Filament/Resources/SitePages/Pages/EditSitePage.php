<?php

namespace App\Filament\Resources\SitePages\Pages;

use App\Actions\Content\PageEditor\EnsureEditablePage;
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
use Illuminate\Support\Str;
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
     * النسخة الأولى (الأصل الذي يُرجع إليه) قبل أي حفظ، ولو فُتحت الشاشة برابطها مباشرة.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Page $page */
        $page = $this->getRecord();
        /** @var User $actor */
        $actor = auth()->user();

        app(EnsureEditablePage::class)->handle($actor, (string) EditablePages::keyForSlug($page->slug));
    }

    /**
     * المسودة إن وُجدت، وإلا ما يُعرض فعلاً (سجل الصفحة وخانة صورتها)، لا آخر نسخة منشورة، فلا يمحو النشر تعديلاً جرى خارج المحرر.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();
        $draft = $this->draft();

        return $draft !== null && is_array($draft->content) ? EditablePages::normalize($draft->content) : EditablePages::liveContent($page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        /** @var User $actor */
        $actor = auth()->user();
        // صورة الرأس قبل الحفظ: يعود إليها حقلها إن رُفضت الجديدة، لا إلى عمود غير موجود في سجل الصفحة
        $previous = $this->mutateFormDataBeforeFill([])['image'];

        try {
            return $this->saveThroughAction($record, [], function () use ($actor, $record, $data): Page {
                app(SavePageDraft::class)->handle($actor, $record, $data);

                return $record;
            });
        } catch (ValidationException $exception) {
            if (array_key_exists('data.image', $exception->errors())) {
                $this->data['image'] = $previous === null ? [] : [(string) Str::uuid() => $previous];
            }

            throw $exception;
        }
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
