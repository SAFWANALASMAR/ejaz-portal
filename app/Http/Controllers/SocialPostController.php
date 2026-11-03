<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Http\Requests\SocialPostIndexRequest;
use App\Models\SocialPost;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\HtmlString;

/**
 * «منشوراتنا» في المركز الإعلامي (طلب صفوان، 3 نوفمبر 2026): المنشور وحده بالتصفية بالمنصة والنوع والترقيم. والصفحة الأولى بلا
 * تصفية مخزنة كقائمة الأخبار. ولا تضمين ولا سكربت من المنصات: بطاقة برابط المنشور الأصلي في تبويب جديد.
 */
class SocialPostController extends Controller implements HasMiddleware
{
    private const PER_PAGE = 12;

    /**
     * التصفية والصفحات التالية تُقرأ من القاعدة في كل طلب، فحد لكل عنوان.
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:120,1')];
    }

    public function index(SocialPostIndexRequest $request): View
    {
        $platform = SocialPlatform::tryFrom((string) $request->validated('platform'));
        $kind = SocialPostKind::tryFrom((string) $request->validated('kind'));
        $filters = array_filter(['platform' => $platform?->value, 'kind' => $kind?->value]);

        $render = function () use ($platform, $kind, $filters): string {
            $posts = SocialPost::query()
                ->published()
                ->when($platform, fn ($query, SocialPlatform $platform) => $query->where('platform', $platform))
                ->when($kind, fn ($query, SocialPostKind $kind) => $query->where('kind', $kind))
                ->newestFirst()
                ->paginate(self::PER_PAGE)
                // الروابط من APP_URL لا من رأس Host، ولا يدخلها إلا ما اجتاز التحقق، لأن الصفحة الأولى تُخزَّن
                ->withPath(route('media.social'))
                ->appends($filters);

            // لا صفحة بعد الأخيرة، فلا تُعرض قائمة فارغة لأي رقم يُكتب في الرابط
            abort_if($posts->currentPage() > $posts->lastPage(), 404);

            return view('media.social.index', ['posts' => $posts, 'platform' => $platform, 'kind' => $kind, 'filters' => $filters])->render();
        };

        $html = $filters === [] && $request->integer('page', 1) === 1 ? ContentCache::remember('media.social', $render) : $render();

        return view('content.page', ['title' => __('social_posts.title'), 'summary' => null, 'html' => new HtmlString($html), 'breadcrumb' => [[__('media.title'), route('media.index')]]]);
    }
}
