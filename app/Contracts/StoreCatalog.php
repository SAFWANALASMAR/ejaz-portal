<?php

namespace App\Contracts;

use App\Support\Store\StoreProduct;

/**
 * منتجات المتجر المعروضة في صفحة «المتجر والتبرعات» (docs/decisions/2026-11-04-store-page.md). التطبيق الحالي عناصر تُدخل من
 * اللوحة (ManualStoreCatalog)، وبطاقات إعدادات الموقع للمتجر الداخلي المؤرشف (SettingsStoreCatalog).
 *
 * والدفع في متجر الجمعية على سلة دائماً. وأزرار الصفحة تأخذ رابطها من purchaseUrl لا من رابط المنتج مباشرة، فيحل محل
 * التطبيق الحالي لاحقاً تطبيق لواجهة Checkout في سلة (SallaStoreCatalog) بلا تغيير في الصفحة: ينشئ سلة ضيف بمعرّف المتجر،
 * ويضيف إليها المنتج بمعرّفه في سلة (StoreProduct::$sallaProductId) وكميته، ويعيد رابط الدفع checkout_url. ولا اتصال بسلة الآن.
 */
interface StoreCatalog
{
    /**
     * @return list<StoreProduct>
     */
    public function products(): array;

    /**
     * رابط شراء المنتج أو التبرع به: صفحته في سلة الآن، ورابط دفع سلة الضيف بعد الربط الآلي. وnull إن لم يكن له رابط آمن،
     * فلا يظهر الزر.
     */
    public function purchaseUrl(StoreProduct $product, int $quantity = 1): ?string;
}
