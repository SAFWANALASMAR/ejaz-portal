<?php

namespace App\Actions\Content\PageEditor;

use App\Enums\ActivityEvent;
use App\Enums\PageRevisionStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use App\Models\User;
use App\Support\EditablePages;
use App\Support\HomeContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نشر مسودة الصفحة الثابتة: عنوانها ووصفها ومقاطعها إلى سجل الصفحة (Page) فيُعرض في قالبها، وصورة رأسها إلى خانتها في
 * «محتوى الرئيسية» (home_sections.page_heroes)، فلا مصدر ثانٍ لما يُعرض. والمنشورة قبلها تصير سابقة يُرجع إليها. وسجل
 * الصفحة والإعداد يُسجَّلان ويمسحان ذاكرة المحتوى بنفسيهما، ومعهما حدث النشر بنسخته.
 */
class PublishPageDraft
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException لا مسودة تُنشر
     */
    public function handle(User $actor, Page $page): PageRevision
    {
        if (! EditablePages::isEditable($page) || ! $actor->can('update', $page)) {
            throw new AuthorizationException;
        }

        $key = (string) EditablePages::keyForSlug($page->slug);

        return DB::transaction(function () use ($actor, $page, $key): PageRevision {
            /** @var Page $locked */
            $locked = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            $draft = PageRevision::query()->where('page_id', $locked->id)->status(PageRevisionStatus::Draft)->first();

            if ($draft === null) {
                throw ValidationException::withMessages(['draft' => __('page_editor.no_draft')]);
            }

            $content = EditablePages::normalize(is_array($draft->content) ? $draft->content : []);

            PageRevision::query()->where('page_id', $locked->id)->status(PageRevisionStatus::Published)
                ->update(['status' => PageRevisionStatus::Archived->value, 'updated_at' => now()]);

            $draft->fill(['status' => PageRevisionStatus::Published, 'published_by' => $actor->id, 'published_at' => now()])->save();

            $locked->fill(['title' => $content['title'], 'summary' => $content['summary'], 'blocks' => $content['blocks'], 'is_published' => true])->save();

            // صورة الرأس في خانتها، بقفل صف الإعداد فلا يضيع نشر أو حفظ متزامن لـ«محتوى الرئيسية» (UpdateHomeContent)
            $stored = Setting::query()->where('key', HomeContent::KEY)->lockForUpdate()->value('value');
            $stored = is_array($stored) ? $stored : [];

            if (($stored['page_heroes'][$key] ?? null) !== $content['image']) {
                $stored['page_heroes'][$key] = $content['image'];
                Setting::put(HomeContent::KEY, $stored);
            }

            activity('content')->causedBy($actor)->performedOn($locked)
                ->event(ActivityEvent::PagePublished->value)
                ->withProperties(['page' => $key, 'revision' => $draft->id])
                ->log(ActivityEvent::PagePublished->value);

            $page->setRawAttributes($locked->getAttributes(), true);

            return $draft;
        });
    }
}
