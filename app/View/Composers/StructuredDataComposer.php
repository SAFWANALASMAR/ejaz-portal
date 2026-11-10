<?php

namespace App\View\Composers;

use App\Support\StructuredData;
use Illuminate\View\View;

/**
 * البيانات المنظمة على مستوى الموقع، في رأس كل صفحة مفهرسة بالتخطيط العام (@stack('head')): الجمعية Organization، والموقع WebSite في الرئيسية،
 * ومسار التنقل للصفحة الداخلية (الرئيسية ثم الصفحة) ما لم تكتب الصفحة مسارها بأقسامه الأعلى. ويعمل بعد رسم محتوى
 * الصفحة، فما دفعته إلى الرأس موجود عنده.
 */
class StructuredDataComposer
{
    public function compose(View $view): void
    {
        $factory = $view->getFactory();
        $title = $view->getData()['title'] ?? null;
        $noindex = (bool) ($view->getData()['noindex'] ?? false);

        // والصفحة غير المفهرسة (الدخول والحساب ورابط معرض الشواهد السري) بلا بيانات منظمة
        if ($noindex) {
            return;
        }

        $factory->startPush('head', StructuredData::script(StructuredData::organization())->toHtml());

        if (request()->routeIs('home')) {
            $factory->startPush('head', StructuredData::script(StructuredData::website())->toHtml());
        }

        if (is_string($title) && $title !== '' && ! str_contains($factory->yieldPushContent('head'), '"@type":"BreadcrumbList"')) {
            $factory->startPush('head', StructuredData::script(StructuredData::breadcrumbs($title, url()->current()))->toHtml());
        }
    }
}
