<?php

namespace App\Support\Store;

use App\Rules\SafeUrl;

/**
 * منتج في صفحة المتجر: الاسم، ووصف قصير، ورابطه في متجر سلة. والرابط لا يُعرض إلا آمناً.
 */
final readonly class StoreProduct
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $url,
    ) {}

    public function safeUrl(): ?string
    {
        return SafeUrl::isSafe($this->url, allowInternal: false) ? $this->url : null;
    }
}
