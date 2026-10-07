<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\Page;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;

/**
 * صفحات المحتوى المنشورة، مخزنة مؤقتاً: «عن الجمعية» (/about) بمجلس الإدارة، وكل صفحة أخرى
 * بمعرّفها (/pages/{slug})، مثل سياسة الخصوصية. وغير المنشورة 404، ولا يُخزَّن إلا معرّف
 * منشور، فلا تملأ الروابط العشوائية الذاكرة.
 */
class PageController extends Controller
{
    public function about(): View
    {
        /** @var array{title: string, summary: ?string, html: string}|null $page */
        $page = ContentCache::remember('pages.about', function (): ?array {
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

    public function show(string $slug): View|RedirectResponse
    {
        // «عن الجمعية» برابطها الخاص وحده، فلا تتكرر الصفحة برابطين
        if ($slug === Page::ABOUT) {
            return redirect()->route('about', status: 301);
        }

        /** @var list<string> $published */
        $published = ContentCache::remember('pages.slugs', fn (): array => Page::query()->published()->pluck('slug')->all());

        abort_unless(in_array($slug, $published, true), 404);

        /** @var array{title: string, summary: ?string, html: string} $page */
        $page = ContentCache::remember('pages.show.'.$slug, function () use ($slug): array {
            $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $page->title,
                'summary' => $page->summary,
                'html' => view('content.pages.show', ['page' => $page])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }
}
