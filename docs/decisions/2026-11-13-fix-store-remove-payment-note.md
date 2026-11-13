# حذف سطر الدفع من صفحة المتجر — 13 نوفمبر 2026

**المصدر:** طلب صفوان (13 نوفمبر 2026، 00:00): حذف السطر «الدفع يتم في متجر الجمعية على منصة سلة، ويُفتح في تبويب جديد.» من صفحة «المتجر والتبرعات». الفرع `fix/store-remove-payment-note`.

## ما تقرر

- **حُذف السطر** من `resources/views/store/page.blade.php`، وحُذف مفتاحه `store.page.payment_note` من `lang/ar/store.php`.
- **يبقى زر «ادخل المتجر» وحده** برابط المتجر العام (`StoreUrl::link()`)، بتبويب جديد و`rel="noopener noreferrer"`، وبنصه المخفي لقارئ الشاشة «يُفتح في تبويب جديد» (`store.page.opens_in_new_tab`).
- **والصندوق كله داخل `@if ($storeUrl)`:** فلا يبقى صندوق فارغ إن غاب رابط المتجر.
- يلغي هذا ذكر «سطر الدفع في متجر سلة» في `2026-11-04-store-page.md`، وبقية ذلك القرار كما هي.

## التحقق

- `tests/Feature/Store/StorePageTest.php`: لا يظهر السطر ولا مفتاحه الخام، والزر بنصه المخفي لقارئ الشاشة.
- `tests/Feature/Platform/StoreLinkTest.php` و`tests/browser/store-page.spec.mjs`: الزر برابطه و`target` و`rel` كما كان.
- لقطات `docs/design/screenshots/store-page/` لم تُحدَّث في هذا الفرع (لا بيئة متصفح في جلسة المشرف)، وتُؤخذ مع أول تشغيل لـ `store-page.spec.mjs`.
