<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Rules\StoreUrl;
use Illuminate\Http\RedirectResponse;

/**
 * /store يحوّل إلى متجر الجمعية على سلة (قرار صفوان)، بالرابط المضبوط في الإعدادات إن صح.
 */
class StoreRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->away(StoreUrl::current(Setting::group('store')['url'] ?? null));
    }
}
