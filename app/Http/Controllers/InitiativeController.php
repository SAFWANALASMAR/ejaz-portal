<?php

namespace App\Http\Controllers;

use App\Models\Initiative;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
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

        // فضاء مستقل لصفحة كل مبادرة، فلا يصطدم معرّف مثل index أو slugs بمفاتيح القائمة
        /** @var array{title: string, summary: ?string, image: ?string, html: string} $page */
        $page = ContentCache::remember('initiatives.show.'.$slug, function () use ($slug): array {
            $initiative = Initiative::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $initiative->name,
                'summary' => $initiative->summary,
                'image' => $initiative->image ? Storage::disk('public')->url($initiative->image) : null,
                'html' => view('content.initiatives.show', ['initiative' => $initiative])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html']), 'breadcrumb' => [[__('content.initiatives.title'), route('initiatives.index')]]]);
    }
}
