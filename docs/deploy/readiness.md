# جاهزية التشغيل: كل بند بملفاته ودليله وطريقة التحقق منه

**الحال (12 أكتوبر 2026): كل البنود أدناه «جاهز بانتظار الخادم».**
- قرار صفوان: لا خادم ولا خدمات مُدارة قبل اكتمال المشروع (`decisions.md`، 11 أكتوبر).
- فما يلزم كل بند مكتوب ومختبر في المستودع، ويبقى تنفيذه على الخادم.
- لا حساب ولا اشتراك أُنشئ، ولا سر في قيت.

**الفحص الجامع بعد التجهيز وبعد كل نشر:**

```bash
bash scripts/verify-server.sh https://staging.<النطاق> --staging --server   # الخادم التجريبي
bash scripts/verify-server.sh https://ejaz.sa --server                       # الإنتاج بعد نقل النطاق
```

- يفحص رؤوس الحماية، وCSP بنطاقات صريحة، و`/up`.
- ويفحص خريطة الموقع وrobots حسب البيئة، والأيقونة، وصفحة 404 العربية.
- ويمر على **كل** رابط في `docs/legacy-import/redirects.csv`: 301 إلى وجهته.
- وعلى الخادم (`--server`): `ejaz:health`، وحدود PHP وnginx، والمجدول وHorizon، ومؤقتا النسخ والصحة، وآخر نسخة، و`SEO_INDEXABLE` و`CSP_CONNECT_SRC`.
- جُرّب على النسخة المحلية بمحتوى الاستيراد الفعلي: التحويلات الـ154 كلها سليمة. وفشلت الفحوص التي لا تكون إلا على https (HTTPS وHSTS) كما يُتوقع محلياً.

## ١. حزمة النشر المحايدة للمزوّد

- **الملفات:**
  - `deploy/compose.production.yaml`: التطبيق، وnginx، وHorizon، والمجدول، وPostgreSQL 17، وRedis 7، والنسخ.
  - `deploy/docker/`، و`scripts/deploy-compose.sh` (النشر بوسم، ويكتب الوسم العامل في `EJAZ_TAG` بـ `.env`، فيبقى بعد إعادة تشغيل الخادم)، و`.env.production.example`.
  - نطاق nginx من `SITE_HOST` (قالب في صورة الويب)، فالحزمة نفسها للإنتاج والتجريبية.
  - البديل بلا حاويات: `scripts/deploy.sh` و`deploy/nginx/ejaz.conf` و`deploy/systemd/`.
- **الدليل:** `docs/deploy.md` («أولاً» إلى «رابعاً»، و«إن فشل النشر»، والملحق).
- **التحقق:**
  - `$DC ps` تكون فيه كل الخدمات healthy.
  - ثم `bash scripts/verify-server.sh <الرابط> --server`.

## ٢. النسخ الاحتياطي المشفّر

- **الملفات:**
  - `scripts/backup.sh`: `pg_dump` مضغوط مشفّر بمفتاح GPG عام إلى حاوية S3 منفصلة.
  - `.env.backup.example`، وخدمة `backup` في الحزمة، و`deploy/systemd/compose/ejaz-backup.service` مع `ejaz-backup.timer`.
  - `deploy/storage/lifecycle-backups.json`: هي وحدها تحذف النسخ بعد 30 يوماً. ومفتاح الخادم كتابة بلا حذف، فمن يخترق الخادم لا يمحو النسخ.
  - دور قاعدة للقراءة وحدها للنسخ (`ejaz_backup`، في `.env.backup.example`).
- **الدليل:** `scripts/restore.md` (التجربة الشهرية والاستعادة الفعلية)، و`docs/deploy.md` (الخطوة 8 والوحدات).
- **التحقق:**
  - `systemctl start ejaz-backup.service`، ثم `systemctl show -p Result --value ejaz-backup.service` يرد `success`.
  - والنسخة الجديدة في الحاوية.
  - ثم «تجربة الاستعادة» في `restore.md` على قاعدة باسم مختلف.
  - `verify-server.sh --server` يفحص المؤقت وآخر نتيجة.
- **ينتظر صفوان:** من يحفظ المفتاح الخاص وعبارة مروره خارج الخادم (`open-questions.md`).

## ٣. المراقبة والتنبيه

- **الملفات:**
  - `php artisan ejaz:health`: القاعدة، والذاكرة، والطابور، والمهام الفاشلة، ومساحة القرص، والمجدول، و**التخزين الخاص**. وأضيف التخزين في هذا الفرع بكتابة ملف صغير وقراءته وحذفه.
  - `deploy/systemd/compose/ejaz-health.service` مع `ejaz-health.timer` (كل خمس دقائق).
  - `/up` للفحص الخارجي.
- **الدليل:** `docs/monitoring.md`: ما يُراقب، ومتى يُنبَّه، والسجلات، ووقت الذروة.
- **التحقق:**
  - `$DC exec -T app php artisan ejaz:health` يطبع ✓ لكل فحص.
  - إيقاف حاوية المجدول خمس دقائق يجعله يفشل بـ«لا نبض من المجدول».
  - اختبار `tests/Feature/Platform/HealthCheckTest.php`.
- **ينتظر صفوان:** أداة التنبيه ومن يُنبَّه (`open-questions.md`).

## ٤. المجدول

- **الملفات:**
  - حاوية `scheduler` (`schedule:work`) في الحزمة.
  - وبلا حاويات `deploy/systemd/ejaz-scheduler.service` مع مؤقته، على خادم واحد.
  - المهام في `routes/console.php`، وكلها `onOneServer`.
- **الدليل:** `docs/deploy.md` («البنية» والملحق).
- **التحقق:**
  - `$DC exec -T app php artisan schedule:list` يعرض المهام (ومنها `ejaz:refresh-stats` كل عشر دقائق).
  - و`ejaz:health` يفحص نبض المجدول.
  - `verify-server.sh --server` يفحص الاثنين.

## ٥. حدود الرفع

- **الملفات:**
  - `deploy/php/production.ini`: `upload_max_filesize` و`post_max_size` بـ 32M، و`memory_limit` بـ 512M.
  - `deploy/docker/nginx.conf` و`deploy/nginx/ejaz.conf`: `client_max_body_size 32M`.
- **الدليل:** `docs/deploy.md` «حدود الرفع».
- **التحقق:**
  - `verify-server.sh --server` يقرأ القيم الثلاث من PHP داخل الحاوية، و`client_max_body_size` من `nginx -T`.
  - ورفع ملف صوت بحجم 24 ميجابايت من اللوحة على الخادم التجريبي.

## ٦. قرص الملفات الخاصة على S3 القياسي

- **الملفات:**
  - `config/filesystems.php` (`AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` من البيئة).
  - `FILESYSTEM_PRIVATE_DISK=s3` في `.env.production.example`.
  - `deploy/storage/` (CORS، ودورة حياة الملفات الخاصة).
- **الدليل:** `deploy/storage/README.md`، و`docs/deploy.md` «الملفات الخاصة».
- **التحقق:**
  - فحص «التخزين الخاص» في `ejaz:health` (كتابة وقراءة وحذف على القرص الخاص).
  - واختبار `tests/Feature/Platform/S3CompatibleStorageTest.php` (الروابط الموقّعة بعنوان مخصص).
  - ورفع وثيقة من اللوحة ثم فتحها من الموقع.

## ٧. فهرسة محركات البحث

- **الملفات:**
  - `SEO_INDEXABLE` (يكون `false` في التجريبية وحتى يوم النقل، و`true` في الإنتاج وحده).
  - robots.txt وsitemap.xml من لارافيل (`SitemapController`)، ولا ملف ثابت لهما.
  - العنوان والوصف وcanonical والأيقونة في القالب العام.
- **الدليل:** `docs/deploy.md` «الفهرسة»، و`docs/launch-plan.md` (Search Console).
- **التحقق:**
  - `verify-server.sh` يفحص سطر Sitemap في الإنتاج، و`Disallow: /` في التجريبية (`--staging`)، وخريطة الموقع، و`SEO_INDEXABLE` في `.env`.
  - واختبارات `SitemapTest` و`LegacySiteSeoTest`.

## ٨. إعدادات رفع مقاطع المسابقات

- **الملفات:**
  - `COMPETITION_VIDEO_UPLOAD=s3` و`COMPETITION_VIDEO_URL_MINUTES` و`COMPETITION_VIDEO_FFPROBE` في `.env.production.example`.
  - `CSP_CONNECT_SRC` بعنوان واجهة S3.
  - `deploy/storage/cors.json`، وقاعدة إلغاء الرفع غير المكتمل بعد يوم في `lifecycle-private.json`.
  - `ffmpeg` في صورة التطبيق.
  - **طابور `videos` مستقل** بعمال قليلين (`supervisor-videos` في `config/horizon.php`، و`COMPETITION_VIDEO_WORKERS`) بمهلة المهمة نفسها (900 ثانية). و`REDIS_QUEUE_RETRY_AFTER=960` فوقها، فلا يُعالج المقطع مرتين.
  - وHorizon معرّف للتجريبية كالإنتاج، فيعمل فيها العمال.
- **الدليل:** `docs/deploy.md` «حدود الرفع» (فقرة المقاطع)، و`deploy/storage/README.md`.
- **التحقق:**
  - `verify-server.sh --server` يتحقق أن نطاق `CSP_CONNECT_SRC` ظاهر فعلاً في رأس CSP متى كان الرفع مباشراً، ومن وجود عمال `supervisor-videos`.
  - وCSP يقبل الأصل الصريح وحده: اختبار `ContentSecurityPolicyTest`.
  - ورفع مقطع تجريبي في مسابقة على الخادم التجريبي.

## ٩. سياسة أمان المحتوى (CSP)

- **الملفات:**
  - `CSP_IMG_SRC` و`CSP_CONNECT_SRC` و`CSP_MEDIA_SRC` و`CSP_FORM_ACTION` في البيئة.
  - الافتراضي: `img-src 'self' data: blob:`، و`connect-src 'self'`.
  - **وفي هذا الفرع:** كل قيمة من البيئة يجب أن تكون أصلاً صريحاً `https://نطاق` (أو `https://*.نطاق`، أو `wss://` للاتصال)، وما سواها يُهمل. فلا يوسّع خطأ في الإعداد (`*` أو `https:` أو `'unsafe-inline'`) السياسةَ كلها.
- **الدليل:** `docs/deploy.md` («ثانياً»)، و`security.md`.
- **التحقق:**
  - اختبار `tests/Feature/Platform/ContentSecurityPolicyTest.php`.
  - و`verify-server.sh` يرفض img-src أو connect-src بنطاق مفتوح.

## ١٠. اختبار الحمل لـ30,000 متقدم

- **الملفات:** `tests/load/`: سكربتات k6 للتصفح، والدخول، والتسجيل في برنامج، والتقديم في المسابقة واختبارها. وكل سكربت يرفض العمل على `ejaz.sa`.
- **الدليل:** `tests/load/README.md`: ملفات التصاعد والتزامن في الذروة، والتجهيز، والتشغيل، ومعايير القبول، والتقرير.
- **التحقق:**
  - تشغيل `smoke` لكل سكربت على الخادم التجريبي، ثم ملف الذروة.
  - ومعايير القبول في الدليل: p95، ونسبة الأخطاء، وعدم فقد أي تسجيل.
- **ينتظر:** الخادم التجريبي.

## ١١. قائمة التحقق قبل اختيار المزوّد

- **الملف:** `docs/deploy/provider-checklist.md`. فيه:
  - المنطقة وإقامة البيانات، والحوسبة، والقرص، وتخزين الكائنات، والشبكة.
  - وDNS وTLS، والنسخ، والدعم والشروط، والأسعار التي تُجمع، والحد الأدنى للمواصفات.
- **التحقق:** كل سطر فيه يُجاب عنه من لوحة المزود أو عرض سعره قبل التعاقد.
- **ينتظر صفوان:** اختيار المزود والتعاقد.

## ١٢. الخادم التجريبي

- **الملف:** `docs/deploy/staging-plan.md`: الهدف، والمواصفات، والخطوات أسبوعاً بأسبوع، ومعايير النجاح، والإتلاف.
- **التحقق:** معايير النجاح في الخطة، وأولها `verify-server.sh … --staging --server` بلا فشل.
- **ينتظر صفوان:** الموافقة على تكلفة الشهر.

## ١٣. خطة الإطلاق ونقل النطاق

- **الملفات:**
  - `docs/launch-plan.md`: النطاق بلا انقطاع، وسجلات البريد كما هي، وقائمة الفحص.
  - `docs/cutover.md` (التنفيذ والتراجع)، و`docs/launch-checklist.md`.
- **التحقق:** قائمة الفحص في `launch-plan.md`، و`verify-server.sh https://ejaz.sa --server` بعد النقل.
- **ينتظر صفوان:** الدخول على مسجل النطاق، والاستضافة، وSearch Console (البنود المعلَّمة في الخطة).
