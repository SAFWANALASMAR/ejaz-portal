<?php

namespace App\Http\Controllers;

use App\Models\Initiative;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * المبادرات: القائمة وصفحة كل مبادرة، مخزنة مؤقتاً. ولا يُخزَّن إلا معرّف منشور، فلا تملأ
 * الروابط العشوائية الذاكرة.
 */
class InitiativeController extends Controller
{
    public function index(): View
    {
        $html = ContentCache::remember('initiatives.index', fn (): string => view('content.initiatives.index', [
            'initiatives' => Initiative::query()->published()->get(),
        ])->render());

        return view('content.page', [
            'title' => __('content.initiatives.title'),
            'summary' => null,
            'html' => new HtmlString($html),
        ]);
    }

    public function show(string $slug): View
    {
        /** @var list<string> $published */
        $published = ContentCache::remember('initiatives.slugs', fn (): array => Initiative::query()->published()->pluck('slug')->all());

        abort_unless(in_array($slug, $published, true), 404);

        /** @var array{title: string, summary: ?string, html: string} $page */
        $page = ContentCache::remember('initiatives.'.$slug, function () use ($slug): array {
            $initiative = Initiative::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $initiative->name,
                'summary' => $initiative->summary,
                'html' => view('content.initiatives.show', ['initiative' => $initiative])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }
}
