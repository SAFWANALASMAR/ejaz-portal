# دليل النشر في الإنتاج والتجريبية

المرجع: بنود الإنتاج في المرحلة 1 من `roadmap.md`، و`security.md`، و`performance.md`، و`decisions.md` (11 أكتوبر 2026: تأجيل اختيار الخادم حتى يكتمل المشروع، ولا اعتماد على خدمة مُدارة).

> **الحال:** كل ما هنا جاهز للتنفيذ، ولا حساب سحابي ولا خادم بعد. المزود يُختار بقائمة `deploy/provider-checklist.md` (والمرشح المسجَّل أوراكل كلاود في جدة والرياض)، والتجربة أولاً على الخادم التجريبي (`deploy/staging-plan.md`). كل قيمة بين `<...>` تُضبط عند الاختيار. ولا سر في قيت: الأسرار في مخزن أسرار المزود وفي `.env` على الخادم وحده.

## الملفات

- `deploy/compose.production.yaml` — الحزمة: التطبيق (PHP-FPM)، وnginx، وHorizon، والمجدول، وPostgreSQL 17، وRedis 7، والنسخ الاحتياطي.
- `deploy/docker/` — صورة الإنتاج (`Dockerfile` بمراحل: التطبيق، والويب، والنسخ)، ونقطة الدخول، وإعداد PHP-FPM، وnginx للحاوية.
- `.env.production.example` و`.env.backup.example` — قالبا الإعداد بلا أسرار.
- `scripts/deploy-compose.sh` — النشر بوسم.
- `scripts/backup.sh` و`scripts/restore.md` — النسخ المشفّر خارج الخادم والاستعادة وتجربتها.
- `deploy/systemd/compose/` — تشغيل الحزمة عند الإقلاع، والنسخ اليومي، وفحص الصحة، مع المؤقتات في `deploy/systemd/`.
- `deploy/storage/` — CORS ودورة الحياة لحاويات S3.
- `deploy/php/production.ini` — قيم PHP للإنتاج (داخل الصورة).
- `docs/monitoring.md`، و`docs/cutover.md` (نقل النطاق والتراجع)، و`docs/launch-checklist.md`.
- **بلا حاويات (بديل):** `scripts/deploy.sh`، و`deploy/nginx/ejaz.conf`، و`deploy/redis/redis.conf`، ووحدات `deploy/systemd/*.service` — في الملحق آخر الدليل.

## البنية

- **خادم لينكس واحد داخل المملكة** بالحزمة كلها (المواصفات في `deploy/provider-checklist.md`)، ويُرفع حجمه مؤقتاً للذروة.
- **تخزين كائنات متوافق مع S3 في المنطقة نفسها:** حاوية للملفات الخاصة ومقاطع المسابقات (`FILESYSTEM_PRIVATE_DISK=s3`)، وحاوية منفصلة للنسخ.
- **القاعدة والذاكرة على الخادم** (ملف التعريف `bundled`)، بحجمين دائمين (`pgdata` و`redisdata`). والانتقال إلى خدمة مُدارة لاحقاً بمتغيرات البيئة وحدها (القسم «الانتقال إلى خدمة مُدارة»).
- **التطبيق بلا حالة محلية** إلا القرص العام والسجلات في الحجم `storage`.

## أولاً: تجهيز الخادم (مرة واحدة)

1. Ubuntu LTS (أو ما يعادله)، وتحديثاته الأمنية آلية (`unattended-upgrades`).
2. جدار ناري (المزود والخادم): 22 من عناوين الإدارة وحدها، و80 و443 للعموم. ولا منفذ للقاعدة ولا لـ Redis (شبكة الحاويات داخلية).
3. الدخول بمفاتيح SSH وحدها، ولا دخول بكلمة مرور ولا بـ root.
4. Docker Engine وإضافة compose من مستودع Docker الرسمي.
5. قرص البيانات مركّب على `/var/lib/docker` (أو مجلد Docker معدّل إليه)، فتكون القاعدة وRedis والتخزين عليه، ولقطاته مجدولة.
6. المشروع:
   ```bash
   sudo mkdir -p /opt/ejaz && sudo chown "$USER" /opt/ejaz
   git clone <المستودع> /opt/ejaz      # بمفتاح نشر للقراءة وحدها
   cd /opt/ejaz
   cp .env.production.example .env && chmod 600 .env          # ثم تُملأ القيم (ثانياً)
   cp .env.backup.example .env.backup && chmod 600 .env.backup
   ```
7. الشهادة: `/etc/ejaz/tls/fullchain.pem` و`privkey.pem`. بـ Let's Encrypt (certbot على الخادم بتحدي HTTP في `/var/lib/ejaz/acme`، أو تحدي DNS قبل نقل النطاق — `cutover.md`)، ثم إعادة تحميل حاوية الويب بعد كل تجديد: `docker compose --env-file .env -f deploy/compose.production.yaml exec web nginx -s reload`.
8. مفتاح النسخ العام: `/etc/ejaz/backup-public.asc` (المفتاح الخاص لا يوضع على الخادم — `scripts/restore.md`).
9. الوحدات:
   ```bash
   sudo cp deploy/systemd/compose/*.service /etc/systemd/system/
   sudo cp deploy/systemd/ejaz-backup.timer deploy/systemd/ejaz-health.timer /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable ejaz-stack
   sudo systemctl enable --now ejaz-backup.timer ejaz-health.timer
   ```
10. حاويتا التخزين: الإنشاء خاصة، ثم CORS والإصدارات ودورة الحياة (`deploy/storage/README.md`).

## ثانياً: ملف `.env` للإنتاج

من `.env.production.example`، وتُملأ الفارغة من مخزن الأسرار:

- `APP_KEY`: يُولَّد مرة ويُحفظ في المخزن: `docker compose --env-file .env -f deploy/compose.production.yaml run --rm --no-deps app php artisan key:generate --show`.
- `APP_URL=https://ejaz.sa`، و`APP_DEBUG=false`، و`SESSION_SECURE_COOKIE=true`.
- `SEO_INDEXABLE=false` حتى يوم نقل النطاق، ثم `true` في الإنتاج وحده.
- `TRUSTED_PROXIES`: عناوين موزع الأحمال إن وُجد، وفارغة إن وصل الزوار مباشرة (`security.md`).
- `DB_PASSWORD` و`REDIS_PASSWORD`: طويلة عشوائية (`openssl rand -base64 32`).
- التخزين بواجهة S3 القياسية: `AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` و`AWS_BUCKET` والمفتاحان، و`CSP_CONNECT_SRC` بعنوان التخزين للرفع المباشر.
- `MAIL_*` لمزود البريد، و`SMS_DRIVER` بعد اعتماد المزود (`open-questions.md`)، و`SMS_PER_HOUR` حسب التسجيل المتوقع (`performance.md`).
- ضبط الذاكرة: `PG_*` و`REDIS_MAXMEMORY` و`FPM_MAX_CHILDREN` حسب حجم الخادم (`deploy/provider-checklist.md`).
- ما يلزمه قرار أو حساب: Turnstile، وMicrosoft Graph، والدفع (`PAYMENTS_ENABLED=false`). انظر «عند كل نشر» في `security.md`.

ولـ `.env.backup`: اتصال القاعدة (`PGHOST=pgsql`)، وحاوية النسخ ومفتاحاها، و`BACKUP_GPG_RECIPIENT`.

## ثالثاً: أول نشر

```bash
cd /opt/ejaz
bash scripts/deploy-compose.sh v1.0.0
DC="docker compose --env-file .env -f deploy/compose.production.yaml"
$DC exec app php artisan ejaz:create-admin      # المشرف الأول، تفاعلي
$DC exec -T app php artisan db:seed --class=MenuSeeder --force
$DC exec -T app php artisan db:seed --class=ContentSeeder --force
$DC exec -T app php artisan db:seed --class=ProgramTypesSeeder --force
$DC exec -T app php artisan db:seed --class=LegacyRedirectsSeeder --force
$DC cp <مجلد الاستيراد بملف JSON وملفاته> app:/tmp/legacy
$DC exec -T app php artisan import:legacy-site /tmp/legacy/<الملف>.json --dry-run   # ثم بدون --dry-run، ثم مراجعة المسودات ونشرها
```

والتحقق:

```bash
curl -I https://ejaz.sa/                       # CSP وHSTS وX-Frame-Options
curl https://ejaz.sa/robots.txt                # Sitemap في الإنتاج، وDisallow: / في التجريبية
curl -o /dev/null -w '%{http_code}\n' https://ejaz.sa/up
$DC exec -T app php artisan ejaz:health
$DC ps                                         # كل الخدمات healthy
```

## رابعاً: كل نشر بعده

1. على جهاز المطور: الفحص الآلي ناجح على `main`، ثم وسم: `git tag -a v1.2.0 -m "..." && git push origin v1.2.0`.
2. على الخادم: `cd /opt/ejaz && bash scripts/deploy-compose.sh v1.2.0`.

ما يفعله السكربت بالترتيب:

1. يتحقق من `APP_ENV` و`APP_DEBUG=false` و`APP_URL` بـ https و`SESSION_SECURE_COOKIE`، وينبّه لـ `TRUSTED_PROXIES` الفارغ ولمزود الرسائل `log`، ويتحقق من نظافة المجلد ووجود الوسم.
2. يسحب الوسم.
3. يبني صورتي التطبيق والويب بالوسم (والموقع يعمل).
4. يضمن تشغيل القاعدة وRedis (إن كانتا مضمّنتين).
5. وضع الصيانة.
6. `migrate --force` ثم `db:seed --class=AccessSeeder --force` (الأدوار ثم مزامنة الصلاحيات من `config/permissions.php`، آمن للتكرار) بالصورة الجديدة.
7. يبدّل الحاويات. وكل حاوية تبني عند بدئها ذاكرة الإعدادات والمسارات والقوالب والأحداث وFilament. وHorizon ينهي مهامه الجارية قبل توقفه.
8. `ejaz:refresh-content` (ذاكرة المحتوى العام وحدها)، ثم رفع الصيانة.
9. فحص `/up` و`ejaz:health`.

**ممنوع في الإنتاج:** `cache:clear` و`optimize:clear` (يصفّران قفل الدخول وحدود الرسائل ورموز التحقق — `security.md`)، و`migrate:fresh`، و`db:seed` بلا `--class`، و`docker compose down -v` (يحذف القاعدة).

## إن فشل النشر

السكربت يتوقف عند أول خطأ، ويبقى الموقع في وضع الصيانة (صفحة 503 العربية).

- **قبل الترحيل** (البناء): الحاويات القديمة ما زالت تعمل، فـ `$DC exec -T app php artisan up` بعد التحقق.
- **في الترحيل أو بعده:** `$DC run --rm --no-deps app php artisan migrate:status`، ثم التراجع (`cutover.md` «ب»).
- السجل: `$DC logs --tail=200 app horizon web`.

## حدود الرفع

من `roadmap.md`: ملف الصوت في المكتبة حتى 25 ميجابايت، وPDF حتى 20. فـ `client_max_body_size 32M` في nginx، و`upload_max_filesize` و`post_max_size` بـ 32M في PHP، و`memory_limit = 512M` (داخل الصورة). وأجزاء مقاطع المسابقات دونها، والرفع المباشر لا يمر بالخادم أصلاً. وإن رُفع حد ملف فيُرفع الثلاثة معاً، ويصير فحص PDF تدفقياً.

ولمقاطع المسابقات: `COMPETITION_VIDEO_UPLOAD=s3`، ونطاق التخزين في `CSP_CONNECT_SRC`، وCORS الحاوية لنطاق البوابة وحده، وقاعدة إلغاء الرفع غير المكتمل بعد يوم (`deploy/storage/`)، و`ffmpeg` في الصورة، ومساحة مؤقتة كافية للعمال (`deploy/provider-checklist.md`).

## الملفات الخاصة

- على تخزين S3 داخل المملكة (`FILESYSTEM_PRIVATE_DISK=s3`) بالواجهة القياسية (`AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` والحاوية من البيئة)، فيعمل مع أي مزود متوافق بلا تغيير في الشيفرة. واختبار `tests/Feature/Platform/S3CompatibleStorageTest.php` يتحقق من الروابط الموقّعة للقرص الخاص ولأجزاء الرفع المباشر بعنوان مخصص.
- الحاوية خاصة بلا وصول عام، وإصداراتها مفعّلة.
- ملفات الوثائق والمكتبة المنشورة تُبث من PHP بعد فحص النشر. والمستهدف قبل الذروة رابط تخزين موقّع قصير (خطة I8 في `security/2026-10-10-security-audit.md`).

## الانتقال إلى خدمة مُدارة (لاحقاً، بلا تغيير في الشيفرة)

متى توفرت قاعدة PostgreSQL 17 أو ذاكرة Redis 7 مُدارتان داخل المملكة وثبت تفوقهما:

1. نسخة احتياطية (`ejaz-backup.service`)، ووضع الصيانة.
2. القاعدة: استعادة النسخة في الخدمة المُدارة (`scripts/restore.md`)، ثم في `.env`: `DB_HOST` و`DB_PORT` و`DB_DATABASE` و`DB_USERNAME` و`DB_PASSWORD` (و`DB_SSLMODE=require` إن طلبه المزود)، ومثلها `PGHOST` وأخواته في `.env.backup`.
3. الذاكرة: `REDIS_HOST` و`REDIS_PORT` و`REDIS_PASSWORD`. والجلسات والطوابير المعلّقة تبدأ من جديد، فيُختار وقت هادئ. وسياسة الإخلاء في الخدمة `volatile-lru` أو `noeviction` (`performance.md`).
4. حذف `bundled` من `COMPOSE_PROFILES`، ثم `$DC up -d --remove-orphans`، ثم رفع الصيانة و`ejaz:health`.
5. يبقى الحجمان القديمان أسبوعين للتراجع، ثم يُحذفان.

## الفهرسة

- `SEO_INDEXABLE=true` في الإنتاج وحده بعد نقل النطاق (`cutover.md`).
- robots.txt وsitemap.xml من لارافيل: لا ملف `public/robots.txt`، ولا `location = /robots.txt` بلا `try_files` في nginx.

## التجريبية

مطابقة للإنتاج ببيانات وهمية: الحزمة نفسها بـ `APP_ENV=staging` و`SEO_INDEXABLE=false` ونطاق فرعي، ومزود الرسائل `log`، وبريد وهمي. وعليها وحدها اختبار التحمل والفحص الأمني الفعلي (`deploy/staging-plan.md`).

---

## ملحق: خادم بلا حاويات

إن تعذر Docker لدى المزود، فالبديل المكافئ:

- PHP 8.4-FPM بإضافاتها، وnginx (`deploy/nginx/ejaz.conf`)، وPostgreSQL 17، وRedis 7 (`deploy/redis/redis.conf`)، و`ffmpeg`، و`postgresql-client-17` و`gnupg` و`awscli`.
- `deploy/php/production.ini` إلى `/etc/php/8.4/{fpm,cli}/conf.d/99-ejaz.ini`.
- المشروع في `/var/www/ejaz` بمستخدم التطبيق، و`storage` و`bootstrap/cache` قابلان للكتابة له وحده، و`.env` بصلاحية 600، و`sudo` لإعادة تحميل PHP-FPM وحدها:
  ```
  www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
  ```
- الوحدات: `deploy/systemd/ejaz-horizon.service`، و`ejaz-scheduler.service` مع مؤقته (خادم واحد)، و`ejaz-health.service` مع مؤقته، و`ejaz-backup.service` مع مؤقته (بـ `/etc/ejaz/backup.env`).
- النشر: `bash scripts/deploy.sh v1.2.0` (الخطوات نفسها، ومعها `composer install --no-dev` و`npm ci && npm run build` على الخادم و`horizon:terminate` وإعادة تحميل PHP-FPM). ومتعدد الخوادم: الترحيل على خادم واحد، والبقية `RUN_MIGRATIONS=false`.
