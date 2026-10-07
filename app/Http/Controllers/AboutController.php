<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\Page;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * «عن الجمعية»: صفحة المحتوى about ومجلس الإدارة، مخزنة مؤقتاً. وغير المنشورة 404.
 */
class AboutController extends Controller
{
    public function __invoke(): View
    {
        /** @var array{title: string, summary: ?string, html: string}|null $page */
        $page = ContentCache::remember('page.about', function (): ?array {
            $page = Page::query()->published()->where('slug', Page::ABOUT)->first();

            return $page === null ? null : [
                'title' => $page->title,
                'summary' => $page->summary,
                'html' => view('content.about', [
                    'page' => $page,
                    'members' => BoardMember::query()->visible()->get(),
                ])->render(),
            ];
        });

        abort_if($page === null, 404);

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }
}
