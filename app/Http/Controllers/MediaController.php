<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementKind;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Support\ContentCache;
use App\Support\HomeContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

/**
 * المركز الإعلامي: صفحته بأقسامها وأحدث الأخبار، وصفحة الشركاء. مخزنتان مؤقتاً.
 */
class MediaController extends Controller
{
    /**
     * الخبر الأحدث بطاقة كبيرة، والبقية في شريطه المتحرك (التصور النهائي — Final-Media).
     */
    private const LATEST = 7;

    /**
     * ألبومات مكتبة الصور في شريطها المتحرك.
     */
    private const ALBUMS = 10;

    /**
     * مكان بطاقة «قياس الرضا» في الصفحة المخزنة. فتح الاستبيان وإغلاقه بموعدهما لا بحفظ سجله،
     * فشرطها يُحسب في كل طلب، فلا تظهر بطاقة إلى 404 ولا تتأخر عن موعدها.
     */
    public const SURVEY_SLOT = '<!-- survey-tile -->';

    public function index(): View
    {
        $html = ContentCache::remember('media.index', fn (): string => view('media.index', [
            'latest' => NewsArticle::query()->published()->newestFirst()->limit(self::LATEST)->get(),
            'albums' => Album::query()->published()->newestFirst()->limit(self::ALBUMS)->get(),
        ])->render());

        // ظاهرة دائماً، وبلا استبيان مفتوح تفتح «قياس الرضا» برسالتها (قرار صفوان، 11 نوفمبر 2026 — SurveyController::index)
        $tile = Route::has('surveys.index') ? view('media.partials.survey-tile')->render() : '';
        $html = str_replace(self::SURVEY_SLOT, $tile, $html);

        return view('content.page', ['title' => __('media.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    /**
     * الإعلانات المنشورة في مدة عرضها (ملاحظات المدير التنفيذي، 25 أكتوبر 2026). مخزنة مؤقتاً كالرئيسية، فما انتهت مدته
     * يختفي خلال ساعة على الأكثر.
     */
    public function announcements(Request $request): View
    {
        // التصفية بالنوع من القائمة المسموحة وحدها، وما سواها «الكل»
        $kind = AnnouncementKind::tryFrom((string) $request->query('kind'));

        $html = ContentCache::remember('media.announcements.'.($kind->value ?? 'all'), fn (): string => view('media.announcements.index', [
            'announcements' => Announcement::query()->current()->when($kind, fn ($query) => $query->where('kind', $kind))->get(),
            'kind' => $kind,
        ])->render());

        // والوصف مقدمة القسم المعروضة في رأسه متى أُدخلت من اللوحة، لا النص التجريبي
        return view('content.page', ['title' => HomeContent::text('announcements', 'title'), 'summary' => HomeContent::isSample('announcements', 'intro') ? null : HomeContent::text('announcements', 'intro'), 'html' => new HtmlString($html), 'breadcrumb' => [[__('media.title'), route('media.index')]]]);
    }

    public function partners(): View
    {
        $html = ContentCache::remember('media.partners', fn (): string => view('media.partners', [
            'partners' => Partner::query()->published()->get(),
        ])->render());

        return view('content.page', ['title' => __('partners.title'), 'summary' => null, 'html' => new HtmlString($html), 'breadcrumb' => [[__('media.title'), route('media.index')]]]);
    }
}
