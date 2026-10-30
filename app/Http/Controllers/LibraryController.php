<?php

namespace App\Http\Controllers;

use App\Enums\LibrarySection;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Requests\LibraryIndexRequest;
use App\Models\LibraryItem;
use App\Support\ContentCache;
use App\Support\PrivateFileResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * المكتبة: صفحتها بأقسامها وأحدث العناصر، وصفحة كل قسم بالبحث والتصفية، وصفحة كل عنصر بمشغّله
 * أو عارضه. والملفات في القرص الخاص، فلا يُقدَّم إلا ملف عنصر منشور (decisions.md).
 */
class LibraryController extends Controller implements HasMiddleware
{
    /**
     * البحث والتصفية والصفحات التالية تُقرأ من القاعدة في كل طلب، فحد لكل عنوان.
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:120,1', only: ['section'])];
    }

    private const PER_PAGE = 12;

    /**
     * فتحات كتب «للقراءة فقط» في الدقيقة لكل عنوان (serveToReader).
     */
    private const READER_PER_MINUTE = 20;

    private const LATEST = 6;

    private const RELATED = 3;

    private const TAGS = 20;

    public function index(): View
    {
        $html = ContentCache::remember('library.index', fn (): string => view('library.index', [
            'latest' => LibraryItem::query()->published()->newestFirst()->limit(self::LATEST)->get(),
        ])->render());

        return view('content.page', ['title' => __('library.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function section(LibraryIndexRequest $request, string $section): View
    {
        $section = LibrarySection::from($section);

        /** @var array{q?: string, tag?: string} $filters */
        $filters = array_filter($request->safe()->only(['q', 'tag']), fn ($value): bool => filled($value));

        $render = function () use ($section, $filters): string {
            $items = self::search($section, $filters);

            // لا صفحة بعد الأخيرة، فلا تُعرض قائمة فارغة لأي رقم يُكتب في الرابط
            abort_if($items->currentPage() > $items->lastPage(), 404);

            return view('library.section', [
                'section' => $section,
                'items' => $items,
                'filters' => $filters,
                // الأكثر استعمالاً
                'tags' => array_slice(LibraryItem::tagsIn(LibraryItem::query()->published()->where('section', $section)), 0, self::TAGS),
            ])->render();
        };

        // الصفحة الأولى بلا تصفية مخزنة. والبحث والتصفية والصفحات التالية بلا تخزين، فلا تُملأ
        // الذاكرة بمفاتيح من مدخلات الزوار
        $html = $filters === [] && $request->integer('page', 1) === 1 ? ContentCache::remember('library.section.'.$section->value, $render) : $render();

        return view('content.page', ['title' => $section->getLabel(), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function show(string $section, string $slug): View
    {
        // معرّف منشور في قسمه، فلا يُخزَّن غيره، ولا يعمل الرابط بقسم آخر
        abort_unless((self::publishedSlugs()[$slug] ?? null) === $section, 404);

        /** @var array{title: string, summary: string, image: ?string, html: string} $page */
        $page = ContentCache::remember('library.show.'.$slug, function () use ($slug): array {
            $item = LibraryItem::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $item->title,
                'summary' => $item->section->getLabel(),
                'image' => $item->thumbnail ? Storage::disk('public')->url($item->thumbnail) : null,
                'html' => view('library.show', ['item' => $item, 'related' => self::related($item)])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }

    /**
     * الملف للعرض: PDF مدمجاً في صفحة العنصر (يقبل التضمين من الموقع نفسه — SecurityHeaders)،
     * أو الصوت في مشغّله.
     */
    public function file(Request $request, string $section, string $slug): Response
    {
        $item = self::publishedItem($section, $slug);

        return $item->isReadOnly() ? self::serveToReader($request, $item) : self::serve($item, 'inline');
    }

    /**
     * والكتاب «للقراءة فقط» لا تنزيل له: لا زر في صفحته، والمسار 404 كأنه غير موجود.
     */
    public function download(string $section, string $slug): Response
    {
        $item = self::publishedItem($section, $slug);

        abort_if($item->isReadOnly(), 404);

        return self::serve($item, 'attachment');
    }

    /**
     * ملف الكتاب «للقراءة فقط» لقارئ الصفحة (resources/js/pdf-reader.js) وحده: يُبث من التطبيق على أي قرص، ولا
     * تحويل إلى رابط تخزين موقّع يُفتح وحده في عارض المتصفح بزر تنزيله ويُتداول حتى نهاية مدته (decisions.md — 30
     * أكتوبر 2026). والعرض inline دائماً بلا تخزين، ولا يُقرأ أي معامل في الرابط. وفتح المسار نفسه في تبويب أو إطار
     * (Sec-Fetch-Dest) يعود إلى صفحة الكتاب، فلا يصل عارض المتصفح. وهذا يصعّب الحفظ ولا يمنعه منعاً تاماً: ما يُعرض
     * على الشاشة يمكن تصويره، والملف يصل المتصفح ليُرسم.
     */
    private static function serveToReader(Request $request, LibraryItem $item): Response
    {
        if (in_array($request->headers->get('Sec-Fetch-Dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true)) {
            return redirect()->route('library.show', [$item->section->value, $item->slug]);
        }

        // الملف كاملاً عبر PHP، والقارئ يطلبه مرة لكل فتح: فحد أضيق لكل عنوان من حد المسار، فلا يحجز التكرار عمال PHP
        // ولا حصة التخزين (المراجعة الأمنية م١)
        $key = 'library-reader:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, self::READER_PER_MINUTE), 429);
        RateLimiter::hit($key, 60);

        // علامة لـ SecurityHeaders: السياسة الكاملة لا سطر التضمين، فلا يُضمَّن الملف في إطار ولا يعمل عليه عارض المتصفح
        $request->attributes->set(SecurityHeaders::READER_ONLY, true);

        return Storage::disk(LibraryItem::disk())->response((string) $item->file, $item->downloadName(), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ], 'inline');
    }

    /**
     * الصوت على القرص المحلي يُقدَّم بطلب الجزء (Range)، فيعمل التقديم والتأخير في مشغّله. وPDF
     * كاملاً كالحوكمة، لأن عارض المتصفح يطلب الملف المحسَّن للويب قطعاً صغيرة كثيرة تتجاوز حد
     * الطلبات. والاستجابة خاصة، فلا يحتفظ بها وسيط بعد إلغاء النشر. وعلى S3 تحويل إلى رابط تخزين
     * موقّع قصير بعد فحص النشر، يطلب منه المشغّل والعارض الأجزاء مباشرة (roadmap.md، خطة I8). ويلزمه
     * نطاق التخزين في CSP_MEDIA_SRC للصوت وCSP_FRAME_SRC للعرض المدمج.
     */
    private static function serve(LibraryItem $item, string $disposition): Response
    {
        if (! PrivateFileResponse::isLocal(LibraryItem::disk())) {
            return PrivateFileResponse::temporaryRedirect(LibraryItem::disk(), (string) $item->file, $item->fileContentType(), $item->downloadName(), (int) config('filesystems.content_url_minutes'), $disposition === 'attachment');
        }

        $disk = Storage::disk(LibraryItem::disk());
        $headers = ['Content-Type' => $item->fileContentType()];

        if (! $item->section->takesPdf()) {
            return response()->file($disk->path((string) $item->file), $headers)
                ->setPrivate()
                ->setContentDisposition($disposition, $item->downloadName());
        }

        return $disposition === 'inline'
            ? $disk->response((string) $item->file, $item->downloadName(), $headers, 'inline')
            : $disk->download((string) $item->file, $item->downloadName(), $headers);
    }

    /**
     * @param  array{q?: string, tag?: string}  $filters
     * @return LengthAwarePaginator<int, LibraryItem>
     */
    private static function search(LibrarySection $section, array $filters): LengthAwarePaginator
    {
        return LibraryItem::query()
            ->published()
            ->where('section', $section)
            // حرفا % و_ في البحث حرفان عاديان، لا نمطان
            ->when($filters['q'] ?? null, function ($query, $term): void {
                $pattern = '%'.addcslashes((string) $term, '\\%_').'%';
                $query->where(fn ($query) => $query->where('title', 'ilike', $pattern)->orWhere('author', 'ilike', $pattern));
            })
            ->when($filters['tag'] ?? null, fn ($query, $tag) => $query->whereJsonContains('tags', (string) $tag))
            ->newestFirst()
            ->paginate(self::PER_PAGE)
            // الروابط من APP_URL لا من رأس Host، لأن الصفحة الأولى تُخزَّن
            ->withPath(route('library.section', $section->value))
            // ولا يدخلها إلا ما اجتاز التحقق، فلا تحمل الصفحة المخزنة معاملات زائر
            ->appends($filters);
    }

    /**
     * عناصر من القسم نفسه: ما يشارك العنصر تصنيفاً أولاً، ثم الأحدث. بلا العنصر نفسه.
     *
     * @return Collection<int, LibraryItem>
     */
    private static function related(LibraryItem $item): Collection
    {
        $others = fn () => LibraryItem::query()->published()->where('section', $item->section)->whereKeyNot($item->getKey())->newestFirst();
        $tags = $item->tagList();

        $related = $tags === [] ? new Collection : $others()
            ->where(function ($query) use ($tags): void {
                foreach ($tags as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            })
            ->limit(self::RELATED)
            ->get();

        if ($related->count() >= self::RELATED) {
            return $related;
        }

        return $related->concat($others()->whereKeyNot($related->modelKeys())->limit(self::RELATED - $related->count())->get());
    }

    /**
     * المعرّفات المنشورة وأقسامها.
     *
     * @return array<string, string>
     */
    private static function publishedSlugs(): array
    {
        return ContentCache::remember('library.slugs', fn (): array => LibraryItem::query()
            ->published()
            ->get(['slug', 'section'])
            ->mapWithKeys(fn (LibraryItem $item): array => [$item->slug => $item->section->value])
            ->all());
    }

    private static function publishedItem(string $section, string $slug): LibraryItem
    {
        $item = LibraryItem::query()->published()->where('section', $section)->where('slug', $slug)->whereNotNull('file')->firstOrFail();

        abort_unless(LibraryItem::isOwnFile((string) $item->file) && Storage::disk(LibraryItem::disk())->exists((string) $item->file), 404);

        return $item;
    }
}
