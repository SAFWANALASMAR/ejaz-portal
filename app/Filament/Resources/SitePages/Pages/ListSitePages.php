<?php

namespace App\Filament\Resources\SitePages\Pages;

use App\Actions\Content\PageEditor\EnsureEditablePage;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\Page;
use App\Models\User;
use App\Support\EditablePages;
use Filament\Resources\Pages\ListRecords;

/**
 * قائمة صفحات الموقع الثابتة. وما لم يُفتح منها بعد يُنشأ سجله ونسخته الأولى بنصوص قالبه (EnsureEditablePage)، فتظهر كلها.
 */
class ListSitePages extends ListRecords
{
    protected static string $resource = SitePageResource::class;

    public function mount(): void
    {
        parent::mount();

        /** @var User $actor */
        $actor = auth()->user();
        $ready = Page::query()->whereIn('slug', EditablePages::slugs())->has('revisions')->pluck('slug')->all();

        foreach (EditablePages::PAGES as $key => [$slug]) {
            if (! in_array($slug, $ready, true)) {
                app(EnsureEditablePage::class)->handle($actor, $key);
            }
        }
    }
}
