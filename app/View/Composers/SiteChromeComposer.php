<?php

namespace App\View\Composers;

use App\Models\MenuItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * يزوّد الرأس والتذييل بالقائمة الرئيسية وبيانات التواصل، من الذاكرة المؤقتة.
 */
class SiteChromeComposer
{
    public function compose(View $view): void
    {
        $view->with('siteMenu', Cache::rememberForever(MenuItem::CACHE_KEY, fn () => MenuItem::query()
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->with(['children' => fn ($query) => $query->where('is_visible', true)])
            ->orderBy('sort')
            ->get()));

        $view->with('siteContact', Setting::group('contact'));
    }
}
