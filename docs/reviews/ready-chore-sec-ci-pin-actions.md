# جاهز للمراجعة — chore/sec-ci-pin-actions

**الحالة: بانتظار الفحص الآلي ومراجعة المشرف** (14 أكتوبر 2026). الفرع من `origin/main`. البند 8 من «إصلاحات أمنية — المجموعة ١ (تحصين)».

## ما تغيّر

- كل `uses:` في `.github/workflows/ci.yml` صار ببصمة الالتزام الكاملة وبعده الوسم تعليقاً:
  - `actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7`
  - `shivammathur/setup-php@eb7c497e18156a6bbabfef1d3a82760b9eda3962 # v2`
  - `actions/setup-node@949feb2413d6458794dcd2491c4babbbce0c15c1 # v7`
  - `actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9 # v6`
- البصمات من `git ls-remote` للمستودع الرسمي على الوسم المستعمل نفسه، فلا تغيير في الإصدار. ووسم `setup-php` معلَّق، فالمأخوذ بصمة الالتزام (`v2^{}`) لا بصمة كائن الوسم. وتحققتُ أن كل بصمة التزام موجود عبر واجهة GitHub (200).
- `.github/dependabot.yml` كان يتابع `github-actions` أسبوعياً، فلم يتغير.
- قرار في `docs/decisions.md` (14 أكتوبر).

## الاختبار

`tests/Feature/Platform/CiActionsPinningTest.php` (اختباران):
- يقرأ كل ملفات `.github/workflows/*.yml` ويشترط في كل `uses:` (عدا المحلي `./` و`docker://`) بصمة من 40 محرفاً ستة عشرياً وتعليق وسم بعدها.
- يشترط أن يتابع `dependabot.yml` منظومة `github-actions`.
- فشل قبل الإصلاح (`actions/checkout@v7`) ونجح بعده.

## الفحص

- Pint: ناجح.
- PHPStan: بلا أخطاء.
- الاختبارات كاملة: 1870 ناجحاً (8578 تحققاً)، بلا فشل، بعمليتين متوازيتين.

## ما بقي

- عند إضافة إجراء جديد يُثبَّت ببصمته، والاختبار يمنع غير ذلك.
