<?php

namespace App\Actions\Content\PageEditor;

use App\Enums\PageRevisionStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\EditablePages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * سجل الصفحة الثابتة ونسختها الأولى عند فتحها في «محرر الصفحات» أول مرة: السجل بنصوص قالبها كما تُعرض الآن، غير منشور فلا
 * يتغير شيء للزائر، والنسخة الأولى بما يُعرض الآن (ومنه صورة الرأس من خانتها)، فيُرجع إليها. و«عن الجمعية» سجلها القائم
 * بمحتواه. والإجراء يتكرر بلا أثر، ولا يتسابق فيه طلبان (createOrFirst وقفل السجل).
 */
class EnsureEditablePage
{
    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException مفتاح ليس في السجل
     */
    public function handle(User $actor, string $key): Page
    {
        if (! $actor->can('pages.edit_all')) {
            throw new AuthorizationException;
        }

        if (! isset(EditablePages::PAGES[$key])) {
            throw (new ModelNotFoundException)->setModel(Page::class, [$key]);
        }

        $defaults = EditablePages::defaults($key);

        $page = Page::query()->createOrFirst(
            ['slug' => EditablePages::slug($key)],
            ['title' => $defaults['title'], 'summary' => $defaults['summary'], 'blocks' => [], 'is_published' => false],
        );

        DB::transaction(function () use ($page): void {
            /** @var Page $locked */
            $locked = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            if (PageRevision::query()->where('page_id', $locked->id)->exists()) {
                return;
            }

            PageRevision::query()->create([
                'page_id' => $locked->id,
                'status' => $locked->is_published ? PageRevisionStatus::Published : PageRevisionStatus::Archived,
                'content' => EditablePages::liveContent($locked),
                'published_at' => $locked->is_published ? $locked->updated_at : null,
            ]);
        });

        return $page;
    }
}
