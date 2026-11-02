<?php

namespace App\Actions\Content\PageEditor;

use App\Enums\ActivityEvent;
use App\Enums\PageRevisionStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\EditablePages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الرجوع إلى نسخة سابقة: مسودة جديدة بمحتواها، تُعاين ثم تُنشر كأي مسودة، فلا يتغير ما يُعرض للزائر قبل النشر. والمسودة
 * القائمة قبلها تصير سابقة لا تُحذف، فلا يضيع عمل. ولا تُحذف نسخة ولا تُعدَّل: الرجوع نسخة جديدة تشير إلى أصلها.
 */
class RevertPageRevision
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException الرجوع إلى المسودة نفسها
     */
    public function handle(User $actor, PageRevision $revision): PageRevision
    {
        $page = $revision->page;

        if (! EditablePages::isEditable($page) || ! $actor->can('revert', $revision) || ! $actor->can('update', $page)) {
            throw new AuthorizationException;
        }

        if ($revision->status === PageRevisionStatus::Draft) {
            throw ValidationException::withMessages(['revision' => __('page_editor.revert_draft')]);
        }

        return DB::transaction(function () use ($actor, $page, $revision): PageRevision {
            Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            PageRevision::query()->where('page_id', $page->id)->status(PageRevisionStatus::Draft)
                ->update(['status' => PageRevisionStatus::Archived->value, 'updated_at' => now()]);

            $draft = PageRevision::query()->create([
                'page_id' => $page->id,
                'status' => PageRevisionStatus::Draft,
                'content' => EditablePages::normalize(is_array($revision->content) ? $revision->content : []),
                'author_id' => $actor->id,
                'reverted_from_id' => $revision->id,
            ]);

            activity('content')->causedBy($actor)->performedOn($page)
                ->event(ActivityEvent::PageReverted->value)
                ->withProperties(['page' => EditablePages::keyForSlug($page->slug), 'from' => $revision->id, 'revision' => $draft->id])
                ->log(ActivityEvent::PageReverted->value);

            return $draft;
        });
    }
}
