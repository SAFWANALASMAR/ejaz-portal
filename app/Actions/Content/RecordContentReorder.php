<?php

namespace App\Actions\Content;

use App\Enums\ActivityEvent;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * الترتيب بالسحب في اللوحة يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل هنا ويُمسح
 * به تخزين المحتوى. والصلاحية تفحصها Filament قبل الحفظ (reorder في السياسة، أي update)،
 * وهذا الإجراء يعمل بعده، فلا فحص هنا يمنع شيئاً.
 */
class RecordContentReorder
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int|string, int|string>  $order
     */
    public function handle(string $model, array $order): void
    {
        ContentCache::flush();

        activity('content')
            ->event(ActivityEvent::ContentReordered->value)
            ->withProperties([
                'model' => class_basename($model),
                'order' => array_map('intval', array_values($order)),
            ])
            ->log(ActivityEvent::ContentReordered->value);
    }
}
