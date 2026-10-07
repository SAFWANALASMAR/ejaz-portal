<?php

namespace App\Http\Controllers;

use App\Actions\Content\BuildHomePage;
use App\Support\ContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * الرئيسية: مقاطعها من الإعدادات، مخزنة مؤقتاً نصاً. والرأس والتذييل خارج الذاكرة
 * المؤقتة، لأنهما يتغيران بحالة الدخول.
 */
class HomeController extends Controller
{
    public function __invoke(BuildHomePage $home): View
    {
        $sections = ContentCache::remember('home', fn (): string => view('home.sections', $home->handle())->render());

        return view('home', ['sections' => new HtmlString($sections)]);
    }
}
