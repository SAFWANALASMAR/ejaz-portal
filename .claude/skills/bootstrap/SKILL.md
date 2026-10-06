---
name: bootstrap
description: تأسيس المشروع لأول مرة بعد تشغيل scripts/setup.sh — تثبيت المكتبات الأساسية وضبط العربية ولوحة الإدارة والصلاحيات والفحص الآلي. يُشغَّل مرة واحدة فقط.
disable-model-invocation: true
---

# تأسيس المشروع (المرحلة 0)

نفّذ الخطوات بالترتيب. بعد كل خطوة تحقق أنها نجحت قبل الانتقال. إذا فشلت خطوة، اشرح السبب لصفوان بالعربية واقترح الحل، ولا تتجاوزها.

استخدم دائماً `docker compose exec -T`. لا طرفية تفاعلية لديك، فالأوامر التي تسأل أسئلة شغّلها بخيار `--no-interaction` أو اطلب من صفوان تشغيلها بنفسه.

## ٠. التحقق من البيئة

- `docker compose ps` — يجب أن تكون app وpgsql وredis تعمل. إن لم تكن، اطلب من صفوان تشغيل `bash scripts/setup.sh` أولاً.
- `git status` — **متوقع** ظهور `composer.lock` و`package-lock.json` غير محفوظين (أنشأهما setup.sh)، وهما يُحفظان في هذه المهمة. أي تعديل آخر غير محفوظ: اسأل قبل المتابعة.
- أنشئ فرعاً: `git switch -c chore/bootstrap`.

## ١. المكتبات

ثبّت **أحدث إصدار مستقر متوافق** دون تثبيت أرقام يدوياً، ثم سجّل الإصدارات المثبتة في `docs/decisions.md`.

```bash
docker compose exec -T app composer require --no-interaction filament/filament laravel/horizon spatie/laravel-permission spatie/laravel-activitylog
docker compose exec -T app composer require --dev --no-interaction -W pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/boost
```

- الخيار `-W` يسمح بمواءمة إصدار PHPUnit مع Pest.
- إن ظهر تعارض، فلا تستخدم `--ignore-platform-reqs`. اعرض المشكلة واقترح حلاً.

## ٢. Pest

```bash
docker compose exec -T app ./vendor/bin/pest --init
```

- تأكد أن `tests/Pest.php` يربط اختبارات Feature بـ `Tests\TestCase` ويستخدم `RefreshDatabase`.
- حوّل الاختبارين المثالين إلى صيغة Pest، أو استبدلهما باختبار: الصفحة الرئيسية ترد 200.
- `docker compose exec -T app php artisan test` يجب أن ينجح (على قاعدة `ejaz_testing`).

## ٣. Larastan

- أنشئ `phpstan.neon` بالمستوى 6، ومسارات `app/` و`config/` و`database/` و`routes/`، مع إضافة larastan.
- `docker compose exec -T app ./vendor/bin/phpstan analyse` يجب أن ينجح دون أخطاء ودون خط أساس.

## ٤. أمر الفحص الكامل

أضف في `composer.json` ضمن scripts:

```json
"check": [
    "./vendor/bin/pint --test",
    "./vendor/bin/phpstan analyse --no-progress --memory-limit=1G",
    "@php artisan test"
]
```

## ٥. العربية والتوقيت

- `docker compose exec -T app php artisan lang:publish`
- أنشئ `lang/ar/`: `validation.php` و`auth.php` و`passwords.php` و`pagination.php`، و`ar.json` للنصوص العامة. ترجمها ترجمة سليمة، وراجعها نصاً نصاً.
- التخزين UTC والعرض بتوقيت الرياض: استخدم `config('app.display_timezone')`.
- تخطيط Blade أساسي في `resources/views/layouts/app.blade.php` بـ `lang="ar" dir="rtl"`، والخط واللون من `docs/design-system.md`. والرئيسية المؤقتة تستخدمه بدل صفحة لارافيل الافتراضية.

## ٦. لوحة الإدارة

```bash
docker compose exec -T app php artisan filament:install --panels --no-interaction
```

- معرّف اللوحة `admin`، والمسار `/admin`.
- اللون الأساسي والخط من `docs/design-system.md`، واللغة العربية والاتجاه من اليمين.
- `FilamentTimezone::set(config('app.display_timezone'))` في مزود الخدمة.
- اسم اللوحة: «جمعية إعجاز — لوحة الإدارة».
- `User` ينفذ `FilamentUser`، و`canAccessPanel()` يسمح فقط لمن لديه دور إداري (انظر `docs/roles-permissions.md`).

## ٧. الصلاحيات وسجل العمليات

- انشر ملفات الإعداد والترحيلات للمكتبتين، وشغّل `docker compose exec -T app php artisan migrate`.
- `User` يستخدم `HasRoles`، ويسجل تعديلاته في سجل العمليات (بلا كلمة المرور والرموز).
- Seeder باسم `RolesSeeder` ينشئ الأدوار الثمانية في `docs/roles-permissions.md`، بلا صلاحيات تفصيلية بعد. تُضاف الصلاحيات مع كل وحدة.
- أمر Artisan: `ejaz:create-admin`.
  - يسأل عن الاسم والبريد وكلمة المرور تفاعلياً، ويطبّق قواعد كلمة المرور في `docs/security.md`، ويمنحه `super_admin`.
  - **لا** حساب مشرف ثابت في Seeder، ولا كلمة مرور في الشيفرة.
- اختبارات:
  - مستخدم بلا دور إداري يُمنع من `/admin`.
  - المشرف يدخل.
  - الأمر ينشئ المشرف ويرفض كلمة مرور ضعيفة.

## ٨. Horizon

- `docker compose exec -T app php artisan horizon:install`
- **انتبه:** Horizon يسمح للجميع في البيئة المحلية افتراضياً. تجاوز `authorization()` في `HorizonServiceProvider` بحيث يطبّق البوابة في كل البيئات: المشرف العام فقط.
- اختبار: مستخدم عادي يُمنع من `/horizon`.
- في `compose.yaml` غيّر أمر خدمة queue إلى `php artisan horizon`، ثم اطلب من صفوان الموافقة على `docker compose up -d queue`.

## ٩. الحماية الأساسية

- Middleware لرؤوس الحماية (انظر `docs/security.md`)، مطبق على مجموعة web، ومتوافق مع Vite في التطوير.
- في `AppServiceProvider`:
  - `Model::shouldBeStrict(! app()->isProduction())`
  - `Password::defaults(...)` بالقواعد في `security.md`
  - فرض HTTPS في الإنتاج.
- اختبار يتحقق من وجود رؤوس الحماية في الاستجابة.

## ١٠. الواجهة

- `docker compose exec -T vite npm run build` يجب أن ينجح.
- إن فشل تحميل الخط من Bunny، فاحفظ ملفات الخط داخل المشروع في `resources/fonts` وحمّلها عبر `@font-face`، وسجّل القرار.

## ١١. الفحص النهائي

```bash
docker compose exec -T app composer check
docker compose exec -T app composer audit
```

يجب أن ينجحا كاملين. ثم:

- اطلب من صفوان تشغيل هذين الأمرين **بنفسه في طرفيته**، لأنهما يسألان أسئلة:
  - `docker compose exec app php artisan boost:install` — يختار Claude Code. بعده راجع `git diff CLAUDE.md`: يجب أن يبقى دستور المشروع كاملاً، وتُضاف إرشادات Boost فقط. وإن حُذف شيء فأعده.
  - `docker compose exec app php artisan ejaz:create-admin` — ثم يفتح `http://localhost:8000/admin` ويتأكد بنفسه.
- حدّث `docs/roadmap.md`: بنود المرحلة 0 ✅.
- احفظ كل شيء ومعه `composer.lock` و`package-lock.json`:
  `git add -A && git commit -m "chore: bootstrap project foundation"`
- اسأل صفوان قبل الرفع: `git push -u origin chore/bootstrap`. ثم يفتح طلب دمج على GitHub، ويدمجه بعد نجاح الفحص الآلي (الذي يعمل كاملاً لأول مرة الآن).

## الملخص لصفوان

بالعربية وبلا جداول:
- ما ثُبّت، وإصداراته.
- ما يجربه بنفسه.
- الخطوة التالية: المرحلة 1، بأمر `/new-feature` على أول بند في `docs/roadmap.md`.
