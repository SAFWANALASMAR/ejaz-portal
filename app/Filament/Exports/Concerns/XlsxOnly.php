<?php

namespace App\Filament\Exports\Concerns;

use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Filament\Actions\Exports\Enums\ExportFormat;

/**
 * التصدير بصيغة Excel وحدها، تنزيلاً أيضاً: مسار التنزيل في Filament يقبل ?format=csv لأي تصدير، ومهمة التصدير تكتب
 * أجزاء CSV دائماً، فكان الملف ينزل CSV ولو حُصر التصدير في Excel (الاختبار الأمني 0.14.0).
 */
trait XlsxOnly
{
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public static function getDownloader(ExportFormatInterface $format): Downloader
    {
        abort_unless($format === ExportFormat::Xlsx, 404);

        return $format->getDownloader();
    }
}
