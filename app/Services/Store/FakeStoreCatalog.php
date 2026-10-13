<?php

namespace App\Services\Store;

use App\Contracts\StoreCatalog;
use App\Support\Store\StoreProduct;

/**
 * للاختبارات: منتجات ثابتة، فتُختبر صفحة المتجر مستقلة عن مصدرها.
 */
class FakeStoreCatalog implements StoreCatalog
{
    /**
     * @param  list<StoreProduct>  $products
     */
    public function __construct(private readonly array $products) {}

    public function products(): array
    {
        return $this->products;
    }
}
