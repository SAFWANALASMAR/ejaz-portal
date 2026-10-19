<?php

namespace App\Console\Commands;

use App\Models\AssistantSettings;
use App\Support\Assistant\AssistantIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * يعيد بناء فهرس المساعد من المحتوى المنشور كله. الفهرس يتبع كل حفظ وحذف من اللوحة، وهذا الأمر لما يتجاوز النماذج:
 * بعد النشر، وبعد استعادة القاعدة أو تعديلها المباشر، وليلياً احتياطاً (routes/console.php).
 */
class RebuildAssistantIndex extends Command
{
    protected $signature = 'ejaz:assistant-index';

    protected $description = 'إعادة بناء فهرس المساعد الذكي من المحتوى المنشور';

    public function handle(): int
    {
        $count = AssistantIndex::rebuild();
        Cache::forget(AssistantSettings::CACHE_KEY);

        $this->info("بُني فهرس المساعد: {$count} مقطعاً.");

        return self::SUCCESS;
    }
}
