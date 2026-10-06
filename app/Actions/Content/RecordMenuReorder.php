<?php

namespace App\Actions\Content;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * إعادة ترتيب القائمة تُحفظ في Filament باستعلام واحد لا يمر بأحداث النموذج.
 * فهذا الإجراء يلتقط الترتيب قبلها، ثم بعدها يمسح الذاكرة المؤقتة ويسجّل الترتيبين.
 * يُسجَّل في الحاوية بنطاق الطلب الواحد (scoped).
 */
class RecordMenuReorder
{
    /** @var list<int> */
    private array $before = [];

    public function capture(): void
    {
        $this->before = MenuItem::query()->orderBy('sort')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int|string, int|string>  $order
     */
    public function record(array $order): void
    {
        Cache::forget(MenuItem::CACHE_KEY);

        activity('content')
            ->event('menu_reordered')
            ->withProperties([
                'old' => $this->before,
                'new' => array_map('intval', array_values($order)),
            ])
            ->log('menu_reordered');
    }
}
