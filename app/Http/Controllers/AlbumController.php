<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlbumIndexRequest;
use App\Models\Album;
use App\Support\ContentCache;
use App\Support\Dates;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * مكتبة الصور: الألبومات بترقيم، وصفحة كل ألبوم بصوره. ولا يُخزَّن إلا معرّف منشور.
 */
class AlbumController extends Controller implements HasMiddleware
{
    /**
     * البحث والتصفية والصفحات التالية تُقرأ من القاعدة في كل طلب، فحد لكل عنوان.
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:120,1', only: ['index'])];
    }

    private const PER_PAGE = 12;

    public function index(AlbumIndexRequest $request): View
    {
        $render = function (): string {
            $albums = Album::query()->published()->newestFirst()->paginate(self::PER_PAGE)->withPath(route('media.albums.index'));

            abort_if($albums->currentPage() > $albums->lastPage(), 404);

            return view('media.albums.index', ['albums' => $albums])->render();
        };

        $html = $request->integer('page', 1) === 1 ? ContentCache::remember('media.albums.index', $render) : $render();

        return view('content.page', ['title' => __('albums.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function show(string $slug): View
    {
        /** @var list<string> $published */
        $published = ContentCache::remember('media.albums.slugs', fn (): array => Album::query()->published()->pluck('slug')->all());

        abort_unless(in_array($slug, $published, true), 404);

        /** @var array{title: string, summary: string, image: ?string, html: string} $page */
        $page = ContentCache::remember('media.albums.show.'.$slug, function () use ($slug): array {
            $album = Album::query()->published()->where('slug', $slug)->firstOrFail();
            $cover = $album->cover();

            return [
                'title' => $album->title,
                'summary' => Dates::display($album->published_on),
                'image' => $cover !== null ? Storage::disk('public')->url($cover) : null,
                'html' => view('media.albums.show', ['album' => $album])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }
}
