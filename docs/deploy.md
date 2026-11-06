# دليل النشر في الإنتاج والتجريبية

المرجع: بنود الإنتاج في المرحلة 1 من `roadmap.md`، و`security.md`، و`performance.md`، و`decisions.md` (11 أكتوبر 2026: تأجيل اختيار الخادم حتى يكتمل المشروع، ولا اعتماد على خدمة مُدارة. و18 أكتوبر 2026: الاستضافة خارج المملكة مقبولة، والمرشح Contabo والنسخ عند Hetzner).

> **الحال:** كل ما هنا جاهز للتنفيذ، ولا حساب سحابي ولا خادم بعد.
> - **المقترح الجديد (26 أكتوبر):** Hostinger KVM 8 في ألمانيا، والملفات على قرصه، والنسخ عند Hetzner (القسم «Hostinger KVM»).
> - **المرشح (قرار 18 أكتوبر):** Contabo Cloud VDS XL للإنتاج، وContabo Object Storage للملفات في منطقة الخادم، وHetzner Object Storage للنسخ الاحتياطية عند شركة ثانية. والاعتماد النهائي بقائمة `deploy/provider-checklist.md`.
> - **التجربة أولاً** على الخادم التجريبي (`deploy/staging-plan.md`)، ومن يجهّز الخادم يبدأ بدليل `deploy/handoff-server.md`.
> - كل قيمة بين `<...>` تُضبط عند الاختيار.
> - **لا سر في قيت:** الأسرار في مدير كلمات المرور المعتمد لدى الجمعية 【ناقص — `open-questions.md`】، وفي `.env` على الخادم وحده.

## الملفات

- `deploy/compose.production.yaml` — الحزمة: التطبيق (PHP-FPM)، وnginx، وHorizon، والمجدول، وPostgreSQL 17، وRedis 7، والنسخ الاحتياطي.
- `deploy/docker/` — صورة الإنتاج (`Dockerfile` بمراحل: التطبيق، والويب، والنسخ)، ونقطة الدخول، وإعداد PHP-FPM، وnginx للحاوية.
- `.env.production.example` و`.env.backup.example` — قالبا الإعداد بلا أسرار.
- `scripts/deploy-compose.sh` — النشر بوسم.
- `scripts/server-bootstrap.sh` — تجهيز خادم Ubuntu 24.04 الجديد مرة واحدة: المستخدم، وSSH بالمفتاح، وufw، وfail2ban، والتحديثات، وDocker (القسم «Hostinger KVM»).
- `scripts/backup.sh` و`scripts/restore.md` — النسخ المشفّر خارج الخادم والاستعادة وتجربتها.
- `deploy/systemd/compose/` — تشغيل الحزمة عند الإقلاع، والنسخ اليومي، وفحص الصحة، مع المؤقتات في `deploy/systemd/`.
- `deploy/storage/` — CORS ودورة الحياة لحاويات S3.
- `deploy/php/production.ini` — قيم PHP للإنتاج (داخل الصورة).
- `docs/monitoring.md`، و`docs/cutover.md` (نقل النطاق والتراجع)، و`docs/launch-checklist.md`.
- `docs/deploy/handoff-server.md` — دليل التسليم لمن يجهّز الخادم. و`docs/deploy/provider-checklist.md` — ما يُتحقق منه عند المزودين، وإعدادات الذاكرة لكل حجم.
- **بلا حاويات (بديل):** `scripts/deploy.sh`، و`deploy/nginx/ejaz.conf`، و`deploy/redis/redis.conf`، ووحدات `deploy/systemd/*.service` — في الملحق آخر الدليل.

## البنية

- **خادم لينكس واحد** بالحزمة كلها (المرشح Contabo Cloud VDS XL، والمواصفات في `deploy/provider-checklist.md`). وعند المرشح يكون بحجم الذروة من البداية، لأنه لا يُرفع بالساعة ولا يُخفض.
- **تخزين كائنات متوافق مع S3 في منطقة الخادم:** حاوية للملفات الخاصة ومقاطع المسابقات (`FILESYSTEM_PRIVATE_DISK=s3`، والمرشح Contabo Object Storage).
- **حاوية النسخ عند مزود ثانٍ** (المرشح Hetzner Object Storage)، بحساب ومفتاح مستقلين وقفل الكائنات، فلا تجتمع البيانات ونسخها عند مزود واحد.
- **القاعدة والذاكرة على الخادم** (ملف التعريف `bundled`)، بحجمين دائمين (`pgdata` و`redisdata`). والانتقال إلى خدمة مُدارة لاحقاً بمتغيرات البيئة وحدها (القسم «الانتقال إلى خدمة مُدارة»).
- **التطبيق بلا حالة محلية** إلا القرص العام والسجلات في الحجم `storage`.

## أولاً: تجهيز الخادم (مرة واحدة)

1. Ubuntu LTS (أو ما يعادله)، وتحديثاته الأمنية آلية (`unattended-upgrades`).
2. جدار ناري (المزود والخادم): 22 من عناوين الإدارة وحدها، و80 و443 للعموم. ولا منفذ للقاعدة ولا لـ Redis (شبكة الحاويات داخلية).
   - جدار Contabo يحجب كل الوارد لحظة تفعيله، فتُضاف قواعده الثلاث قبله.
   - وDocker يتجاوز جدار الخادم (ufw) في المنافذ المنشورة، فلا يُنشر إلا 80 و443 (`deploy/compose.production.yaml`).
3. الدخول بمفاتيح SSH وحدها، ولا دخول بكلمة مرور ولا بـ root.
4. Docker Engine وإضافة compose من مستودع Docker الرسمي.
5. قرص البيانات مركّب على `/var/lib/docker` (أو مجلد Docker معدّل إليه)، فتكون القاعدة وRedis والتخزين عليه، ولقطاته مجدولة حيث أتاحها المزود. وفي Cloud VDS قرص واحد بلا لقطات، فالاحتياط كله النسخ (الخطوة 9).
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
10. حاويتا التخزين (`deploy/storage/README.md`):
    - حاوية الملفات عند مزود الخادم: خاصة، ثم CORS والإصدارات ودورة الحياة.
    - وحاوية النسخ عند المزود الثاني، بقفل الكائنات من إنشائها (لا يُضاف بعده).

## ثانياً: ملف `.env` للإنتاج

من `.env.production.example`، وتُملأ الفارغة من مدير كلمات المرور (أعلى الدليل):

- `APP_KEY`: يُولَّد مرة ويُحفظ في مدير كلمات المرور: `docker compose --env-file .env -f deploy/compose.production.yaml run --rm --no-deps app php artisan key:generate --show`.
- `APP_URL=https://ejaz.sa`، و`APP_DEBUG=false`، و`SESSION_SECURE_COOKIE=true`.
  - **قبل طباعة رمز QR لمسابقة الطلاب** («الرابط ورمز QR» في اللوحة): الرمز يُبنى من `APP_URL`، فتأكد أنه رابط الإنتاج النهائي قبل التنزيل والتعميم على المدارس. والرمز المطبوع برابط آخر لا يُصلحه تغيير لاحق.
- `SEO_INDEXABLE=false` حتى يوم نقل النطاق، ثم `true` في الإنتاج وحده.
- `TRUSTED_PROXIES`: عناوين موزع الأحمال إن وُجد، وفارغة إن وصل الزوار مباشرة (`security.md`).
- `DB_PASSWORD` و`REDIS_PASSWORD`: طويلة عشوائية (`openssl rand -base64 32`).
- التخزين بواجهة S3 القياسية: `AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` و`AWS_BUCKET` والمفتاحان، و`CSP_CONNECT_SRC` بعنوان التخزين للرفع المباشر.
  - عند Contabo: `AWS_ENDPOINT=https://eu2.contabostorage.com` بنمط المسار، والمنطقة كما في أمثلته (`default`). ويُجرَّب على التجريبي (`deploy/provider-checklist.md`).
- `MAIL_*` لمزود البريد، و`SMS_DRIVER` بعد اعتماد المزود (`open-questions.md`)، و`SMS_PER_HOUR` حسب التسجيل المتوقع (`performance.md`).
- ضبط الذاكرة: `PG_*` و`REDIS_MAXMEMORY` و`FPM_MAX_CHILDREN` حسب حجم الخادم (`deploy/provider-checklist.md`، ولخادم Cloud VDS XL قسم «إعدادات خادم 8 أنوية و64 جيجابايت»).
- ما يلزمه قرار أو حساب: Turnstile، وMicrosoft Graph، والدفع (`PAYMENTS_ENABLED=false`). انظر «عند كل نشر» في `security.md`.
- **Turnstile وحدود الدخول (قرار صفوان، 26 أكتوبر 2026):** بمفتاحَي `TURNSTILE_SITE_KEY` و`TURNSTILE_SECRET_KEY` مع `BOT_CHALLENGE_DRIVER=turnstile`، يصير بلوغ حدود الدخول الخاطئ (لكل حساب ومعرّف وعنوان وشبكة) تحدياً لا حجباً، حتى `BOT_CHALLENGE_CEILING_FACTOR` ضعفاً من الحد (5 افتراضياً) فيعود الحجب. فلا يقفل طالبٌ حسابَ زميله ولا دخولَ مدرسته بمحاولات خاطئة متعمدة. وحد طلبات الاستعادة لكل عنوان يبقى حاجباً كما هو. وصاحب الحساب يُنبَّه، وسقف رسائل التنبيه النصية `LOGIN_CHALLENGE_SMS_PER_DAY` (200). ونمط العنصر في لوحة Cloudflare «Managed».
  - **المفاتيح غير موجودة بعد:** وما دامت غائبة يبقى الحجب الحالي عند الحد كما هو، ويبقى خطر قفل الحساب والمدرسة قائماً حتى تُضبط.
  - **بعد ضبطها:** تجربة على التجريبي: عشر محاولات خاطئة لحساب، ثم الدخول بكلمة مروره الصحيحة يمر بعد حل التحدي.

ولـ `.env.backup`: اتصال القاعدة (`PGHOST=pgsql`) بدور للقراءة وحدها (`CREATE ROLE ejaz_backup LOGIN PASSWORD '...'; GRANT pg_read_all_data TO ejaz_backup;`)، وحاوية النسخ ومفتاحاها عند المزود الثاني (`BACKUP_S3_ENDPOINT` مثل `https://fsn1.your-objectstorage.com`، و`AWS_DEFAULT_REGION` بموقعه)، و`BACKUP_GPG_RECIPIENT`، وكلمتا سر تشفير الملفات `BACKUP_CRYPT_PASSWORD` و`BACKUP_CRYPT_SALT` (عشوائيتان، ونسختهما في مدير كلمات المرور مع مفتاح GPG الخاص، ولا تُغيَّران بعد أول نسخة)، وحاوية الملفات الخاصة للقراءة `BACKUP_SOURCE_*` (`.env.backup.example`). ومفاتيح Hetzner لكل مشروع على كل حاوياته، فمفتاح الخادم في مشروع مستقل تعطيه سياسةُ حاوية النسخ الكتابةَ والسرد وحدهما (`deploy/provider-checklist.md`). ومفتاح حاوية النسخ **كتابة بلا حذف ولا قراءة** (`PutObject` و`ListBucket` و`AbortMultipartUpload` بلا `DeleteObject` ولا `GetObject` — `deploy/storage/README.md` «المفاتيح»)، والحذف بقاعدة دورة الحياة في الحاوية (`deploy/storage/README.md`)، ومع الإصدارات أو قفل الكائنات على حاوية النسخ (شرط — `deploy/storage/README.md`) لا يمحو من يخترق الخادم النسخ ولا يكتب فوقها.

## ثالثاً: أول نشر

```bash
cd /opt/ejaz
bash scripts/deploy-compose.sh v1.0.0
DC="docker compose --env-file .env -f deploy/compose.production.yaml"
$DC exec app php artisan ejaz:create-admin      # المسؤول التقني الأول، تفاعلي (--role=super_admin للمشرف العام، اطلاع فقط)
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

1. يتحقق من `APP_ENV` و`APP_DEBUG=false` و`APP_URL` بـ https و`SESSION_SECURE_COOKIE`، وينبّه لـ `TRUSTED_PROXIES` الفارغ أو `*` ولمزود الرسائل `log`، ويتحقق من نظافة المجلد ووجود الوسم.
2. يسحب الوسم.
3. يبني صورتي التطبيق والويب بالوسم (والموقع يعمل).
4. يضمن تشغيل القاعدة وRedis (إن كانتا مضمّنتين).
5. وضع الصيانة.
6. **قبل أول نشر فيه ترحيل تفرد الجوال** (`2026_11_05_010000`، الإصدار 0.15.2): `php artisan ejaz:phone-only-duplicates` على القاعدة القائمة. يطبع معرّفات الحسابات التي سيفرّغ الترحيل جوالها وهو معرّف دخولها الوحيد، بلا أرقام. فيُضاف لكل منها بريد أو جوال آخر من «المستخدمون» بعد النشر، وإلا لا يدخل (`decisions/2026-11-05-phone-login.md` — البلاغ 185، L2).
   ثم `migrate --force` ثم `db:seed --class=AccessSeeder --force` (الأدوار ثم مزامنة الصلاحيات من `config/permissions.php`، آمن للتكرار) بالصورة الجديدة. ثم `db:seed --class=MainMenuSevenSeeder --force` (القائمة الرئيسية بسبعة عناصر، النسخة 0.15) **مرة واحدة** على الخادم: يترك علامة `.deploy-state/main-menu-seven`، فلا يعيد نشر لاحق ترتيب «القائمة» كما رتّبها المحرر ولا يخفي ما أظهره منها. ولإعادته عمداً تُحذف العلامة.
7. يبدّل الحاويات. وكل حاوية تبني عند بدئها ذاكرة الإعدادات والمسارات والقوالب والأحداث وFilament. وHorizon ينهي مهامه الجارية قبل توقفه.
8. `ejaz:refresh-content` (ذاكرة المحتوى العام، وبنوك مراحل مسابقة الطلاب وفحص درجتها القصوى، فلا يبقى بعد ترحيل يغيّر الفئات أو الأسئلة مباشرة بنكٌ مخزن قديم)، ثم رفع الصيانة. ولا `cache:clear`.
9. فحص `/up` و`ejaz:health`.

### ما يتغيّر للمحرر عند الترقية إلى 0.15 (التحديث البصري 2، #111)

- **«إعدادات الموقع» ← «الرئيسية»:** لا مفتاح بعد لإظهار «البطل» ولا «بطاقات الاختصارات» (`show_hero` و`show_shortcuts`): البطل وبطاقات «خدماتنا» ظاهرة دائماً ببنية مرجع المدير. والقيمتان المحفوظتان من قبل تُتركان في القاعدة ولا تُقرآن.
- **«الشركاء» ظاهرة افتراضياً** (`show_partners`) في الرئيسية ما لم تُطفأ من الشاشة نفسها. فمن أطفأها قبل الترقية تبقى مطفأة، ومن لم يحفظ الإعداد قط يراها ظاهرة.
- **القائمة الرئيسية بسبعة عناصر** بالبذرة `MainMenuSevenSeeder` مرة واحدة (الخطوة ٦ أعلاه).

**ممنوع في الإنتاج:** `cache:clear` و`optimize:clear` (يصفّران قفل الدخول وحدود الرسائل ورموز التحقق — `security.md`)، و`migrate:fresh`، و`db:seed` بلا `--class`، و`docker compose down -v` (يحذف القاعدة).

## الترقية إلى v0.15.0

بحزمة الحاويات، و`$DC` كما في «ثالثاً». وللخادم بلا حاويات الأوامر نفسها بلا `$DC exec -T app` (الملحق). والقرار في `docs/decisions/2026-11-03-deploy-readiness.md`.

### خطوات طلبات دمج v0.15 (حالتها في 3 نوفمبر 2026)

ما عُلّم «غير مدموج» لم يدخل `main` بعد، فإن وُسم v0.15.0 قبل دمجه سقطت خطوته من هذا الإصدار:

- **#124 محرر الصفحات (مدموج):** `migrate` (جدول `page_revisions`)، و`PermissionsSeeder` (صلاحية `pages.edit_all` وتضييق صلاحيات الصفحات لمحرر المحتوى). وكلاهما في سكربت النشر (`AccessSeeder` يشغّل `PermissionsSeeder`). واستيراد الموقع السابق (`import:legacy-site`) يلزمه منفّذ مسؤول تقني.
- **#126 برامج المكتبة (غير مدموج، مكدّس على #124):** `migrate` (`programs.library_item_id` و`library_items.duration_seconds` و`program_registrations.completed_at`)، ثم **يدوياً مرة**: `$DC exec -T app php artisan db:seed --class=ProgramTypesSeeder --force` (الأنواع الثلاثة، ولا يمس الموجود). وffprobe في صورة العامل (يأتي مع `ffmpeg` في `deploy/docker/Dockerfile`)، وعامل طابور `library` (`supervisor-library` في `config/horizon.php`، مدموج).
- **#127 إحصائيات البرامج لكل نوع (مدموج):** لا ترحيل ولا بذرة. `$DC exec -T app php artisan ejaz:refresh-stats` بعد النشر، فيظهر الرسم والقسم دون انتظار المجدول.
- **#132 مراجعة الأداء (مدموج):** `types { application/javascript mjs; }` في nginx. بحزمة الحاويات يكفي بناء صورة الويب (`ejaz-web`) في سكربت النشر. وللخادم بلا حاويات: نسخ `deploy/nginx/ejaz.conf`، ثم `nginx -t` و`nginx -s reload`.
- **#137 المنخفضات الأمنية (`fix/v0.15-security-lows`، غير مدموج):** تأخير إعادة `SendExamReceipt` (`$backoff = [30, 120]`) في #137 وحده، لا في هذا الطلب. ولا خطوة نشر له.

### قائمة النشر وما بعده (عشرة بنود: ١ و٢ قبله، و٣ هو، والباقي بعده)

1. **قبل الترحيل:** ترحيل «مكتبة إعجاز» بأقسامها الأربعة يحوّل ما خارجها إلى «الكتب والمؤلفات» بلا سجل (`docs/decisions/2026-11-02-library-four-sections.md`، «خطوات النشر»). فيُشغَّل الاستعلام الآتي وتُحفظ نتيجته مع سجل النشر، والمتوقع صفر صفوف:

   ```bash
   $DC exec -T pgsql psql -U ejaz -d ejaz -c "SELECT id, section, title, deleted_at FROM library_items WHERE lower(trim(section)) NOT IN ('videos', 'audio', 'books', 'research') ORDER BY id;"
   ```

   والقاعدة والمستخدم من `DB_DATABASE` و`DB_USERNAME` في `.env` (`ejaz` في المثال). ومع قاعدة مُدارة يُشغَّل الاستعلام نفسه بـ `psql` على اتصالها.

   وبعد الترحيل: `SELECT section, count(*) FROM library_items GROUP BY section;` لا يُظهر إلا الأربع، وما ظهر في الاستعلام الأول صار `books`، ويُعاد تصنيف البحث منه من اللوحة.
2. **نسخة احتياطية** قبل النشر: `sudo systemctl start ejaz-backup.service`، ثم `journalctl -u ejaz-backup.service -n 50` بلا خطأ (`scripts/restore.md`).
3. **النشر:** `bash scripts/deploy-compose.sh v0.15.0`. يشغّل الترحيل و`AccessSeeder` و`MainMenuSevenSeeder` مرة واحدة (علامة `.deploy-state/main-menu-seven`) و`ejaz:refresh-content`، ثم `/up` و`ejaz:health`.
4. **Horizon وطوابيره:** `$DC exec -T horizon php artisan horizon:status` يرد «running»، و`$DC exec -T horizon php artisan horizon:supervisors` فيه `supervisor-1` (`default`) و`supervisor-videos` (`videos`) و`supervisor-receipts` (`receipts`، إيصالات الاختبار) و`supervisor-library` (`library`، عدّ صفحات الكتب).
5. **متغيرات `.env` الجديدة** منذ v0.14.2 (القيم في `.env.production.example`): `EVIDENCE_PER_MINUTE` و`EVIDENCE_FILES_PER_MINUTE` و`EVIDENCE_FILE_URL_MINUTES` و`EVIDENCE_STORAGE_URL_MINUTES` (معرض الشواهد). وتُراجع `REDIS_QUEUE_RETRY_AFTER=960`: فوق مهلة أطول مهمة (`ProcessSubmissionVideo`، 900 ثانية)، وبدونه 90 فيلتقط عامل آخر مقطعاً ما زال يُعالج.
6. **المجدول:** `$DC exec -T scheduler php artisan schedule:list` يُظهر المهام كلها، ومنها `ejaz:refresh-stats` كل عشر دقائق و`ejaz:close-expired-attempts` كل دقيقة.
7. **«للقراءة فقط» (يعتمد على #132):** يُفتح كتاب «للقراءة فقط» في المكتبة فتُرسم صفحاته. ثم اسم عامل القارئ وفحص نوعه:

   ```bash
   $DC exec -T app sh -c 'ls public/build/assets | grep "\.mjs$"'
   curl -sI https://ejaz.sa/build/assets/<الاسم>.mjs | grep -i '^content-type'   # application/javascript
   ```

   وإن ردّ `application/octet-stream` فالقارئ معطّل: صورة الويب لم تُبنَ بالإصدار الجديد، أو إعداد nginx لم يُنسخ على الخادم بلا حاويات.
8. **وثائق الحوكمة:** ترحيل `is_view_only` جعل كل وثيقة بملف «للاطلاع فقط» (`docs/decisions/2026-11-01-director-notes.md`، البند ٢٢). تُراجع الوثائق في اللوحة مع الجمعية، ويُفتح التنزيل لما تقرر فتحه وحده.
9. **القائمة وخريطة الموقع:** الشريط بسبعة عناصر بترتيبها: الرئيسية، عن الجمعية، مكتبة إعجاز، المركز الإعلامي، الحوكمة، الخدمات الإلكترونية، تواصل معنا. و`curl -s https://ejaz.sa/sitemap.xml | head` يرد XML بروابط المنشور.
10. **الصحة:** `$DC exec -T app php artisan ejaz:health` سليم (القاعدة، وRedis، والطوابير والمهام الفاشلة، والقرص، والقرص الخاص، ونبض المجدول).

### التراجع عن v0.15.0: بالوسم وحده

- **التراجع بإعادة نشر الوسم السابق** لا بشيء غيره: `EJAZ_TAG` في `.env` إلى الوسم السابق، ثم `$DC up -d app horizon scheduler web` (صوره باقية على الخادم — `cutover.md` «ب»، «بحزمة الحاويات»). وللخادم بلا حاويات: `RUN_MIGRATIONS=false bash scripts/deploy.sh <الوسم السابق>`.
- **لا `migrate:rollback` في هذا الإصدار**، خلافاً للخطوة ٣ في `cutover.md` «ب»:
  - ترحيلات v0.15 إضافة في البنية: جداول وأعمدة جديدة قابلة للفراغ أو بقيمة افتراضية، وفهارس، وقيد أقسام المكتبة الأربعة (وهي القيم التي تحفظها اللوحة من قبل). والإصدار السابق لا يقرأ الجديد منها، فبقاؤها لا يكسر شيئاً إلا ما في النقطة التالية. وما يعدّل البيانات منها (ملاحظات البراهين ودليلها، ومقاطع «عن الجمعية» المكررة، وإظهار «شركاؤنا» في الرئيسية مرة، واسم «الكتب والمؤلفات») تصحيح محتوى يعمل به الإصدار السابق كما هو، ويبقى بعد التراجع. والقائمة الكاملة: `git diff --name-only v0.14.2 v0.15.0 -- database/migrations`.
  - **حالتان يجب علمهما قبل التراجع بالوسم:**
    - **وثيقة برابط بلا ملف** (`documents.file` صار قابلاً للفراغ): شيفرة v0.14.2 تفترض الملف دائماً، فتعطي كل وثيقة رابط خطأ أو رابط تنزيل معطلاً في الحوكمة. فقبل التراجع تُسرد ويُلغى نشرها من اللوحة:

      ```bash
      $DC exec -T pgsql psql -U ejaz -d ejaz -c "SELECT id, slug FROM documents WHERE NULLIF(file, '') IS NULL AND deleted_at IS NULL;"
      ```

    - **«للاطلاع فقط» يسقط مؤقتاً:** v0.14.2 لا تعرف `is_view_only`، فكل وثيقة حوكمة تعود قابلة للتنزيل حتى العودة إلى v0.15. فإن كان في الوثائق ما لا يجوز تنزيله، يُلغى نشره قبل التراجع.
  - والرجوع يتلف بيانات: يحذف الجداول الجديدة بما كُتب فيها بعد النشر (نسخ الصفحات، والإنجازات، ومعارض الشواهد، و«قراءاتي»)، ويسقط أعمدة مثل `is_view_only` بقرارات المحرر. وترحيل الأقسام الأربعة لا يعيد ما حوّله. ورجوع ترحيل «الوثيقة برابط» يفشل ما دامت في القاعدة وثيقة رابط بلا ملف (`docs/decisions/2026-10-30-document-url.md`).
- **وإن تلفت البيانات نفسها:** الاستعادة من نسخة البند ٢ بقرار صفوان (`scripts/restore.md`، القسم ٣ للحاويات).

## إن فشل النشر

السكربت يتوقف عند أول خطأ. إن وقع قبل وضع الصيانة (الفحوص، وسحب الوسم، والبناء، وتشغيل القاعدة) فالإصدار القديم باقٍ يعمل. وإن وقع بعده بقي الموقع في وضع الصيانة (صفحة 503 العربية).

- **بين وضع الصيانة والترحيل:** الحاويات القديمة ما زالت تعمل، فـ `$DC exec -T app php artisan up` بعد التحقق.
- **في الترحيل أو بعده:** `$DC run --rm --no-deps app php artisan migrate:status`، ثم التراجع (`cutover.md` «ب»).
- السجل: `$DC logs --tail=200 app horizon web`.

## حدود الرفع

من `roadmap.md`: ملف الصوت في المكتبة حتى 25 ميجابايت، وPDF حتى 20. فـ `client_max_body_size 32M` في nginx، و`upload_max_filesize` و`post_max_size` بـ 32M في PHP، و`memory_limit = 512M` (داخل الصورة). وأجزاء مقاطع المسابقات دونها، والرفع المباشر لا يمر بالخادم أصلاً. وإن رُفع حد ملف فيُرفع الثلاثة معاً، ويصير فحص PDF تدفقياً.

ولمقاطع المسابقات: `COMPETITION_VIDEO_UPLOAD=s3`، ونطاق التخزين في `CSP_CONNECT_SRC`، وCORS الحاوية لنطاق البوابة وحده، وقاعدة إلغاء الرفع غير المكتمل بعد يوم (`deploy/storage/`)، و`ffmpeg` في الصورة، ومساحة مؤقتة كافية للعمال (`deploy/provider-checklist.md`).

## الملفات الخاصة

- على تخزين S3 لدى مزود الخادم في منطقته (`FILESYSTEM_PRIVATE_DISK=s3`) بالواجهة القياسية (`AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` والحاوية من البيئة)، فيعمل مع أي مزود متوافق بلا تغيير في الشيفرة. واختبار `tests/Feature/Platform/S3CompatibleStorageTest.php` يتحقق من الروابط الموقّعة للقرص الخاص ولأجزاء الرفع المباشر بعنوان مخصص.
- الحاوية خاصة بلا وصول عام، وإصداراتها مفعّلة.
- **نسختها عند المزود الثاني:** `scripts/backup.sh` ينسخ الحاوية يومياً إلى حاوية النسخ مشفّرة عند العميل (rclone crypt) متى ضُبطت `BACKUP_SOURCE_*` في `.env.backup` (`deploy/readiness.md` §١٤). والنسخة الأولى قد تطول بحد Contabo، فتُشغَّل يدوياً قبل تفعيل المؤقت (`handoff-server.md`).
- ملفات الوثائق والمكتبة المنشورة تُحوَّل بعد فحص النشر إلى رابط تخزين موقّع قصير (`CONTENT_FILE_URL_MINUTES`، 60 دقيقة افتراضياً)، فلا تمر عبر PHP (خطة I8 في `security/2026-10-10-security-audit.md`). ويلزمه نطاق التخزين في `CSP_MEDIA_SRC` (الصوت) و`CSP_FRAME_SRC` (عرض PDF مدمجاً)، ويفحصهما `verify-server.sh --server`. والوثيقة المنشورة خطأً ببيانات شخصية يُحذف ملفها أو يُستبدل، لأن رابطاً صدر قبل إلغاء نشرها يبقى صالحاً حتى نهاية مدته.

## الانتقال إلى خدمة مُدارة (لاحقاً، بلا تغيير في الشيفرة)

متى توفرت قاعدة PostgreSQL 17 أو ذاكرة Redis 7 مُدارتان لدى المزود وثبت تفوقهما:

1. نسخة احتياطية (`ejaz-backup.service`)، ووضع الصيانة.
2. القاعدة: استعادة النسخة في الخدمة المُدارة (`scripts/restore.md`)، ثم في `.env`: `DB_HOST` و`DB_PORT` و`DB_DATABASE` و`DB_USERNAME` و`DB_PASSWORD` (و`DB_SSLMODE=require` إن طلبه المزود)، ومثلها `PGHOST` وأخواته في `.env.backup`.
3. الذاكرة: `REDIS_HOST` و`REDIS_PORT` و`REDIS_PASSWORD`. والجلسات والطوابير المعلّقة تبدأ من جديد، فيُختار وقت هادئ. وسياسة الإخلاء في الخدمة `volatile-lru` أو `noeviction` (`performance.md`).
4. حذف `bundled` من `COMPOSE_PROFILES`، ثم `$DC up -d --remove-orphans`، ثم رفع الصيانة و`ejaz:health`.
5. يبقى الحجمان القديمان أسبوعين للتراجع، ثم يُحذفان.

## الفهرسة

- `SEO_INDEXABLE=true` في الإنتاج وحده بعد نقل النطاق (`cutover.md`).
- robots.txt وsitemap.xml من لارافيل: لا ملف `public/robots.txt`، ولا `location = /robots.txt` بلا `try_files` في nginx.

## Hostinger KVM (المقترح في 26 أكتوبر 2026)

> **مقترح لا قرار:** خادم Hostinger KVM 8 (8 معالجات افتراضية، و32 جيجابايت، و400 جيجابايت NVMe) في ألمانيا، بنظام Ubuntu 24.04 LTS. ولا شراء قبل موافقة صفوان على التكلفة، ولا بيانات حقيقية قبل شروط النقل خارج المملكة (`decisions.md` 18 أكتوبر). وما يُتحقق منه قبل الشراء في `deploy/provider-checklist.md` «Hostinger KVM».

### ما يوفره Hostinger وما نضيفه

- **من Hostinger (من لوحة hPanel):**
  - **جدار ناري على مستوى المزود:** قواعد للوارد تُربط بالخادم. وهو الطبقة الحاسمة، لأن Docker يتجاوز ufw في المنافذ المنشورة.
  - **لقطة يدوية واحدة** (snapshot): تُحذف بعد 20 يوماً، وبإعادة تثبيت النظام أو الاستعادة. فهي للرجوع قبل عملية خطرة، لا نسخة.
  - **نسخ أسبوعية آلية** عند المزود نفسه، والاستعادة منها تمحو الخادم كله وتقفله أثناءها. والنسخ اليومية عنده إضافة مدفوعة لا تلزمنا.
- **ما نضيفه نحن:**
  - **النسخ اليومي المشفّر خارج الخادم وخارج المزود:** `scripts/backup.sh` إلى حاوية Hetzner Object Storage (مزود ثانٍ، بقفل الكائنات). ونسخ Hostinger الأسبوعية احتياط ثالث لا بديل: عند المزود نفسه، وغير مشفّرة بمفتاحنا، وعمرها حتى أسبوع.
  - ufw وfail2ban والتحديثات الأمنية وSSH بالمفتاح وحده: `scripts/server-bootstrap.sh`.
  - المراقبة: `ejaz:health` ومؤقته، و`/up` من أداة خارجية (`monitoring.md`).

### تخزين الملفات الخاصة: لا تخزين S3 عند Hostinger

Hostinger لا يبيع تخزين كائنات متوافقاً مع S3، والحزمة كانت تفترضه عند مزود الخادم (Contabo). فالخياران (القرار لصفوان، والمقترح أ):

- **(أ) القرص المحلي على NVMe الخادم (المقترح للإطلاق):** بلا تغيير في الشيفرة، والنسخ اليومي ينسخ `storage/app/private` مشفّراً إلى Hetzner. وقيوده:
  - رفع ملفات اللوحة حتى 32 ميجابايت (حد nginx وPHP). فالفيديو التعريفي للاختبار الإلكتروني ووسائط الأسئلة فوق ذلك لا تُرفع، حتى يُرفع الحد لمسار `/livewire/upload-file` وحده بفرع مستقل.
  - مقاطع المسابقات بالرفع المجزأ عبر الخادم (`COMPETITION_VIDEO_UPLOAD=app`)، وتشغل القرص نفسه. ومسابقة الطلاب (اختبار موقوت) لا رفع فيها.
  - التطبيق على خادم واحد، والقرص يتسع للبداية (400 جيجابايت)، وتُراقب المساحة.
- **(ب) Hetzner Object Storage في ألمانيا للملفات:** الحزمة كما هي (`FILESYSTEM_PRIVATE_DISK=s3`). لكن الملفات ونسخها يصيران عند Hetzner، فتنتقل النسخ إلى مزود ثالث، أو تُقبل المخاطرة بقرار مكتوب.

### الخطوات من الصفر حتى `ejaz:health` سليم

**١. الشراء والإعداد في hPanel** (بحساب الجمعية وبريدها، وبتحقق ثنائي):

1. KVM 8، والموقع ألمانيا، والنظام «Ubuntu 24.04» نظيفاً بلا لوحة تحكم (`handoff-server.md` «ما لا تفعله»).
2. مفتاح SSH العام في خانة المفاتيح عند الإعداد (يُضاف لـ root، وينسخه سكربت التجهيز).
3. **الجدار الناري** (VPS ← Security ← Firewall): إعداد جديد بثلاث قواعد قبول TCP: 22 من عنوان الإدارة وحده إن ثبت (وإلا من الكل، وfail2ban يحميه)، و80 و443 من الكل. ثم يُربط بالخادم ويُفعَّل.
4. **IPv6:** إن كان للخادم عنوان IPv6 فقواعد الجدار تشمله، وإلا فلا سجل `AAAA` له في `cutover.md`.

**٢. التجهيز الأساسي** (من جهازك، ثم بـ root مرة واحدة):

```bash
scp scripts/server-bootstrap.sh root@<IP>:/root/
ssh root@<IP>
less /root/server-bootstrap.sh                      # يُقرأ قبل تشغيله
ADMIN_USER=ejaz ADMIN_CIDR=<عنوان الإدارة>/32 bash /root/server-bootstrap.sh   # ADMIN_CIDR اختياري، ويسأل عن كلمة مرور sudo
```

ثم من نافذة جديدة قبل إغلاق جلسة root: `ssh ejaz@<IP>`، ثم `sudo -v` و`docker ps`. ومن هنا كل شيء بالمستخدم `ejaz`.

- السكربت يطلب كلمة مرور sudo للمستخدم في الطرفية قبل إغلاق دخول root، ولا يُغلقه بدونها. وفي تشغيل بلا طرفية يُنشئ المستخدم ويتوقف قبل SSH: `passwd ejaz` ثم يُعاد تشغيله.
- `ADMIN_CIDR` يُرفض إن لم يكن عنوان جلستك داخله (أو يطلب تأكيداً في الطرفية)، فلا تُقفل خارج الخادم.
- وبلا `ADMIN_CIDR` فالمنفذ 22 بـ `ufw limit`: يحجب العنوان الذي يفتح 6 اتصالات أو أكثر في 30 ثانية، فقد يُحجب سكربت أو أداة تفتح جلسات متتابعة (scp ثم ssh مراراً) مؤقتاً. ومع `ADMIN_CIDR` لا حد، فالمنفذ لعنوان الإدارة وحده.
- ومن شغّله بـ `sudo` لا بـ root مباشرة: `sudo --preserve-env=SSH_CLIENT ...`، لأن sudo يحذف `SSH_CLIENT` فلا يُعرف عنوان الجلسة لفحص `ADMIN_CIDR`.
- إعادة التشغيل بـ `ADMIN_CIDR` مختلف تضيف قاعدة 22 جديدة ولا تحذف القديمة: بعد الدخول بالجديدة `sudo ufw status numbered`، ثم `sudo ufw delete <رقم القاعدة القديمة>`، ثم يُعدَّل جدار hPanel مثلها.

**٣. لقطة في hPanel** بعد التجهيز («قبل المشروع»)، فإن أخطأت خطوة لاحقة رجعت إليها.

**٤. المشروع والإعداد:**

```bash
cd /opt/ejaz
git clone <المستودع> .            # بمفتاح نشر للقراءة وحدها: ssh-keygen -t ed25519، والعام في GitHub ← Deploy keys
git checkout v0.14.0
cp .env.production.example .env && chmod 600 .env
cp .env.backup.example .env.backup && chmod 600 .env.backup
```

ثم `.env` كما في «ثانياً»، ومعه لخادم KVM 8 (القيم من `deploy/provider-checklist.md` «توزيع الذاكرة التقريبي لخادم 32 جيجابايت»):

```dotenv
PG_MAX_CONNECTIONS=200
PG_SHARED_BUFFERS=4GB
PG_EFFECTIVE_CACHE_SIZE=12GB
PG_WORK_MEM=16MB
PG_MAINTENANCE_WORK_MEM=512MB
REDIS_MAXMEMORY=2gb
FPM_MAX_CHILDREN=80
```

ولخيار (أ) القرص المحلي:

```dotenv
FILESYSTEM_PRIVATE_DISK=local
COMPETITION_VIDEO_UPLOAD=app
LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=
CSP_CONNECT_SRC=
CSP_MEDIA_SRC=
CSP_FRAME_SRC=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
```

- كل قيمة بين `<...>` تُستبدل أو تُفرغ، فلا يبقى في `.env` نص فيه مسافة بلا علامات اقتباس.
- `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=` الفارغ يرجع إلى القرص الافتراضي `FILESYSTEM_DISK=local` (Livewire يقرؤه بـ `?:`، فالنص الفارغ لا يصير اسم قرص). مجرَّب: `FileUploadConfiguration::disk()` يرد `local`.
- و`COMPETITION_NATIONAL_ID_HMAC_KEY` بقيمة `openssl rand -base64 48` (`CHANGELOG.md` 0.14.0).
- والبريد من مايكروسوفت 365 (شروطه في `cutover.md` «ما تحتاجه الجمعية يوم النشر»):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=info@ejaz.sa
MAIL_PASSWORD=<من مدير كلمات المرور>
MAIL_FROM_ADDRESS=info@ejaz.sa
```

وفي `.env.backup` لخيار (أ): `BACKUP_SOURCE_S3_BUCKET=none`، فيُنسخ `storage/app/private` و`storage/app/public` من الحجم مشفّرين. وبقية قيمه (حاوية Hetzner ومفتاح GPG وكلمتا التشفير) كما في «ثانياً».

**٥. الشهادة.** حاوية nginx لا تبدأ بلا ملفي الشهادة، فشهادة مؤقتة ذاتية أولاً، ثم Let's Encrypt:

```bash
sudo openssl req -x509 -nodes -newkey rsa:2048 -days 7 -subj "/CN=ejaz.sa" \
  -keyout /etc/ejaz/tls/privkey.pem -out /etc/ejaz/tls/fullchain.pem
sudo chmod 600 /etc/ejaz/tls/privkey.pem
sudo apt-get install -y certbot
sudo install -d /etc/letsencrypt/renewal-hooks/deploy
```

وخطاف التجديد `/etc/letsencrypt/renewal-hooks/deploy/ejaz.sh` (بصلاحية 755): ينسخ الشهادة إلى مجلد الحاوية (لا روابط رمزية فيه) ويعيد تحميل nginx:

```sh
#!/bin/sh
set -e
install -m 644 "$RENEWED_LINEAGE/fullchain.pem" /etc/ejaz/tls/fullchain.pem
install -m 600 "$RENEWED_LINEAGE/privkey.pem" /etc/ejaz/tls/privkey.pem
cd /opt/ejaz && docker compose --env-file .env -f deploy/compose.production.yaml exec -T web nginx -s reload
```

- **قبل نقل النطاق** (ejaz.sa ما زال عند hosting.com): بتحدي DNS، وسجل `_acme-challenge` يضيفه صاحب صلاحية DNS:
  - `sudo certbot certonly --manual --preferred-challenges dns -d ejaz.sa -d www.ejaz.sa`
  - ثم بعد الخطوة ٧: `sudo env RENEWED_LINEAGE=/etc/letsencrypt/live/ejaz.sa sh /etc/letsencrypt/renewal-hooks/deploy/ejaz.sh`
  - وهذه الشهادة لا تتجدد آلياً، فتُستبدل بعد النقل.
- **بعد النقل** (`cutover.md`): شهادة بتحدي HTTP تتجدد وحدها:
  - `sudo certbot certonly --webroot -w /var/lib/ejaz/acme -d ejaz.sa -d www.ejaz.sa --force-renewal`
  - ثم خطاف النسخ يدوياً، لأن certbot لا يشغّل خطافات `renewal-hooks/deploy` إلا في `renew`: `sudo env RENEWED_LINEAGE=/etc/letsencrypt/live/ejaz.sa sh /etc/letsencrypt/renewal-hooks/deploy/ejaz.sh`، ثم `curl -vI https://ejaz.sa 2>&1 | grep -i "issuer"` يعرض Let's Encrypt.
  - ثم `sudo certbot renew --dry-run` بلا خطأ (التجديدات بعدها تشغّل الخطاف وحدها).

**٦. مفتاح النسخ والوحدات:** «أولاً» الخطوتان 8 و9 كما هما (`/etc/ejaz/backup-public.asc`، ثم `deploy/systemd/compose/`). ومؤقت النسخ لا يُفعَّل قبل أول نسخة يدوية (الخطوة ٩).

**٧. أول نشر:** «ثالثاً» كما هي، بالوسم `v0.14.0`، ومعها ما بعد النشر في `CHANGELOG.md` 0.14.0 (`CompetitionListsSeeder` و`ejaz:create-admin`).

**٨. التحقق حتى `ejaz:health` سليم:**

```bash
DC="docker compose --env-file .env -f deploy/compose.production.yaml"
$DC ps                                              # كل الخدمات healthy
$DC exec -T app php artisan ejaz:health             # يخرج بـ 0 بلا ✗
$DC exec -T app php artisan migrate:status | tail -16
echo "127.0.0.1 ejaz.sa www.ejaz.sa" | sudo tee -a /etc/hosts
bash scripts/verify-server.sh https://ejaz.sa --server   # فحصا الفهرسة وحدهما يفشلان ما دام SEO_INDEXABLE=false
sudo sed -i '/ ejaz.sa www.ejaz.sa$/d' /etc/hosts
```

وإن فشل `ejaz:health` فرسالته تسمّي الفحص (القاعدة، أو Redis، أو الطوابير والمهام الفاشلة، أو مساحة القرص، أو القرص الخاص، أو نبض المجدول بعد دقيقتين من التشغيل). والسجل: `$DC logs --tail=200 app horizon scheduler web`.

**٩. أول نسخة واستعادتها:** `sudo systemctl start ejaz-backup.service`، ثم `journalctl -u ejaz-backup.service -n 50`، ثم تجربة الاستعادة (`scripts/restore.md`)، ثم `sudo systemctl enable --now ejaz-backup.timer ejaz-health.timer`.

**١٠. لقطة ثانية في hPanel** («v0.14.0 سليم»)، وتُستبدل بلقطة جديدة قبل كل نشر كبير (لقطة واحدة متاحة).

### الصيانة الدورية على Hostinger

- **إعادة الإقلاع بعد تحديث النواة:** التحديثات الأمنية آلية بلا إعادة إقلاع. فأسبوعياً: إن وُجد `/var/run/reboot-required` يُعاد الإقلاع في وقت هادئ (`sudo reboot`)، والحزمة تعود بخدمة `ejaz-stack`، ثم `ejaz:health`.
- **المساحة:** `df -h /` و`docker system df` شهرياً، و`docker image prune` للصور القديمة مع إبقاء الوسم السابق للتراجع.
- **fail2ban:** `sudo fail2ban-client status sshd`.

## التجريبية

مطابقة للإنتاج ببيانات وهمية: الحزمة نفسها بـ `APP_ENV=staging` و`SEO_INDEXABLE=false` ونطاق فرعي، ومزود الرسائل `log`، وبريد وهمي، على خادم صغير شهراً (المرشح Contabo Cloud VPS). وعليها الفحص الأمني الفعلي. واختبار التحمل بإعدادها نفسه على خادم الإنتاج قبل تجهيزه للإنتاج، لأن الخادم الصغير ليس بمواصفات الإنتاج (`deploy/staging-plan.md` «اليوم ٥»).

---

## ملحق: خادم بلا حاويات

إن تعذر Docker لدى المزود، فالبديل المكافئ:

- PHP 8.4-FPM بإضافاتها، وnginx (`deploy/nginx/ejaz.conf`)، وPostgreSQL 17، وRedis 7 (`deploy/redis/redis.conf`)، و`ffmpeg`، و`postgresql-client-17` و`gnupg` و`awscli`.
- `deploy/php/production.ini` إلى `/etc/php/8.4/{fpm,cli}/conf.d/99-ejaz.ini`.
- المشروع في `/var/www/ejaz` **يملكه مستخدم نشر مستقل** (`ejaz-deploy`) يشغّل `scripts/deploy.sh`، لا مستخدم PHP. و`www-data` يقرأ الشيفرة ولا يكتب إلا في `storage` و`bootstrap/cache`، فلا يكتب تنفيذٌ عن بعد عبر PHP باباً خلفياً في `app/` أو `vendor/`. و`.env` بصلاحية 640 لمستخدم النشر ومجموعة `www-data`، و`sudo` لإعادة تحميل PHP-FPM وحدها لمستخدم النشر:
  ```bash
  sudo useradd --system --create-home ejaz-deploy
  sudo chown -R ejaz-deploy:www-data /var/www/ejaz && sudo chmod -R g-w /var/www/ejaz
  # الكتابة فيهما لـ PHP ولمستخدم النشر (ذاكرة الإعدادات والمسارات عند النشر)
  sudo chown -R www-data:ejaz-deploy /var/www/ejaz/storage /var/www/ejaz/bootstrap/cache
  sudo chmod -R g+w /var/www/ejaz/storage /var/www/ejaz/bootstrap/cache
  # في /etc/sudoers.d/ejaz:
  ejaz-deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
  ```
- الوحدات: `deploy/systemd/ejaz-horizon.service`، و`ejaz-scheduler.service` مع مؤقته (خادم واحد)، و`ejaz-health.service` مع مؤقته، و`ejaz-backup.service` مع مؤقته (بـ `/etc/ejaz/backup.env`).
- النشر: `bash scripts/deploy.sh v1.2.0` (الخطوات نفسها، ومعها `composer install --no-dev` و`npm ci && npm run build` على الخادم و`horizon:terminate` وإعادة تحميل PHP-FPM). ومتعدد الخوادم: الترحيل على خادم واحد، والبقية `RUN_MIGRATIONS=false`.
