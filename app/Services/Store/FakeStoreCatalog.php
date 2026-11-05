<?php

namespace App\Services\Store;

use App\Contracts\StoreCatalog;
use App\Support\Store\StoreProduct;
use Closure;

/**
 * للاختبارات: منتجات ثابتة، فتُختبر صفحة المتجر مستقلة عن مصدرها. ورابط الشراء من دالة يمررها الاختبار إن شاء، فيثبت أن
 * الصفحة تأخذ أزرارها من purchaseUrl، وإلا رابط المنتج الآمن.
 */
class FakeStoreCatalog implements StoreCatalog
{
    /**
     * @param  list<StoreProduct>  $products
     * @param  (Closure(StoreProduct, int): ?string)|null  $purchaseUrl
     */
    public function __construct(private readonly array $products, private readonly ?Closure $purchaseUrl = null) {}

    public function products(): array
    {
        return $this->products;
    }

    public function purchaseUrl(StoreProduct $product, int $quantity = 1): ?string
    {
        return $this->purchaseUrl === null ? $product->safeUrl() : ($this->purchaseUrl)($product, $quantity);
    }
}
