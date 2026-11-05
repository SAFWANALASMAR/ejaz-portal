<?php

namespace App\Support\Store;

use App\Enums\StoreItemCategory;
use App\Rules\SafeUrl;

/**
 * منتج في صفحة المتجر: الاسم، ووصف قصير، ورابطه في متجر سلة. والرابط لا يُعرض إلا آمناً.
 * ومعها لصفحة «المتجر والتبرعات» (4 نوفمبر 2026): رابط الصورة العام، والتصنيف (تبرع أو إصدار)، ومعرّف المنتج في سلة للربط
 * الآلي لاحقاً (StoreCatalog::purchaseUrl). وكلها اختيارية، فيبقى كتالوج الإعدادات المؤرشف كما هو.
 */
final readonly class StoreProduct
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $url,
        public ?string $image = null,
        public ?StoreItemCategory $category = null,
        public ?string $sallaProductId = null,
    ) {}

    public function safeUrl(): ?string
    {
        return SafeUrl::isSafe($this->url, allowInternal: false) ? $this->url : null;
    }
}
