<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsIndexRequest;
use App\Models\NewsArticle;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * الأخبار: القائمة بالتصفية والبحث والترقيم، وصفحة كل خبر. ولا يُخزَّن إلا معرّف منشور، فلا
 * تملأ الروابط العشوائية الذاكرة.
 */
class NewsController extends Controller implements HasMiddleware
{
    /**
     * البحث والتصفية والصفحات التالية تُقرأ من القاعدة في كل طلب، فحد لكل عنوان.
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:120,1', only: ['index'])];
    }

    private const PER_PAGE = 12;

    private const MORE = 3;

    public function index(NewsIndexRequest $request): View
    {
        /** @var array{q?: string, category?: string} $filters */
        $filters = array_filter($request->safe()->only(['q', 'category']), fn ($value): bool => filled($value));

        $render = function () use ($filters): string {
            $articles = NewsArticle::query()
                ->published()
                ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
                // حرفا % و_ في البحث حرفان عاديان، لا نمطان
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'ilike', '%'.addcslashes((string) $term, '\\%_').'%'))
                ->newestFirst()
                ->paginate(self::PER_PAGE)
                // الروابط من APP_URL لا من رأس Host، ولا يدخلها إلا ما اجتاز التحقق، لأن الصفحة الأولى تُخزَّن
                ->withPath(route('media.news.index'))
                ->appends($filters);

            // لا صفحة بعد الأخيرة، فلا تُعرض قائمة فارغة لأي رقم يُكتب في الرابط
            abort_if($articles->currentPage() > $articles->lastPage(), 404);

            return view('media.news.index', [
                'articles' => $articles,
                'filters' => $filters,
                'categories' => NewsArticle::categoriesIn(NewsArticle::query()->published()),
            ])->render();
        };

        // الصفحة الأولى بلا تصفية مخزنة. والبحث والتصفية والصفحات التالية بلا تخزين
        $html = $filters === [] && $request->integer('page', 1) === 1 ? ContentCache::remember('media.news.index', $render) : $render();

        return view('content.page', ['title' => __('news.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function show(string $slug): View
    {
        /** @var list<string> $published */
        $published = ContentCache::remember('media.news.slugs', fn (): array => NewsArticle::query()->published()->pluck('slug')->all());

        abort_unless(in_array($slug, $published, true), 404);

        // فضاء مستقل لصفحة كل خبر، فلا يصطدم معرّف مثل index أو slugs بمفاتيح القائمة
        /** @var array{title: string, summary: ?string, image: ?string, html: string} $page */
        $page = ContentCache::remember('media.news.show.'.$slug, function () use ($slug): array {
            $article = NewsArticle::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $article->title,
                'summary' => $article->summary,
                'image' => $article->image ? Storage::disk('public')->url($article->image) : null,
                'html' => view('media.news.show', [
                    'article' => $article,
                    'more' => NewsArticle::query()->published()->whereKeyNot($article->getKey())->newestFirst()->limit(self::MORE)->get(),
                ])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }
}
