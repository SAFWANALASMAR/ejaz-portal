# جاهز للمراجعة — `chore/sec-ci-npm-audit-dev`

التاريخ: 14 أكتوبر 2026. الفرع من `origin/main` عند `54679b4`. البند ٧ من «مهمة إصلاحات أمنية ١ — تحصين»: تدقيق مكتبات الواجهة كان يستثني أدوات البناء.

## ما فيه

1. **خطوة جديدة في `.github/workflows/ci.yml`:** «تدقيق مكتبات الواجهة كاملة (تقريري)».
   - الأمر `npm audit --audit-level=moderate` بلا `--omit=dev`، فيشمل أدوات البناء والتطوير.
   - `continue-on-error: true`: نتيجتها في السجل، ولا توقف الفحص.
   - بالشرط نفسه لبقية الخطوات (`steps.boot.outputs.ready == 'true'`).
2. **الفحص المانع كما هو:** `composer audit` و`npm audit --omit=dev --audit-level=high`.
3. **قرار في `docs/decisions.md`** (14 أكتوبر): لماذا تقريرية لا مانعة.

## الاختبار المانع

`tests/Feature/Platform/CiDependencyAuditTest.php` يقرأ `ci.yml`:

- `keeps the blocking production npm audit at high severity`: الفحص المانع باقٍ مع `composer audit` وبلا `continue-on-error`.
- `reports every npm package including build tools at moderate severity without blocking`: خطوة واحدة بـ `--audit-level=moderate`، ولا `--omit` فيها، وفيها `continue-on-error: true` وشرط التأسيس.

فشل الاختبار الثاني قبل الإصلاح (لم توجد الخطوة)، ونجح بعده.

## نتيجة الفحص

- Pint: ناجح (945 ملفاً). PHPStan: صفر أخطاء.
- الاختبارات: 1870 اختباراً ناجحة (8579 تأكيداً)، بلا فشل ولا تخطٍّ، بعمليتين متوازيتين على `ejaz_testing_sec7`.
- `npm audit --audit-level=moderate` محلياً على ملف القفل الحالي (حاوية node:22): لا ثغرات.

## ما بقي

- لا شيء في هذا البند. والبندان ٣ و٨ يمسان `ci.yml` نفسه في فرعيهما، فقد يلزم حل تعارض بسيط عند الدمج.
