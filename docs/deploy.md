# دليل النشر في الإنتاج والتجريبية

المرجع: بنود الإنتاج في المرحلة 1 من `roadmap.md`، و`security.md`، و`performance.md`، و`decisions.md` (الاستضافة داخل المملكة على أوراكل كلاود).

> **الحال:** هذا دليل وقوالب جاهزة، ولا حساب سحابي بعد. التعاقد مع أوراكل كلاود بانتظار صفوان، وقبله التحقق من توفر PostgreSQL المُدارة وOCI Cache وتخزين S3 في منطقتي جدة والرياض (بند في `roadmap.md`). كل قيمة بين `<...>` تُضبط عند التعاقد. ولا سر في قيت: الأسرار في مخزن أسرار المزود (OCI Vault) وفي `.env` على الخادم وحده.

## الملفات

- `scripts/deploy.sh` — النشر خطوة بخطوة بوسم.
- `deploy/nginx/ejaz.conf` — عينة nginx.
- `deploy/php/production.ini` — قيم PHP للإنتاج.
- `deploy/systemd/` — وحدات Horizon، والمجدول، وفحص الصحة، والنسخ الاحتياطي.
- `scripts/backup.sh` و`scripts/restore.md` — النسخ والاستعادة.
- `docs/monitoring.md` — المراقبة والتنبيه.

## البنية المستهدفة (من قرار الاستضافة)

- خادما تطبيق (nginx وPHP-FPM 8.4) خلف موزع أحمال يُنهي TLS. ويرتفع العدد وقت الذروة.
- PostgreSQL 17 مُدارة بنسختين.
- Redis 7 للجلسات والذاكرة والطوابير، بسياسة إخلاء `volatile-lru` أو `noeviction` (لا `allkeys-*` — `performance.md`).
- تخزين متوافق مع S3 للقرص الخاص (`FILESYSTEM_PRIVATE_DISK`)، وحاوية منفصلة للنسخ الاحتياطية.
- **المجدول على خادم واحد،** والعمال (Horizon) على كل خادم أو على خادم مخصص.

## أولاً: تجهيز الخادم (مرة واحدة)

1. نظام Ubuntu LTS، وتحديثاته الأمنية آلية (`unattended-upgrades`)، وجدار ناري لا يفتح إلا 22 (من عناوين الإدارة وحدها) و80/443 (من الموزع وحده).
2. الحزم: `nginx`، و`php8.4-fpm` بإضافاتها (`pgsql` و`redis` و`gd` و`intl` و`mbstring` و`xml` و`zip` و`bcmath` و`curl`)، و`composer`، و`nodejs` (إصدار LTS)، و`git`، و`ffmpeg` (على خوادم عمال مقاطع المسابقات)، و`postgresql-client-17` و`gnupg` و`awscli` (على خادم النسخ).
3. مستخدم للتطبيق (`www-data` أو `ejaz`) يملك `/var/www/ejaz`، وبلا صلاحية `sudo` إلا لأمر إعادة تحميل PHP-FPM:
   ```
   www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
   ```
4. استنساخ المستودع بمفتاح نشر للقراءة وحدها:
   ```bash
   git clone git@github.com:<المستودع>.git /var/www/ejaz
   ```
5. PHP: `cp deploy/php/production.ini /etc/php/8.4/fpm/conf.d/99-ejaz.ini` ومثله في `cli/conf.d`. ثم `systemctl restart php8.4-fpm`.
6. nginx: `cp deploy/nginx/ejaz.conf /etc/nginx/sites-available/` وضبط النطاق والمسار والشهادة، ثم ربطه في `sites-enabled` و`nginx -t && systemctl reload nginx`.
7. الصلاحيات: `storage` و`bootstrap/cache` قابلة للكتابة لمستخدم التطبيق وحده (`chmod -R 750`)، و`.env` بصلاحية `600`.
8. الوحدات:
   ```bash
   cp deploy/systemd/*.service deploy/systemd/*.timer /etc/systemd/system/
   systemctl daemon-reload
   systemctl enable --now ejaz-horizon                 # كل خادم عمال
   systemctl enable --now ejaz-scheduler.timer         # خادم واحد فقط
   systemctl enable --now ejaz-health.timer            # كل خادم
   systemctl enable --now ejaz-backup.timer            # خادم واحد (انظر «النسخ الاحتياطي»)
   ```

## ثانياً: ملف `.env` للإنتاج

يُبنى من `.env.example`، وأهم ما يختلف (والأسرار من المخزن):

- `APP_ENV=production`، و`APP_DEBUG=false`، و`APP_URL=https://ejaz.sa` (العنوان العام بـ https، فمنه الروابط و`trustHosts`)، و`APP_KEY` يُولَّد مرة بـ `php artisan key:generate --show` ويُحفظ في المخزن.
- `SEO_INDEXABLE=true` في الإنتاج وحده، و`false` في التجريبية.
- `LOG_STACK=daily` و`LOG_LEVEL=warning`.
- `TRUSTED_PROXIES=<عناوين موزع الأحمال>` بعينها (`security.md`).
- `SESSION_DRIVER=redis`، و`SESSION_SECURE_COOKIE=true`، و`CACHE_STORE=redis`، و`QUEUE_CONNECTION=redis`، و`REDIS_PASSWORD` من المخزن.
- `FILESYSTEM_PRIVATE_DISK=s3` ومعه `AWS_*` للحاوية داخل المملكة (`AWS_ENDPOINT` لواجهة S3 المتوافقة، و`AWS_USE_PATH_STYLE_ENDPOINT=true` لأوراكل).
- `CSP_IMG_SRC` و`CSP_CONNECT_SRC` و`CSP_MEDIA_SRC`: نطاقات التخزين وشبكة التوصيل إن قُدّمت منها ملفات للمتصفح مباشرة (الرفع المباشر لمقاطع المسابقات يلزمه نطاق التخزين في `CSP_CONNECT_SRC`).
- `MAIL_*` لمزود البريد، و`SMS_DRIVER` لمزود الرسائل المعتمد، و`SMS_PER_HOUR` حسب التسجيل المتوقع (`performance.md`).
- `HEALTH_*` حدود فحص الصحة (`monitoring.md`).
- ما يلزمه قرار أو حساب: `BOT_CHALLENGE_DRIVER` وTurnstile، و`ONLINE_MEETINGS_DRIVER` وMicrosoft Graph، والدفع (`PAYMENTS_ENABLED=false` حتى يُعاد تشغيله بقرار). انظر «عند كل نشر» في `security.md`.

## ثالثاً: أول نشر

```bash
cd /var/www/ejaz
bash scripts/deploy.sh v1.0.0
php artisan ejaz:create-admin          # المشرف الأول، تفاعلي، بلا كلمة مرور في السجل
```

ثم بنود الإطلاق الأول في `roadmap.md`:

```bash
php artisan db:seed --class=MenuSeeder --force
php artisan db:seed --class=ContentSeeder --force
php artisan db:seed --class=ProgramTypesSeeder --force
php artisan db:seed --class=LegacyRedirectsSeeder --force
php artisan import:legacy-site <الملف> --dry-run      # ثم بدون --dry-run، ثم مراجعة المسودات ونشرها
```

والتحقق:

```bash
curl -I https://ejaz.sa/                      # رؤوس الحماية: CSP وHSTS وX-Frame-Options
curl https://ejaz.sa/robots.txt               # فيه سطر Sitemap في الإنتاج، وDisallow: / في التجريبية
curl -o /dev/null -w '%{http_code}\n' https://ejaz.sa/up
php artisan ejaz:health
```

## رابعاً: كل نشر بعده

1. على جهاز المطور: الفحص الآلي ناجح على `main`، ثم وسم: `git tag -a v1.2.0 -m "..." && git push origin v1.2.0`.
2. على الخادم الأول (الترحيل والبذر):
   ```bash
   cd /var/www/ejaz && bash scripts/deploy.sh v1.2.0
   ```
3. على كل خادم آخر: `RUN_MIGRATIONS=false bash scripts/deploy.sh v1.2.0`.

ما يفعله السكربت بالترتيب:

1. يتحقق من `APP_ENV` و`APP_DEBUG=false` و`APP_URL` بـ https، ومن نظافة المجلد ووجود الوسم.
2. `php artisan down` (ومعه `MAINTENANCE_SECRET` إن ضُبط، لمراجعة الموقع قبل رفع الصيانة).
3. يسحب الوسم.
4. `composer install --no-dev --optimize-autoloader`.
5. `npm ci && npm run build` (أو `BUILD_ASSETS=false` إن رُفعت `public/build` من الفحص الآلي).
6. `php artisan migrate --force`.
7. `php artisan db:seed --class=AccessSeeder --force`: الأدوار ثم مزامنة الصلاحيات من `config/permissions.php`، آمن للتكرار.
8. `php artisan ejaz:refresh-content`: ذاكرة المحتوى العام وحدها.
9. `config:cache` و`route:cache` و`view:cache` و`event:cache` و`filament:optimize`.
10. `php artisan horizon:terminate` (يعيد systemd تشغيل العمال بالشيفرة الجديدة)، وإعادة تحميل PHP-FPM لتفريغ OPcache.
11. `php artisan up`، ثم فحص `/up` و`ejaz:health`.

**ممنوع في الإنتاج:** `cache:clear` و`optimize:clear`، لأنهما يصفّران قفل الدخول وحدود الرسائل والسقف العام، ويبطلان رموز التحقق المعلّقة (`security.md`). ولا `migrate:fresh` ولا `db:seed` بلا `--class`.

## إن فشل النشر

السكربت يتوقف عند أول خطأ، ويبقى الموقع في وضع الصيانة (صفحة 503 العربية).

- **قبل الترحيل** (المكتبات أو البناء): `git checkout --force refs/tags/<الوسم السابق>`، ثم أعد تشغيل السكربت بالوسم السابق.
- **في الترحيل أو بعده:** لا تُرجع الشيفرة قبل فحص حالة القاعدة (`php artisan migrate:status`). الترحيل الجديد يُكتب ليقبل التراجع، وإن تعذر فالاستعادة من النسخة (`scripts/restore.md`).
- السجل: `storage/logs/` و`journalctl -u php8.4-fpm`.

## حدود الرفع

من `roadmap.md`: ملف الصوت في المكتبة حتى 25 ميجابايت، وPDF حتى 20. فـ `client_max_body_size 32M` في nginx، و`upload_max_filesize` و`post_max_size` بـ 32M في PHP، و`memory_limit = 512M`. وأجزاء مقاطع المسابقات (`COMPETITION_VIDEO_CHUNK_MB`، 5 افتراضياً) دونها. وإن رُفع حد ملف فيُرفع الثلاثة معاً، ويصير فحص PDF تدفقياً.

ولرفع مقاطع المسابقات في الإنتاج (المرحلة 2): الرفع المباشر `COMPETITION_VIDEO_UPLOAD=s3`، ونطاق التخزين في `CSP_CONNECT_SRC`، وقواعد CORS على الحاوية لنطاق البوابة وحده، وقاعدة دورة حياة تلغي الرفع غير المكتمل بعد يوم، ومساحة مؤقتة كافية لعمال المقاطع و`ffmpeg` فيها.

## الملفات الخاصة

- في الإنتاج على تخزين S3 داخل المملكة (`FILESYSTEM_PRIVATE_DISK=s3`)، والحاوية خاصة بلا وصول عام، وإصداراتها مفعّلة.
- ملفات الوثائق والمكتبة المنشورة تُبث اليوم من PHP بعد فحص النشر. والمستهدف قبل الذروة: رابط تخزين موقّع قصير، أو `X-Accel-Redirect` إن بقي القرص محلياً (القسم المعلّق في عينة nginx). الخطة في `docs/security/2026-10-10-security-audit.md`.

## الفهرسة

- `SEO_INDEXABLE=true` في الإنتاج وحده.
- robots.txt وsitemap.xml من لارافيل: لا ملف `public/robots.txt`، ولا `location = /robots.txt` بلا `try_files` في nginx.

## التجريبية

مطابقة للإنتاج ببيانات وهمية: الإعداد نفسه بـ `APP_ENV=staging` و`SEO_INDEXABLE=false`، ومزود الرسائل `log`، وبريد وهمي. وعليها وحدها اختبار التحمل (`tests/load/README.md`) والفحص الأمني الفعلي.
