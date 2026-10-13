<?php

namespace App\Services\Store;

use App\Contracts\StoreCatalog;
use App\Models\Setting;
use App\Support\Store\StoreProduct;

/**
 * بطاقات المنتجات كما أدخلها محرر المحتوى في إعدادات الموقع (مجموعة store).
 */
class SettingsStoreCatalog implements StoreCatalog
{
    public function products(): array
    {
        $products = [];

        foreach ((array) (Setting::group('store')['products'] ?? []) as $row) {
            if (is_array($row) && is_string($row['name'] ?? null) && is_string($row['url'] ?? null)) {
                $products[] = new StoreProduct($row['name'], is_string($row['description'] ?? null) ? $row['description'] : null, $row['url']);
            }
        }

        return $products;
    }
}
