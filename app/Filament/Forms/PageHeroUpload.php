<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\FileUpload;

/**
 * صورة رأس الصفحة في «محرر الصفحات». ليست عموداً في سجل الصفحة بل في خانتها من «محتوى الرئيسية» ونسخ الصفحة، فلا محفوظ
 * باسم الحقل يقبله منع التلاعب بالمسار: المقبول بلا رفع صور الصفحة ونسخها وحدها (allowFilePathUsing في SitePageResource).
 */
final class PageHeroUpload extends FileUpload
{
    /**
     * @return array<string>
     */
    public function getOriginalFilePaths(): array
    {
        return [];
    }
}
