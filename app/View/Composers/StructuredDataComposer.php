<?php

namespace App\View\Composers;

use App\Support\StructuredData;
use Illuminate\View\View;

/**
 * البيانات المنظمة على مستوى الموقع، في رأس كل صفحة بالتخطيط العام (@stack('head')): الجمعية Organization، ومسار
 * التنقل للصفحة الداخلية المفهرسة (الرئيسية ثم الصفحة) ما لم تكتب الصفحة مسارها بأقسامه الأعلى. ويعمل بعد رسم محتوى
 * الصفحة، فما دفعته إلى الرأس موجود عنده.
 */
class StructuredDataComposer
{
    public function compose(View $view): void
    {
        $factory = $view->getFactory();
        $title = $view->getData()['title'] ?? null;
        $noindex = (bool) ($view->getData()['noindex'] ?? false);

        $factory->startPush('head', StructuredData::script(StructuredData::organization())->toHtml());

        if (is_string($title) && $title !== '' && ! $noindex && ! str_contains($factory->yieldPushContent('head'), '"@type":"BreadcrumbList"')) {
            $factory->startPush('head', StructuredData::script(StructuredData::breadcrumbs($title, url()->current()))->toHtml());
        }
    }
}
