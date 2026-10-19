<?php

namespace App\Http\Controllers;

use App\Http\Requests\GovernanceIndexRequest;
use App\Models\Document;
use App\Support\ContentCache;
use App\Support\PrivateFileResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * الحوكمة: قائمة الوثائق المنشورة بالبحث والتصفية، وصفحة كل وثيقة، وملفها عرضاً مدمجاً
 * وتنزيلاً. الملفات في القرص الخاص، فلا يُقدَّم إلا ملف وثيقة منشورة، فإذا أُلغي نشرها لم
 * يبقَ ملفها متاحاً بمسار الموقع. وعلى S3 يبقى رابط تخزين صدر قبل الإلغاء صالحاً حتى نهاية مدته
 * (CONTENT_FILE_URL_MINUTES)، فالسحب العاجل بحذف الملف أو استبداله (docs/decisions.md).
 */
class GovernanceController extends Controller implements HasMiddleware
{
    /**
     * البحث والتصفية والصفحات التالية تُقرأ من القاعدة في كل طلب، فحد لكل عنوان.
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:120,1', only: ['index'])];
    }

    private const PER_PAGE = 12;

    public function index(GovernanceIndexRequest $request): View
    {
        /** @var array{category?: ?string, year?: ?int, q?: ?string} $filters */
        $filters = array_filter($request->safe()->only(['category', 'year', 'q']), fn ($value): bool => filled($value));

        $render = function () use ($filters): string {
            $documents = self::search($filters);

            // لا صفحة بعد الأخيرة، فلا تُعرض قائمة فارغة لأي رقم يُكتب في الرابط
            abort_if($documents->currentPage() > $documents->lastPage(), 404);

            return view('governance.index', [
                'documents' => $documents,
                'filters' => $filters,
                'years' => self::years(),
            ])->render();
        };

        // الصفحة الأولى بلا تصفية مخزنة. والبحث والتصفية والصفحات التالية بلا تخزين، فلا تُملأ
        // الذاكرة بمفاتيح من مدخلات الزوار
        $html = $filters === [] && $request->integer('page', 1) === 1 ? ContentCache::remember('governance.index', $render) : $render();

        return view('content.page', ['title' => __('governance.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function show(string $slug): View
    {
        abort_unless(in_array($slug, self::publishedSlugs(), true), 404);

        /** @var array{title: string, summary: ?string, html: string} $page */
        $page = ContentCache::remember('governance.show.'.$slug, function () use ($slug): array {
            $document = Document::query()->published()->where('slug', $slug)->firstOrFail();

            return [
                'title' => $document->title,
                'summary' => $document->description,
                'html' => view('governance.show', ['document' => $document])->render(),
            ];
        });

        return view('content.page', [...$page, 'html' => new HtmlString($page['html'])]);
    }

    /**
     * الملف للعرض المدمج في صفحة الوثيقة. وهذا المسار وحده يقبل التضمين من الموقع نفسه
     * (SecurityHeaders).
     */
    public function file(string $slug): Response
    {
        return self::serve(self::publishedDocument($slug), download: false);
    }

    public function download(string $slug): Response
    {
        return self::serve(self::publishedDocument($slug), download: true);
    }

    /**
     * على القرص المحلي من التطبيق، وعلى S3 بتحويل إلى رابط تخزين موقّع قصير بعد فحص النشر، فلا تمر الملفات
     * عبر PHP في الذروة (roadmap.md، خطة I8). والعرض المدمج يلزمه نطاق التخزين في CSP_FRAME_SRC.
     */
    private static function serve(Document $document, bool $download): Response
    {
        if (! PrivateFileResponse::isLocal(Document::disk())) {
            return PrivateFileResponse::temporaryRedirect(Document::disk(), $document->file, 'application/pdf', $document->downloadName(), (int) config('filesystems.content_url_minutes'), $download);
        }

        $disk = Storage::disk(Document::disk());
        $headers = ['Content-Type' => 'application/pdf'];

        return $download
            ? $disk->download($document->file, $document->downloadName(), $headers)
            : $disk->response($document->file, $document->downloadName(), $headers, 'inline');
    }

    /**
     * @param  array{category?: ?string, year?: ?int, q?: ?string}  $filters
     * @return LengthAwarePaginator<int, Document>
     */
    private static function search(array $filters): LengthAwarePaginator
    {
        return Document::query()
            ->published()
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('issued_at', (int) $year))
            // حرفا % و_ في البحث حرفان عاديان، لا نمطان
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'ilike', '%'.addcslashes((string) $term, '\\%_').'%'))
            ->newestFirst()
            ->paginate(self::PER_PAGE)
            // الروابط من APP_URL لا من رأس Host، لأن الصفحة الأولى تُخزَّن
            ->withPath(route('governance.index'))
            // ولا يدخلها إلا ما اجتاز التحقق، فلا تحمل الصفحة المخزنة معاملات زائر
            ->appends($filters);
    }

    /**
     * سنوات الإصدار في الوثائق المنشورة، لقائمة التصفية.
     *
     * @return Collection<int, int>
     */
    private static function years(): Collection
    {
        return Document::query()
            ->published()
            ->whereNotNull('issued_at')
            ->pluck('issued_at')
            ->map(fn ($date): int => (int) $date->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * @return list<string>
     */
    private static function publishedSlugs(): array
    {
        return ContentCache::remember('governance.slugs', fn (): array => Document::query()->published()->pluck('slug')->all());
    }

    private static function publishedDocument(string $slug): Document
    {
        $document = Document::query()->published()->where('slug', $slug)->firstOrFail();

        abort_unless(Storage::disk(Document::disk())->exists($document->file), 404);

        return $document;
    }
}
