<?php

namespace App\Http\Controllers;

use App\Http\Requests\GovernanceIndexRequest;
use App\Models\Document;
use App\Support\ContentCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الحوكمة: قائمة الوثائق المنشورة بالبحث والتصفية، وصفحة كل وثيقة، وملفها عرضاً مدمجاً
 * وتنزيلاً. الملفات في القرص الخاص، فلا يُقدَّم إلا ملف وثيقة منشورة، فإذا أُلغي نشرها لم
 * يبقَ ملفها متاحاً برابط قديم.
 */
class GovernanceController extends Controller
{
    private const PER_PAGE = 12;

    public function index(GovernanceIndexRequest $request): View
    {
        /** @var array{category?: ?string, year?: ?int, q?: ?string} $filters */
        $filters = array_filter($request->safe()->only(['category', 'year', 'q']), fn ($value): bool => filled($value));

        $render = fn (): string => view('governance.index', [
            'documents' => self::search($filters),
            'filters' => $filters,
            'years' => self::years(),
        ])->render();

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
    public function file(string $slug): StreamedResponse
    {
        $document = self::publishedDocument($slug);

        return Storage::disk(Document::DISK)->response($document->file, $document->downloadName(), ['Content-Type' => 'application/pdf'], 'inline');
    }

    public function download(string $slug): StreamedResponse
    {
        $document = self::publishedDocument($slug);

        return Storage::disk(Document::DISK)->download($document->file, $document->downloadName(), ['Content-Type' => 'application/pdf']);
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
            ->paginate(self::PER_PAGE)
            // الروابط من APP_URL لا من رأس Host، لأن الصفحة الأولى تُخزَّن
            ->withPath(route('governance.index'))
            ->withQueryString();
    }

    /**
     * سنوات الإصدار في الوثائق المنشورة، لقائمة التصفية.
     *
     * @return Collection<int, int>
     */
    private static function years(): Collection
    {
        return Document::query()
            ->where('is_published', true)
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
        return ContentCache::remember('governance.slugs', fn (): array => Document::query()->where('is_published', true)->pluck('slug')->all());
    }

    private static function publishedDocument(string $slug): Document
    {
        $document = Document::query()->where('is_published', true)->where('slug', $slug)->firstOrFail();

        abort_unless(Storage::disk(Document::DISK)->exists($document->file), 404);

        return $document;
    }
}
