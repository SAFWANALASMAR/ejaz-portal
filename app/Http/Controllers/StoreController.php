<?php

namespace App\Http\Controllers;

use App\Contracts\StoreCatalog;
use App\Models\Setting;
use App\Rules\StoreUrl;
use Illuminate\Contracts\View\View;

/**
 * صفحة المتجر: رابط متجر الجمعية على سلة وبطاقات المنتجات، ولا تظهر حتى تُفعَّل من اللوحة.
 * الروابط لا تُعرض إلا آمنة.
 */
class StoreController extends Controller
{
    public function __invoke(StoreCatalog $catalog): View
    {
        $store = Setting::group('store');

        abort_unless((bool) ($store['enabled'] ?? false), 404);

        $url = $store['url'] ?? null;

        return view('store.index', [
            'intro' => is_string($store['intro'] ?? null) ? $store['intro'] : null,
            'storeUrl' => StoreUrl::isAllowed($url) ? $url : null,
            'products' => $catalog->products(),
        ]);
    }
}
