---
name: bootstrap
description: تأسيس المشروع لأول مرة بعد تشغيل scripts/setup.sh — تثبيت المكتبات الأساسية وضبط العربية ولوحة الإدارة والصلاحيات والفحص الآلي. يُشغَّل مرة واحدة فقط.
disable-model-invocation: true
---

# تأسيس المشروع (المرحلة 0)

نفّذ الخطوات بالترتيب. بعد كل خطوة تحقق أنها نجحت قبل الانتقال. إذا فشلت خطوة، اشرح السبب لصفوان بالعربية واقترح الحل، ولا تتجاوزها.

## ٠. التحقق من البيئة

- `docker compose ps` — يجب أن تكون app وpgsql وredis تعمل. إن لم تكن، اطلب من صفوان تشغيل `bash scripts/setup.sh` أولاً.
- `git status` — المستودع موجود، ولا تعديلات غير محفوظة. إن وُجدت تعديلات فاسأل قبل المتابعة.
- أنشئ فرعاً: `git switch -c chore/bootstrap`.

## ١. المكتبات

ثبّت **أحدث إصدار مستقر متوافق** دون تثبيت أرقام يدوياً، ثم سجّل الإصدارات التي ثُبّتت في `docs/decisions.md`.

```bash
docker compose exec app composer require filament/filament laravel/horizon spatie/laravel-permission spatie/laravel-activitylog
docker compose exec app composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/boost
```

إن ظهر تعارض، فلا تستخدم `--ignore-platform-reqs`. اعرض المشكلة واقترح حلاً.

## ٢. Laravel Boost

```bash
docker compose exec app php artisan boost:install
```

اختر Claude Code عند السؤال. بعدها تأكد أن محتوى `CLAUDE.md` الأصلي (دستور المشروع) **ما زال موجوداً كاملاً**، وأن إرشادات Boost أُضيفت إليه ولم تستبدله. راجع ذلك بـ `git diff CLAUDE.md`، وإن حُذف شيء فأعده.

## ٣. Pest

- حوّل الاختبارين المثالين إلى صيغة Pest، أو احذفهما واكتب اختباراً بديلاً: الصفحة الرئيسية ترد 200.
- `docker compose exec app php artisan test` يجب أن ينجح.

## ٤. Larastan

- أنشئ `phpstan.neon` بالمستوى 6، ومسارات `app/` و`config/` و`database/` و`routes/`.
- `docker compose exec app ./vendor/bin/phpstan analyse` يجب أن ينجح دون أخطاء.

## ٥. أمر الفحص الكامل

أضف في `composer.json` ضمن scripts:

```json
"check": [
    "./vendor/bin/pint --test",
    "./vendor/bin/phpstan analyse --no-progress --memory-limit=1G",
    "@php artisan test"
]
```

## ٦. العربية

- `docker compose exec app php artisan lang:publish`
- أنشئ `lang/ar/` بالترجمات الأساسية (التحقق، والمصادقة، وكلمات المرور، والترقيم). ترجمها ترجمة سليمة، وتحقق منها نصاً نصاً.
- `config/app.php`: اللغة `ar` (عبر `.env` الموجود)، والتوقيت `Asia/Riyadh` (موجود).
- تخطيط Blade أساسي في `resources/views/layouts/app.blade.php` بـ `lang="ar" dir="rtl"`.

## ٧. لوحة الإدارة

```bash
docker compose exec app php artisan filament:install --panels
```

- معرّف اللوحة `admin`، والمسار `/admin`.
- اللون الأساسي من `docs/design-system.md`، والخط نفسه، والاتجاه من اليمين.
- اسم اللوحة: «جمعية إعجاز — لوحة الإدارة».
- `User` ينفذ `FilamentUser`، و`canAccessPanel()` يسمح فقط لمن لديه دور إداري (انظر `docs/roles-permissions.md`).

## ٨. الصلاحيات وسجل العمليات

- انشر ملفات الإعداد والترحيلات للمكتبتين، وشغّل `migrate`.
- `User` يستخدم `HasRoles`.
- Seeder باسم `RolesSeeder` ينشئ الأدوار الثمانية في `docs/roles-permissions.md` بلا صلاحيات تفصيلية بعد. تُضاف الصلاحيات مع كل وحدة.
- أمر Artisan: `php artisan ejaz:create-admin`. يسأل عن الاسم والبريد وكلمة المرور تفاعلياً، ويمنحه `super_admin`. **لا** حساب مشرف ثابت في Seeder، ولا كلمة مرور في الشيفرة.
- اختبارات:
  - مستخدم بلا دور إداري يُمنع من `/admin` (403).
  - المشرف يدخل.
  - الأمر ينشئ المشرف.

## ٩. Horizon

- `php artisan horizon:install`.
- اللوحة `/horizon` للمشرف العام فقط (تعديل `gate` في `HorizonServiceProvider`).
- في `compose.yaml` غيّر أمر خدمة queue إلى `php artisan horizon`، وأعد تشغيلها.

## ١٠. الحماية الأساسية

- Middleware لرؤوس الحماية (انظر `docs/security.md`)، مطبق على مجموعة web.
- `Model::preventLazyLoading(! app()->isProduction())` و`Model::shouldBeStrict()` في `AppServiceProvider`.
- `Password::defaults()` بالقواعد في `security.md`.
- اختبار يتحقق من وجود رؤوس الحماية في الاستجابة.

## ١١. الواجهة

- `docker compose exec vite npm install` ثم `npm run build`، ويجب أن ينجح.
- **احفظ `package-lock.json` في قيت** — الفحص الآلي يحتاجه (`npm ci`).

## ١٢. الفحص النهائي

```bash
docker compose exec app composer check
```

يجب أن ينجح كاملاً. ثم:

- اطلب من صفوان إنشاء حسابه بـ `docker compose exec app php artisan ejaz:create-admin`، وفتح `http://localhost:8000/admin` للتأكد بنفسه.
- حدّث `docs/roadmap.md`: بنود المرحلة 0 ✅.
- احفظ: `git add -A && git commit -m "chore: bootstrap project foundation"`.
- اسأل صفوان قبل الرفع: `git push -u origin chore/bootstrap`. ثم يدمجه بعد نجاح الفحص الآلي على GitHub.

## الملخص لصفوان

بالعربية وبلا جداول:
- ما ثُبّت، وإصداراته.
- ما يجربه بنفسه.
- الخطوة التالية: المرحلة 1، بأمر `/new-feature` على أول بند.
