<?php

namespace App\Http\Controllers;

use App\Contracts\StoreCatalog;
use App\Enums\StoreItemCategory;
use App\Http\Requests\StoreIndexRequest;
use App\Rules\StoreUrl;
use App\Support\ContentCache;
use App\Support\Store\StoreProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;

/**
 * «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026 — docs/decisions/2026-11-04-store-page.md، ويحل محل تحويل /store إلى سلة):
 * بطاقات التبرعات والإصدارات المنشورة بتبويبات التصنيف، وزر كل بطاقة إلى رابط الشراء في متجر سلة من الكتالوج
 * (StoreCatalog::purchaseUrl) في تبويب جديد، و«ادخل المتجر» برابط المتجر العام. والدفع كله في سلة.
 *
 * وتبويب التصنيف لا يظهر إلا وفيه عنصر منشور، و«الكل» دائماً (ملاحظة المشرف على اللقطات الأولى). والتصنيف بلا عناصر يعود إلى
 * «الكل». وقائمة التصنيفات وشبكة كل تبويب مخزنتان كبقية المحتوى العام، ويمسحهما حفظ أي عنصر.
 */
class StorePageController extends Controller
{
    public function __invoke(StoreIndexRequest $request, StoreCatalog $catalog): View|RedirectResponse
    {
        /** @var list<string> $available */
        $available = ContentCache::remember('store.categories', fn (): array => array_values(array_unique(array_map(
            fn (StoreProduct $product): string => ($product->category ?? StoreItemCategory::Donation)->value,
            $catalog->products(),
        ))));

        $category = StoreItemCategory::tryFrom((string) $request->validated('category'));

        if ($category !== null && ! in_array($category->value, $available, true)) {
            return redirect()->route('store.index');
        }

        $html = ContentCache::remember('store.'.($category->value ?? 'all'), function () use ($catalog, $category): string {
            $products = $catalog->products();
            $shown = array_values(array_filter($products, fn (StoreProduct $product): bool => $category === null || ($product->category ?? StoreItemCategory::Donation) === $category));

            $cards = array_map(fn (StoreProduct $product): array => [
                'product' => $product,
                'category' => $product->category ?? StoreItemCategory::Donation,
                'href' => $catalog->purchaseUrl($product),
            ], $shown);

            return view('store.partials.grid', ['cards' => $cards])->render();
        });

        return view('store.page', [
            'category' => $category,
            // بترتيب التعداد، لا بترتيب ظهورها في العناصر
            'tabs' => array_values(array_filter(StoreItemCategory::cases(), fn (StoreItemCategory $tab): bool => in_array($tab->value, $available, true))),
            'storeUrl' => StoreUrl::link(),
            'grid' => new HtmlString($html),
        ]);
    }
}
