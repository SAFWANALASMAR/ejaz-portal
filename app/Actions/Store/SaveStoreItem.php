<?php

namespace App\Actions\Store;

use App\Actions\Content\SaveContent;
use App\Models\StoreItem;
use App\Models\User;
use App\Rules\StoreUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * حفظ عنصر «المتجر والتبرعات» من اللوحة: رابطه في سلة https من نطاقات المتجر المسموحة وحدها (StoreUrl)، يُفحص هنا لكل
 * مستدعٍ لا في النموذج وحده. ثم الحفظ العام للمحتوى (SaveContent): الصلاحية، وتنظيف الصورة، وسجل العمليات، ومسح الذاكرة.
 */
class SaveStoreItem
{
    public function __construct(private readonly SaveContent $saveContent) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException رابط من نطاق آخر أو بلا https، أو صورة لا تُقرأ
     */
    public function handle(User $actor, StoreItem $item, array $data): StoreItem
    {
        // الصلاحية قبل فحص المدخل، فلا يعرف من لا صلاحية له شيئاً عن قواعده (وSaveContent يعيد الفحص)
        if (! ($item->exists ? $actor->can('update', $item) : $actor->can('create', StoreItem::class))) {
            throw new AuthorizationException;
        }

        if (array_key_exists('salla_url', $data) || ! $item->exists) {
            $url = $data['salla_url'] ?? null;

            if (! StoreUrl::isAllowed($url)) {
                throw ValidationException::withMessages(['salla_url' => __('store.validation.salla_url', ['hosts' => implode('، ', (array) config('store.allowed_hosts'))])]);
            }
        }

        // ومعرّف المنتج في سلة اختياري، وإن وُجد فحروف لاتينية وأرقام و_ و- حتى 50، هنا لكل مستدعٍ كالنموذج (قرار المشرف على #179، البند 4)
        if (array_key_exists('salla_product_id', $data)) {
            $data['salla_product_id'] = filled($data['salla_product_id']) ? trim((string) $data['salla_product_id']) : null;

            if ($data['salla_product_id'] !== null && preg_match('/^[A-Za-z0-9_-]{1,50}$/', $data['salla_product_id']) !== 1) {
                throw ValidationException::withMessages(['salla_product_id' => __('store.validation.salla_product_id')]);
            }
        }

        return $this->saveContent->handle($actor, $item, $data);
    }
}
