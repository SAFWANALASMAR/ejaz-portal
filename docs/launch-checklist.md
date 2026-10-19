# قائمة الإطلاق

كل بند إنتاج في المرحلة 1 من `roadmap.md`، وكل سطر «قبل النشر / عند النشر / بعد النشر / عند الإطلاق» في `roadmap.md` و`security.md` و`requirements/01-foundation-public-site.md` (القسم ١٠)، مربوطاً بالسكربت أو الوثيقة التي تنفّذه.

> **الحال (11 أكتوبر 2026):** اختيار الخادم والتعاقد مؤجلان حتى يكتمل المشروع (`decisions.md`). فكل ما هنا جاهز، والإطلاق تنفيذ بالترتيب. العلامات: ✅ جاهز في المستودع (ينتظر التنفيذ على الخادم) — ⏳ ينتظر قراراً أو معلومة — ⬜ يُنفَّذ يوم الإطلاق.
>
> اختصار: `DC="docker compose --env-file .env -f deploy/compose.production.yaml"`.

## أ. قبل اختيار المزود

- ⏳ **التحقق قبل التعاقد** (المنطقة، والخادم، وتخزين S3 في المنطقة، والإرسال، واللقطات، والأسعار) — `deploy/provider-checklist.md`. ولا اعتماد على قاعدة أو ذاكرة مُدارة (`decisions.md`، 11 أكتوبر)، فلا يمنع غيابهما الإطلاق.
- ⏳ اعتماد صفوان للمزود (المرشح في قرار 18 أكتوبر) وتكلفة الخادم التجريبي — `deploy/staging-plan.md`.
- ⏳ **شروط النقل خارج المملكة** قبل أي بيانات حقيقية: اعتماد الجمعية المكتوب، وتقويم المخاطر، واتفاقيتا المعالجة، وسياسة الخصوصية — `decisions.md` (18 أكتوبر)، و`open-questions.md`.
- ⏳ معلومات من الجمعية: صلاحية DNS للنطاق، والوصول إلى الاستضافة الحالية ونسخة منها، ومزود الرسائل النصية، ومزود البريد، وأداة التنبيه، ومن يحفظ مفتاح النسخ الخاص — `open-questions.md`.

## ب. ما هو جاهز في المستودع

### البنية والنشر

- ✅ **بيئة الإنتاج** (بند `roadmap.md`): حزمة مستقلة عن المزود، بـ PostgreSQL وRedis على الخادم — `deploy/compose.production.yaml`، و`deploy/docker/`، و`.env.production.example`، و`docs/deploy.md` (أولاً وثانياً). والانتقال إلى خدمة مُدارة بالمتغيرات وحدها — `docs/deploy.md` «الانتقال إلى خدمة مُدارة».
- ✅ **حزمة النشر المستقلة عن المزود** و**التخزين بواجهة S3 القياسية** (بندا `roadmap.md`) — `config/filesystems.php` (`AWS_ENDPOINT` و`AWS_DEFAULT_REGION` و`AWS_USE_PATH_STYLE_ENDPOINT` ومجاميع التحقق من البيئة)، واختبار `tests/Feature/Platform/S3CompatibleStorageTest.php`، و`deploy/storage/`.
- ✅ **دليل «قائمة التحقق قبل اختيار المزوّد»** — `docs/deploy/provider-checklist.md`.
- ✅ **الخادم التجريبي** (الخطة؛ التكلفة ⏳ صفوان) — `docs/deploy/staging-plan.md`.
- ✅ **خطوات النشر:** `ejaz:refresh-content` بعد كل نشر، ولا `cache:clear`، و`PermissionsSeeder` بعد كل تغيير في `config/permissions.php` — `scripts/deploy-compose.sh` (يشغّل `AccessSeeder` = الأدوار ثم `PermissionsSeeder` في كل نشر، آمن للتكرار)، و`docs/deploy.md` «رابعاً».
- ✅ **المجدول في الإنتاج** على خادم واحد كل دقيقة — خدمة `scheduler` (`schedule:work`) في الحزمة، أو `deploy/systemd/ejaz-scheduler.timer` بلا حاويات. ونبضه يراقبه `ejaz:health`.
- ✅ **حدود الرفع** (26M على الأقل، وذاكرة 512M) — `deploy/docker/nginx.conf` و`deploy/nginx/ejaz.conf` (`client_max_body_size 32M`)، و`deploy/php/production.ini` (32M و512M)، و`docs/deploy.md` «حدود الرفع».
- ✅ **`zend.exception_ignore_args=On`** في PHP الإنتاج (`security.md`) — `deploy/php/production.ini` داخل الصورة.
- ✅ **`APP_DEBUG=false`، و`APP_URL` بـ https، وكعكة الجلسة Secure** — يفحصها `scripts/deploy-compose.sh` قبل كل نشر، و`config/session.php` آمن افتراضياً في الإنتاج.
- ✅ **`TRUSTED_PROXIES`** بعناوين الموزع بعينها (`security.md` «قبل كل إطلاق») — ينبّه السكربت إن خلا، و`docs/deploy.md` «ثانياً».
- ⏳ **قرص الملفات الخاصة على S3، وتقديم الوثائق والمكتبة برابط موقّع قصير أو X-Accel-Redirect** — القرص جاهز بالمتغيرات (`FILESYSTEM_PRIVATE_DISK=s3`). والتقديم بلا بث من PHP خطة I8 في `security/2026-10-10-security-audit.md` (تغيير في التطبيق يُبنى في بند مستقل قبل الذروة).

### الحماية

- ✅ **الفحص الأمني الداخلي الشامل** (بند `roadmap.md`، و«قبل كل إطلاق» في `security.md`، و«مراجعة أمنية» في `requirements/01`) — `docs/security/2026-10-10-security-audit.md` ثم الشامل بعد دمج كل الفروع `docs/security/2026-10-12-security-audit.md`: لا حرجة ولا عالية ولا متوسطة باقية (وعولجت فيه F1 وF5). ⬜ ويُعاد الجزء الفعلي (ZAP) على التجريبي (`deploy/staging-plan.md` اليوم ٤).
- ✅ **تضييق `img-src`** (بند `roadmap.md`) — `app/Http/Middleware/SecurityHeaders.php` و`CSP_IMG_SRC`، واختبار `tests/Feature/Platform/ContentSecurityPolicyTest.php`. ⬜ يُضاف نطاق التخزين أو شبكة التوصيل إلى `CSP_IMG_SRC` متى قُدّمت منه الصور.
- ✅ **رؤوس الحماية والتخزين المؤقت** — `SecurityHeaders`، و`ContentCache`، ورؤوس الملفات الثابتة في nginx.
- ⬜ **تسجيل الخروج من كل حساب إداري بعد الإطلاق** (كعكات التذكّر القديمة — F3) — `docs/cutover.md` يوم النقل.

### النسخ والمراقبة

- ⏳ **النسخ الاحتياطي** اليومي المشفّر خارج الخادم، والاحتفاظ، والاستعادة وتجربتها (`security.md` «النسخ الاحتياطي») — `scripts/backup.sh`، وخدمة `backup` في الحزمة، و`deploy/systemd/compose/ejaz-backup.service` مع `deploy/systemd/ejaz-backup.timer`، و`scripts/restore.md`، و`deploy/storage/lifecycle-backups.json`. ونسخ الملفات الخاصة على S3 (مقاطع المسابقات، ومرفقات المتقدمين، والنماذج المعبأة) عند المزود الثاني مبني، و`files/` كلها مشفّرة عند العميل بلا `--sse` (19 أكتوبر 2026 — `deploy/readiness.md` §١٤، ومُجرَّب على MinIO في `tests/backup/`). ويبقى تجريبه على الخادم، وكلمتا سر تشفير الملفات في مدير كلمات المرور مع مفتاح GPG الخاص.
- ✅ **المراقبة** (`security.md` «تنبيهات فورية») — `/up`، و`php artisan ejaz:health` (`deploy/systemd/compose/ejaz-health.service` مع مؤقته)، وفحوص صحة الحاويات، و`docs/monitoring.md`. ⏳ أداة التنبيه نفسها.

### المحتوى والفهرسة

- ✅ **نقل المحتوى وتحويل الروابط القديمة** (بند `roadmap.md`، و`requirements/01`): «عند الإطلاق» `LegacyRedirectsSeeder` ثم `import:legacy-site --dry-run` ثم بدونه — `docs/deploy.md` «ثالثاً». ⏳ اعتماد الجمعية للنصوص وملف الاستيراد الفعلي (`feature/legacy-content`).
- ✅ **فهرسة محركات البحث في الإنتاج وحده** (`SEO_INDEXABLE=true`، وrobots عبر `try_files`، و`curl .../robots.txt`) — `deploy/docker/nginx.conf` (لا `location = /robots.txt`)، و`docs/cutover.md` يوم النقل، والتجريبية `false` (`.env.production.example`).
- ✅ **أنواع البرامج** «عند النشر» (`feature/home-ed`): `db:seed --class=ProgramTypesSeeder --force` — `docs/deploy.md` «ثالثاً».
- ✅ **نقل النطاق** (آخر بند في `requirements/01` القسم ١٠)، والتراجع — `docs/cutover.md`.
- ✅ **اختبار أداء أساسي** (`requirements/01` القسم ١٠) و**اختبار التحمل** (المرحلة 2) — `tests/load/` و`tests/load/README.md`، ويُجرى على التجريبي (`deploy/staging-plan.md` اليوم ٥). ⏳ نافذة الذروة من الجمعية.

### ما يلزمه قرار أو حساب (`security.md` «عند كل نشر»)

- ⏳ **التحدي ضد الآليات** إن سُمح للضيوف في استبيان: `BOT_CHALLENGE_DRIVER=turnstile` ومفتاحاه.
- ⏳ **اجتماعات تيمز**: `ONLINE_MEETINGS_DRIVER=graph` (في القالب)، ثم تطبيق Entra ومفاتيحه في «ربط تيمز» باللوحة بعد وصول حساب Microsoft 365 (`guides/teams-meetings.md`). ولا يمنع الإطلاق: مطفأً يُحفظ الاجتماع عن بعد بلا رابط.
- ⏳ **مزود الرسائل النصية**: `SMS_DRIVER` (بدونه لا يعمل توثيق الجوال في الإنتاج — I2 في تقرير الفحص).
- 🗄️ **الدفع** (مؤرشف، لا يلزم للإطلاق): `PAYMENT_DRIVER=moyasar` ومفاتيحه، و`CSP_FORM_ACTION`، و`REVOKE` على جداول الدفع — بند المرحلة 5 في `roadmap.md`، متى أُعيد `PAYMENTS_ENABLED=true`.
- ⏳ **رفع مقاطع المسابقات في الإنتاج** (المرحلة 2): `COMPETITION_VIDEO_UPLOAD=s3`، و`CSP_CONNECT_SRC`، وCORS، وقاعدة إلغاء الرفع غير المكتمل — `.env.production.example`، و`deploy/storage/`، و`docs/deploy.md` «حدود الرفع». و`ffmpeg` في الصورة.

### خارج هذا البند (بنود برمجية في `roadmap.md`)

- ⬜ مهمة مجدولة تحذف ملفات القرص العام غير المرتبطة بسجل.
- ⬜ التنظيف المشترك بلا تغيير سلوك (مراجعة المركز الإعلامي).

## ج. يوم الإطلاق بالترتيب

1. ⬜ الخادم والحاويتان والجدار الناري والشهادة — `docs/deploy.md` «أولاً».
2. ⬜ `.env` و`.env.backup` من المخزن — `docs/deploy.md` «ثانياً».
3. ⬜ `bash scripts/deploy-compose.sh <الوسم>`، ثم المشرف والبذور والاستيراد — `docs/deploy.md` «ثالثاً».
4. ⬜ `$DC ps` كله healthy، و`$DC exec -T app php artisan ejaz:health` سليم.
5. ⬜ نسخة احتياطية يدوية أولى، وتجربة استعادتها — `scripts/restore.md`.
6. ⬜ مراجعة المحتوى والتحويلات على النطاق المؤقت أو بـ `curl --resolve` — `docs/cutover.md`.
7. ⬜ نقل النطاق و`SEO_INDEXABLE=true` — `docs/cutover.md`.
8. ⬜ تسجيل الخروج من الحسابات الإدارية، وإرسال خريطة الموقع لمحركات البحث — `docs/cutover.md`.
9. ⬜ مراقبة الساعة الأولى ثم الأسبوعين — `docs/monitoring.md`، والتراجع إن لزم — `docs/cutover.md`.
