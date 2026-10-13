<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Survey;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

/**
 * المركز الإعلامي: صفحته بأقسامها وأحدث الأخبار، وصفحة الشركاء. مخزنتان مؤقتاً.
 */
class MediaController extends Controller
{
    private const LATEST = 3;

    /**
     * مكان بطاقة «قياس الرضا» في الصفحة المخزنة. فتح الاستبيان وإغلاقه بموعدهما لا بحفظ سجله،
     * فشرطها يُحسب في كل طلب، فلا تظهر بطاقة إلى 404 ولا تتأخر عن موعدها.
     */
    public const SURVEY_SLOT = '<!-- survey-tile -->';

    public function index(): View
    {
        $html = ContentCache::remember('media.index', fn (): string => view('media.index', [
            'latest' => NewsArticle::query()->published()->newestFirst()->limit(self::LATEST)->get(),
        ])->render());

        $tile = Route::has('surveys.index') && Survey::hasOpenGeneral() ? view('media.partials.survey-tile')->render() : '';
        $html = str_replace(self::SURVEY_SLOT, $tile, $html);

        return view('content.page', ['title' => __('media.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }

    public function partners(): View
    {
        $html = ContentCache::remember('media.partners', fn (): string => view('media.partners', [
            'partners' => Partner::query()->published()->get(),
        ])->render());

        return view('content.page', ['title' => __('partners.title'), 'summary' => null, 'html' => new HtmlString($html)]);
    }
}
