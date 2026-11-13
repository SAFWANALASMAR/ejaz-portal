# الرجوع برابط العرض إلى نسخة سابقة

نقطة رجوع ثابتة قبل كل تحديث للعرض (قرار صفوان، 30 أكتوبر 2026: «نقطة رجوع»). الرجوع يأخذ دقائق، ولا يغيّر الرابط لأنه لا يمس النفق ولا الوكيل.

## أين يعيش العرض

- **الشيفرة:** شجرة العمل `..\ejaz-demo` (بلا فرع، على وسم أو إيداع)، ومشروع compose اسمه `ejaz-demo`.
- **التشغيل والنسخ:** المجلد `..\ejaz-demo-ops` خارج git. فيه الإعدادات والأسرار، وطريقة التحديث في `README.md` هناك.
- **صورة PHP:** `ejaz-php:demo`. وقبل كل تحديث تُوسم الصورة الجارية باسم الإيداع: `ejaz-php:demo-rollback-<الإيداع أو الوسم>`.

## نقطة الرجوع المستقرة

- **الوسم `v0.14.2`** (`f0df0a97`) على `main`، ونسخة العرض قبل تحويله إلى ما بعده في `ejaz-demo-ops\backup-2026-10-30-pre-7429dbb4\`.
- **وكل تحديث للعرض** يُسبق بمجلد `backup-<التاريخ>-pre-<الإيداع>\` فيه خمسة ملفات:
  - `HEAD.txt`: الإيداع الذي يُرجع إليه.
  - `ejaz.dump`: نسخة القاعدة (`pg_dump -Fc`).
  - `env.backup`: نسخة `.env`.
  - `public-build.tgz`: الواجهة المبنية.
  - `storage-app.tgz`: الملفات المرفوعة (`storage/app`).
- **ومعها `rollback.sh`** يعيد العرض إلى ذلك الإيداع بكل ما سبق.

## الرجوع إلى آخر نسخة (الحالة المعتادة)

من Git Bash:

```bash
cd ../ejaz-demo-ops
ls -d backup-*            # أحدث مجلد قبل التحديث الذي تريد التراجع عنه
bash backup-<المجلد>/rollback.sh
```

يوقف `app` و`queue` و`scheduler`، ثم يعيد بالترتيب:
1. الشيفرة إلى الإيداع في `HEAD.txt`.
2. `.env`.
3. صورة PHP من وسمها.
4. المكتبات.
5. الواجهة المبنية والملفات.
6. القاعدة من النسخة.

ثم يمسح الذاكرة ويحدّث المحتوى (`ejaz:refresh-content`)، ويشغّل الخدمات الثلاث، ويطبع الإيداع الذي رجع إليه.

## الرجوع إلى وسم بلا مجلد نسخة له

إن لم يكن للوسم مجلد نسخة، فالشيفرة والصورة ترجعان، والقاعدة بأحد طريقين أدناه. وكل الأوامر بـ `MSYS_NO_PATHCONV=1`، داخل `..\ejaz-demo`:

1. **نسخة طازجة أولاً:**
   ```bash
   mkdir -p ../ejaz-demo-ops/backup-<التاريخ>-pre-rollback
   docker exec ejaz-demo-pgsql-1 pg_dump -U ejaz -d ejaz -Fc > ../ejaz-demo-ops/backup-<التاريخ>-pre-rollback/ejaz.dump
   ```
2. **إيقاف الخدمات:** `docker compose stop app queue scheduler`.
3. **القاعدة، قبل تبديل الشيفرة** (ترحيلات ما بعد الوسم موجودة في الشيفرة الحالية وحدها، فلو بُدّلت الشيفرة أولاً لطبع `migrate:rollback` «Migration not found» وبقي المخطط الجديد مع شيفرة قديمة):
   - **(أ) إن كانت ترحيلات ما بعد الوسم تُرجع بأمان** (لها `down()` لا يحذف بيانات): بالشيفرة الحالية
     `docker compose run --rm -T --no-deps app php artisan migrate:rollback --step=<عددها> --force`.
     وعددها من `php artisan migrate:status`: الترحيلات بعد آخر ترحيل في الوسم.
   - **(ب) وإلا فالطريق الوحيد الاستعادة من أقرب نسخة قبل الوسم** (أحدث مجلد `backup-*` تاريخه قبل تحويل العرض إلى ما بعد الوسم)، بأوامر `pg_restore` في `rollback.sh`. **وما أُدخل في العرض بعد تلك النسخة يضيع**، فيُعاد بسكربته في `ejaz-demo-ops`.
4. **الشيفرة:** `git fetch origin --tags`، ثم `git checkout --detach <الوسم>`.
5. **الصورة:** `docker tag ejaz-php:demo-rollback-<الوسم> ejaz-php:demo` إن وُجدت، وإلا `docker compose build --pull app`.
6. **المكتبات والواجهة:** `docker compose run --rm -T --no-deps app composer install --no-interaction`، ثم `docker compose run --rm -T --no-deps vite sh -c "npm ci && npm run build"`.
7. **الذاكرة والتشغيل:** بلا `optimize:clear` ولا `cache:clear` (يصفّران قفل الدخول وحدود الرسائل ويبطلان رموز التحقق المعلّقة، `security.md`)، كما في `scripts/deploy-compose.sh`:
   `docker compose run --rm -T --no-deps app sh -c "php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan ejaz:refresh-content"`، ثم `docker compose up -d --no-deps app queue scheduler`.

## بعد الرجوع
- **الرجوع إلى ما قبل Filament 5.11 (`v0.15.8` فأقدم):** الجلسة التي كتبها 5.11 لا يقرؤها 5.10، فترد اللوحة 500 لصاحبها (`docs/decisions/2026-11-11-chore-deps-nov11.md`). فتُبطَل الجلسات القائمة بعد الرجوع:
  - **والطريقة الآمنة** (مراجعة المشرف على #246):
    - يُغيَّر `SESSION_COOKIE` في ملف البيئة (`.env` الخادم أو العرض) إلى اسم جديد، مثل لاحقة بالتاريخ. ثم يُعاد إنشاء حاوية `app` (`docker compose up -d --no-deps app queue scheduler`، أو `scripts/deploy-compose.sh`).
    - فيتجاهل التطبيق الكعكة القديمة، ويبدأ كل زائر جلسة جديدة، ويسجّل الدخول مرة أخرى.
    - **لا `FLUSHDB` ولا `FLUSHALL` في Redis:** الجلسات على قاعدة Redis نفسها مع الطوابير، فالمسح يضيّع المهام المنتظرة (البريد، والشهادات، والتقارير).

- **التحقق:** `node ../ejaz-demo-ops/verify.mjs` في حاوية Playwright (طريقته في `README.md` هناك)، و`php artisan ejaz:health`.
- **السجل:** سطر في `ejaz-demo-ops\README.md` (التاريخ، والإيداع، والسبب)، وفي `docs/progress-log.md`.
- **الرابط** في `ejaz-demo-ops\current-host.txt`، ولا يتغير ما لم يُعَد تشغيل النفق.

## ما لا يفعله الرجوع

- **لا يمس النفق ولا الوكيل** ولا كلمة مرور بوابة العرض.
- **ولا يرجع ما أُدخل في العرض بعد النسخة:** القاعدة تعود إلى لحظة النسخ. فما أُضيف بعدها (حساب أو محتوى) يُعاد إدخاله بسكربته في `ejaz-demo-ops`.
