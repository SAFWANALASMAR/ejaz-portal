<?php

namespace App\Services\Store;

use App\Contracts\StoreCatalog;
use App\Models\StoreItem;
use App\Rules\StoreUrl;
use App\Support\Store\StoreProduct;

/**
 * عناصر «المتجر والتبرعات» كما أدخلها المسؤول في اللوحة (StoreItemResource): المنشورة بترتيبها، وما خرج رابطه عن نطاقات
 * المتجر المسموحة لا يُعرض. ورابط الشراء صفحة المنتج في سلة حتى يُبنى الربط الآلي (StoreCatalog).
 */
class ManualStoreCatalog implements StoreCatalog
{
    public function products(): array
    {
        return StoreItem::query()->published()->ordered()->get()
            ->map(fn (StoreItem $item): ?StoreProduct => $item->toProduct())
            ->filter()
            ->values()
            ->all();
    }

    public function purchaseUrl(StoreProduct $product, int $quantity = 1): ?string
    {
        return StoreUrl::isAllowed($product->url) ? $product->url : null;
    }
}
