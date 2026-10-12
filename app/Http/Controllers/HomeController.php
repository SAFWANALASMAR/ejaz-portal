<?php

namespace App\Http\Controllers;

use App\Actions\Content\BuildHomePage;
use App\Support\ContentCache;
use App\Support\LegacyPaths;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * الرئيسية: مقاطعها من الإعدادات، مخزنة مؤقتاً نصاً. والرأس والتذييل خارج الذاكرة
 * المؤقتة، لأنهما يتغيران بحالة الدخول.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, BuildHomePage $home): View|RedirectResponse
    {
        // روابط ووردبريس القديمة على الرئيسية (/?p=12) إلى أقرب قسم بتحويل دائم
        if (($legacy = LegacyPaths::forQuery($request->query())) !== null) {
            return redirect($legacy, 301);
        }

        $sections = ContentCache::remember('home', fn (): string => view('home.sections', $home->handle())->render());

        return view('home', ['sections' => new HtmlString($sections)]);
    }
}
