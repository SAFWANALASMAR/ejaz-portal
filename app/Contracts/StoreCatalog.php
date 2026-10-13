<?php

namespace App\Contracts;

use App\Support\Store\StoreProduct;

/**
 * منتجات المتجر المعروضة في صفحة المتجر. التطبيق الحالي بطاقات تُدخل من إعدادات الموقع،
 * وتحل محله واجهة سلة البرمجية حين تتوفر صلاحية الربط (docs/open-questions.md).
 */
interface StoreCatalog
{
    /**
     * @return list<StoreProduct>
     */
    public function products(): array;
}
