# الرفع إلى Hostinger KVM 8: الدليل الجامع ليوم الرفع

> لصفوان، وهو من ينفّذ الرفع بنفسه. كُتب في 6 نوفمبر 2026 من ملفات المستودع وحدها، ولا أمر فيه شُغّل على خادم.
>
> - **لا سر في هذا الملف:** المفاتيح بأسمائها وحدها، وقيمها في مدير كلمات المرور وفي `.env` على الخادم.
> - **لا يُمس النطاق `ejaz.sa`** في الرفع نفسه: الخادم الجديد يُجرَّب بعنوانه أو بملف `hosts` حتى قرار النقل. والنقل في الملف المرافق [نقل النطاق ejaz.sa](hostinger-domain-cutover.md).
> - **نسخة الموقع القديم** قبل أي تغيير في الملف المرافق [نسخ الموقع القديم](old-site-backup.md).
> - كل ما لم يُعرف: 【ناقص】.

## ٠. قبل كل شيء: أي وسم يُرفع؟

- آخر وسم موجود: `v0.15.1` (`10efc551`).
- الإصدار التالي `v0.15.2` غير موسوم بعد:
  - سجله في طلب الدمج #184.
  - ومعه طلبان مفتوحان: #190 (إصلاحات المراجعة الأمنية الشاملة منذ v0.15.1) و#189 (طول بطاقة التسجيل).
- **التوصية:** الرفع بوسم `v0.15.2` بعد دمج #190 ووسمه، لا بوسم أقدم. و#190 إصلاحات أمنية، والرفع بعده بيوم أو يومين.
- 【ناقص: الوسم الذي يقرره المشرف للرفع】
- **تنبيه:** `docs/deploy.md` «Hostinger KVM» يذكر `git checkout v0.14.0` و«أول نشر بالوسم v0.14.0»، وكُتب في 26 أكتوبر. فيُستبدل بالوسم المقرر أعلاه.

## ١. خطوات الرفع بالترتيب

المرجع التفصيلي لكل خطوة في ملفه، ولا يُنسخ هنا:

- [`docs/deploy.md`](../deploy.md) قسم «Hostinger KVM»: الخطوات من الصفر حتى `ejaz:health` سليم.
- [`scripts/server-bootstrap.sh`](../../scripts/server-bootstrap.sh): تجهيز Ubuntu 24.04 مرة واحدة.
- [`scripts/deploy-compose.sh`](../../scripts/deploy-compose.sh): النشر بالوسم.
- [`scripts/verify-server.sh`](../../scripts/verify-server.sh): الفحص بعد النشر.
- [`docs/deploy/readiness.md`](readiness.md): البنود ودليل كل منها.
- [`docs/deploy/provider-checklist.md`](provider-checklist.md) قسم «Hostinger KVM»: ما يُتحقق منه قبل الشراء.
- [`docs/deploy/handoff-server.md`](handoff-server.md): «ما لا تفعله» و«ما تسلّمه».
- [`docs/cutover.md`](../cutover.md): نقل النطاق والتراجع.

### أ. الخادم (مرة واحدة)

1. **الشراء والإعداد في hPanel**، بحساب الجمعية وبريدها وتحقق ثنائي (`deploy.md` «Hostinger KVM» ١):
   - KVM 8 في ألمانيا، وUbuntu 24.04 نظيفاً بلا لوحة تحكم.
   - مفتاح SSH العام عند الإعداد.
   - الجدار الناري بثلاث قواعد قبل تفعيله: 22، و80، و443.
   - وIPv6 إن وُجد.
   - وقبل الشراء: بنود `provider-checklist.md` «Hostinger KVM» (الموقع ألمانيا، والمعالجات، واتفاقية معالجة البيانات).
2. **التجهيز الأساسي** بـ `scripts/server-bootstrap.sh` من root مرة واحدة (`deploy.md` «Hostinger KVM» ٢):
   - يُقرأ السكربت قبل تشغيله.
   - `ADMIN_USER=ejaz`، و`ADMIN_CIDR` اختياري.
   - ثم الدخول من نافذة جديدة بالمستخدم `ejaz` قبل إغلاق جلسة root.
3. **لقطة hPanel «قبل المشروع»** (الخطوة ٣).
4. **المشروع:** `git clone` بمفتاح نشر للقراءة وحدها في `/opt/ejaz`، ثم `git checkout <الوسم>`. ثم نسخ `.env.production.example` إلى `.env` و`.env.backup.example` إلى `.env.backup` بصلاحية 600 (الخطوة ٤). والقيم بأسمائها في القسم ٢ أدناه.
5. **الشهادة:** شهادة ذاتية مؤقتة، ثم Let's Encrypt بتحدي DNS قبل النقل، وخطاف التجديد (الخطوة ٥).
6. **مفتاح النسخ العام** `/etc/ejaz/backup-public.asc` ووحدات systemd، ولا يُفعَّل مؤقت النسخ قبل أول نسخة يدوية (الخطوة ٦، و`deploy.md` «أولاً» ٨ و٩).
7. **حاوية النسخ عند Hetzner** بقفل الكائنات من إنشائها، ومفتاح كتابة بلا حذف ولا قراءة ([`deploy/storage/README.md`](../../deploy/storage/README.md)).

### ب. أول نشر (قاعدة فارغة)

بـ `DC="docker compose --env-file .env -f deploy/compose.production.yaml"` كما في `deploy.md` «ثالثاً». والترتيب:

1. **`bash scripts/deploy-compose.sh <الوسم>`.** يفعل بنفسه بالترتيب:
   - فحص `.env`.
   - بناء الصورتين، وفيهما `composer install` و`npm run build`.
   - وضع الصيانة.
   - **`php artisan migrate --force`:** أول نشر يشغّل الترحيلات كلها على قاعدة فارغة، وعددها في المستودع 107 (`ls database/migrations | wc -l`)، منها 33 منذ `v0.14.2` (القائمة في القسم ١-ج).
   - **`db:seed --class=AccessSeeder --force`:** يشغّل `RolesSeeder` (الأدوار) ثم `PermissionsSeeder` (الصلاحيات من `config/permissions.php`).
   - **`db:seed --class=MainMenuSevenSeeder --force` مرة واحدة** بعلامة `.deploy-state/main-menu-seven`. وعلى قائمة فارغة لا يفعل شيئاً، والقائمة ينشئها `MenuSeeder` في الخطوة ٣.
   - تبديل الحاويات، وفيها Horizon.
   - `php artisan ejaz:refresh-content` ثم `php artisan ejaz:assistant-index`، ثم رفع الصيانة وفحص `/up` و`ejaz:health`.
2. **المسؤول التقني الأول** (تفاعلي): `$DC exec app php artisan ejaz:create-admin`. الدور الافتراضي `technical_admin`، ويقبل `--role=executive_director` أو `--role=super_admin`. ثم يُمنح المدير التنفيذي دوره من «المستخدمون» في اللوحة.
3. **البذور بالترتيب** (ترتيب `database/seeders/DatabaseSeeder.php` نفسه، لكن كل بذرة باسمها، لأن `db:seed` بلا `--class` ممنوع في الإنتاج — `deploy.md`). كلها `$DC exec -T app php artisan db:seed --class=<الاسم> --force`، وكلها آمنة للتكرار أو تعمل مرة واحدة:
   1. `MenuSeeder`: القائمة الرئيسية بسبعة عناصر إن كانت فارغة.
   2. `AboutMenuAnchorsSeeder`: القائمة المنسدلة «عن الجمعية» بمراسيها الثماني. وتحتاج «عن الجمعية» في القائمة، فبعد `MenuSeeder`.
   3. `ContentSeeder`: صفحة «عن الجمعية» والمبادرات الخمس و«سياسة الخصوصية» مسودات بلا نصوص.
   4. `LegacyRedirectsSeeder`: تحويلات روابط الموقع القديم، قبل الاستيراد.
   5. `ProgramTypesSeeder`: أنواع البرامج، ومنها أنواع المكتبة الثلاثة.
   6. `CompetitionListsSeeder`: فئات مسابقة الطلاب ومناطقها.
   7. `BrahinCompetitionSeeder`: مسابقة «البراهين العلمية» مسودة غير منشورة بنصوص المطوية (`CHANGELOG.md` 0.14.0). ليست في `DatabaseSeeder`.
   8. `JobFilesSeeder`: ملفات الوظائف مرة واحدة. في `DatabaseSeeder`، ولم يذكره `deploy.md`.
   9. `AssistantGuideSeeder`: مقالات «دليل الاستخدام» مسودات. ثم تُعدَّل مقالتا «إنشاء حساب» و«الدخول» من اللوحة إن لزم (#180).
   - و`RolesSeeder` و`PermissionsSeeder` شغّلهما `AccessSeeder` في الخطوة ١، فلا يُعادان.
   - **لا تُشغَّل في الإنتاج:** `DemoStoreItemsSeeder` و`DemoAboutGoalsSeeder` و`DemoSocialPostsSeeder` (ترفض خارج `local` و`testing`)، ولا الأوامر `ejaz:demo-*`.
4. **استيراد الموقع القديم مسوداتٍ** (`docs/legacy-import/README.md` «التشغيل»):
   - على جهازك: تنزيل الأرشيف من إصدار `legacy-site-archive` ومطابقة بصمته. ثم `python -I docs/legacy-import/copy_from_archive.py "<مسار الأرشيف>"` فيُنشئ `docs/legacy-import/files/` (خارج git).
   - نقل المجلد `docs/legacy-import` بملفاته إلى الخادم، ثم `$DC cp <المجلد> app:/tmp/legacy`.
   - `$DC exec -T app php artisan import:legacy-site /tmp/legacy/legacy-site.json --actor=<بريد المسؤول التقني> --dry-run`، ثم بدون `--dry-run`. المنفّذ مسؤول تقني لأن في الملف «عن الجمعية» (#124).
   - كل ما ينشئه مسودات غير منشورة، تُراجع وتُنشر بعد اعتماد الجمعية.
5. **التحديث بعد البذور والاستيراد:** `$DC exec -T app php artisan ejaz:refresh-content`، ثم `ejaz:refresh-stats`، ثم `ejaz:assistant-index` بعد اعتماد مقالات الدليل.
6. **Horizon:** `$DC exec -T horizon php artisan horizon:status` يرد «running». و`horizon:supervisors` فيه:
   - `supervisor-1` (`default`)
   - `supervisor-videos`
   - `supervisor-receipts`
   - `supervisor-library`
   - `supervisor-certificates` (#173)
7. **المجدول:** `$DC exec -T scheduler php artisan schedule:list` يُظهر المهام، ومنها `ejaz:refresh-stats` و`ejaz:close-expired-attempts`.
8. **أول نسخة واستعادتها**، ثم تفعيل مؤقتي النسخ والصحة (`deploy.md` «Hostinger KVM» ٩). ثم **لقطة hPanel ثانية** (١٠).

### ب-١. `ejaz:phone-only-duplicates` قبل الترحيل

- **الاسم صحيح، لكنه ليس في `main`:** الأمر في `app/Console/Commands/ReportPhoneOnlyDuplicates.php` على الفرع `fix/v0.15.2-security`، وهو طلب الدمج #190 المفتوح.
- **ما يفعله:** يطبع معرّفات الحسابات (بلا الأرقام) التي سيفرّغ ترحيل تفرد الجوال (`2026_11_05_010000_make_phone_unique_for_all_accounts`، #180) جوالها وهو معرّف دخولها الوحيد. وبعد الترحيل لا يطبع شيئاً.
- **الإشكال في الترتيب:** يلزم تشغيله على القاعدة القائمة **قبل** الترحيل. لكن `deploy-compose.sh` يشغّل الترحيل بنفسه بالصورة الجديدة، والأمر لا يوجد إلا في الصورة الجديدة.
  - فعلى خادم قائم: يُشغَّل بالصورة الجديدة قبل السكربت، مثل `$DC run --rm --no-deps app php artisan ejaz:phone-only-duplicates` بعد بنائها.
  - 【ناقص: الطريقة كما يكتبها #190 في `docs/deploy.md` بعد دمجه】
- **في الرفع الأول إلى Hostinger لا يلزم:** القاعدة فارغة فلا حسابات ولا تكرار. ويلزم في كل بيئة فيها حسابات قائمة، مثل رابط العرض.
- وما في `main` اليوم لمعالجة التكرار: **الترحيل نفسه**. يفرّغ المكرر بترتيب (الموثق، ثم من له معرّف دخول غيره، ثم الأقدم)، ويحفظ الأرقام المفرّغة في `phone_login_cleared_phones`، ويكتب تحذير «Cleared duplicate phone numbers» في السجل. ثم «تحرير الجوال» في اللوحة لمديري النظام.

### ج. خطوات نشر الإصدارات 0.15.0 و0.15.1 و0.15.2 مجمّعة

التفصيل في `CHANGELOG.md` («خطوات نشر v0.15.0» و«v0.15.1» وقسم `[0.15.2]` في #184). وفي الرفع الأول على قاعدة فارغة يغطي أغلبَها القسمُ «ب». وما يبقى منها:

- **الترحيلات منذ `v0.14.2` (33)، وكلها يشغّلها أول نشر:**
  - **0.15.0:**
    - `2026_10_29_100000_apply_brahin_director_notes`
    - `2026_10_29_100100_remove_duplicate_about_sections`
    - `2026_10_30_090000_create_evidence_galleries_table`
    - `2026_10_30_094137_add_is_downloadable_to_library_items`
    - `2026_10_30_100000_add_show_round_dates_to_competitions`
    - `2026_10_30_100100_add_brahin_rounds_intro`
    - `2026_10_30_100200_add_receipt_sent_at_to_competition_attempts`
    - `2026_10_30_110000_add_url_to_documents`
    - `2026_10_30_223000_correct_brahin_guide_phone_step`
    - `2026_10_30_233000_simplify_brahin_guide_data_step`
    - `2026_11_01_120000_create_library_progress_table`
    - `2026_11_01_120000_show_home_partners_once`
    - `2026_11_01_120100_add_page_count_to_library_items`
    - `2026_11_01_130000_add_is_view_only_to_documents`
    - `2026_11_02_090000_add_page_count_checked_at_to_library_items`
    - `2026_11_02_090100_add_reading_tracking_disabled_at_to_users`
    - `2026_11_02_140000_create_page_revisions_table`
    - `2026_11_02_140500_constrain_library_items_to_four_sections`
    - `2026_11_02_140600_rename_books_menu_item`
    - `2026_11_02_160000_add_library_programs`
    - `2026_11_02_173100_create_achievements_table`
    - `2026_11_03_090000_add_online_join_manual_to_meetings`
    - `2026_11_03_090000_add_sessions_revoked_at_to_users_table`
    - `2026_11_03_090000_add_summary_to_announcements_table`
    - `2026_11_03_090000_expire_evidence_links_without_expiry`
    - `2026_11_03_090000_index_evidence_and_achievement_foreign_keys`
    - `2026_11_03_120000_add_must_set_password_at_to_users_table`
  - **0.15.1:**
    - `2026_11_04_010000_create_social_posts_table`
  - **0.15.2:**
    - `2026_11_04_220000_create_store_items_table`
    - `2026_11_05_010000_make_phone_unique_for_all_accounts`
    - `2026_11_05_010000_rename_competitions_account_card`
    - `2026_11_05_090000_open_store_menu_item_in_same_tab`
    - `2026_11_05_090100_add_category_check_to_store_items_table`
  - والقائمة من `git diff --name-only --diff-filter=A v0.14.2 <الوسم> -- database/migrations`. وتوزيعها على الإصدارات بالفرق بين الوسوم: 27 في 0.15.0، وواحد في 0.15.1، وخمسة بعده.
- **ما يخص الترقية لا الرفع الأول، فيُتخطى على خادم جديد:**
  - استعلام أقسام المكتبة قبل الترحيل (0.15.0).
  - `schedule:clear-cache` لقفل قديم (0.15.1).
  - `ejaz:phone-only-duplicates` (القسم ب-١).
- **`.env`:**
  - `STORE_DRIVER=manual` (0.15.2، وهو الافتراضي).
  - قيم `EVIDENCE_*` و`REDIS_QUEUE_RETRY_AFTER=960` كما في `.env.production.example` (0.15.0).
  - `MESSAGING_EXAM_RECEIPT_*` اختيارية (0.15.0).
- **الإعدادات من اللوحة بعد النشر:**
  - «البوابة بالأرقام» مطفأة افتراضياً (0.15.0).
  - وثائق الحوكمة «للاطلاع فقط» افتراضياً، والكتب كذلك بخيارها (0.15.0).
  - صور شرائح البطل الأربع من «محتوى الرئيسية» (0.15.1).
  - بنود «المتجر» من اللوحة (0.15.2).
  - مزود الرسائل في «قنوات التواصل».
  - «ربط تيمز».
  - «إعدادات المساعد» (مطفأ).
- **لا يُصدر في الإنتاج:** شهادات الحضور والإتمام قبل قالب الجمعية (`PROGRAM_CERTIFICATE_TEMPLATE_READY=false`).

## ٢. ما يلزم صفوان بيده: المفاتيح والقيم بأسمائها

القيم في `.env.production.example` و`.env.backup.example`، وشرحها في `deploy.md` «ثانياً» و«Hostinger KVM» ٤. **[لازم]** للرفع، و**[قبل النقل]** قبل تحويل النطاق، و**[يمكن تأجيله]**.

**التطبيق والنطاق:**
- [لازم] `APP_KEY` (يُولَّد مرة بأمر `key:generate --show`، ويُحفظ).
- [لازم] `APP_URL` بـ https النهائي، و`SITE_HOST`، و`APP_ENV=production`، و`APP_DEBUG=false`، و`SESSION_SECURE_COOKIE=true`.
- [لازم] `TRUSTED_PROXIES` فارغة، فالزوار يصلون إلى الخادم مباشرة.
- [لازم] `SEO_INDEXABLE=false` حتى يوم النقل، ثم `true` (القسم ٣).
- `EJAZ_TAG`: يكتبه سكربت النشر.

**القاعدة وRedis وضبط الذاكرة لـ KVM 8:**
- [لازم] `DB_PASSWORD` و`REDIS_PASSWORD`.
- [لازم] `PG_MAX_CONNECTIONS` و`PG_SHARED_BUFFERS` و`PG_EFFECTIVE_CACHE_SIZE` و`PG_WORK_MEM` و`PG_MAINTENANCE_WORK_MEM` و`REDIS_MAXMEMORY` و`FPM_MAX_CHILDREN` بقيم «Hostinger KVM» ٤.

**الملفات الخاصة** (بحسب قرار القسم ٣):
- خيار (أ) القرص المحلي: [لازم] `FILESYSTEM_PRIVATE_DISK=local` و`COMPETITION_VIDEO_UPLOAD=app`. و`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` و`CSP_CONNECT_SRC` و`CSP_MEDIA_SRC` و`CSP_FRAME_SRC` و`AWS_*` فارغة.
- خيار (ب) Hetzner Object Storage: `AWS_ACCESS_KEY_ID` و`AWS_SECRET_ACCESS_KEY` و`AWS_DEFAULT_REGION` و`AWS_BUCKET` و`AWS_ENDPOINT` و`AWS_USE_PATH_STYLE_ENDPOINT`، ونطاقه في `CSP_CONNECT_SRC` و`CSP_MEDIA_SRC` و`CSP_FRAME_SRC`.
- [يمكن تأجيله] `CSP_IMG_SRC` و`CSP_FORM_ACTION`.

**البريد (مايكروسوفت 365):**
- [قبل النقل، ويُفضَّل للرفع] `MAIL_MAILER` و`MAIL_HOST` و`MAIL_PORT` و`MAIL_USERNAME` و`MAIL_PASSWORD` و`MAIL_FROM_ADDRESS` و`MAIL_FROM_NAME`.
- وشرط مدير 365: تفعيل «Authenticated SMTP» للصندوق (`cutover.md` «ما تحتاجه الجمعية»).

**النسخ الاحتياطي (`.env.backup`، عند Hetzner):**
- [لازم قبل أي بيانات حقيقية] `PGUSER` و`PGPASSWORD` (دور للقراءة).
- و`BACKUP_BUCKET` و`BACKUP_S3_ENDPOINT` و`AWS_ACCESS_KEY_ID` و`AWS_SECRET_ACCESS_KEY` و`AWS_DEFAULT_REGION`.
- و`BACKUP_GPG_RECIPIENT`، وكلمتا التشفير `BACKUP_CRYPT_PASSWORD` و`BACKUP_CRYPT_SALT`، ولا تُغيَّران بعد أول نسخة.
- و`BACKUP_SOURCE_S3_BUCKET=none` في الخيار (أ).
- وخارج الملف: مفتاح GPG الخاص ونسخة الكلمتين في مدير كلمات المرور.

**الحماية من الآليات (Cloudflare Turnstile):**
- [قبل النقل] `TURNSTILE_SITE_KEY` و`TURNSTILE_SECRET_KEY` و`BOT_CHALLENGE_DRIVER=turnstile`. وبدونهما يُرفض ضيف استبيان الرضا، ويبقى الحجب عند حدود الدخول.

**مسابقة الطلاب:**
- [قبل فتح التسجيل] `COMPETITION_NATIONAL_ID_HMAC_KEY` (يُولَّد مرة ولا يُغيَّر). وبدونه يُرفض التسجيل في المسابقة.

**الرسائل النصية وواتساب** (من «قنوات التواصل» في اللوحة، وتتقدم على `.env`):
- [قبل فتح المسابقة، ومانع إطلاق] مزود الرسائل ومفتاحه.
- [يمكن تأجيله] `WHATSAPP_*`.
- `SMS_DRIVER=log` حتى يُضبط المزود، و`SMS_PER_HOUR`.

**ما يُضبط من اللوحة أو مؤجل:**
- [يمكن تأجيله] تيمز: «ربط تيمز» في اللوحة، و`ONLINE_MEETINGS_DRIVER=graph`.
- [يمكن تأجيله] الذكاء الاصطناعي: المزود من «إعدادات المساعد». و`AI_ASSISTANT_ANTHROPIC_KEY` أو `AI_ASSISTANT_OPENAI_KEY`، والمساعد مطفأ حتى يُختار.
- [لازم] المتجر: `STORE_DRIVER=manual` و`STORE_URL` و`STORE_ALLOWED_HOSTS`.
- [يبقى] الدفع: `PAYMENTS_ENABLED=false` (مؤرشف).
- [يبقى] الشهادات: `PROGRAM_CERTIFICATE_TEMPLATE_READY=false` حتى قالب الجمعية.

## ٣. الموانع المفتوحة: أهي مانعة للرفع؟

**(أ)** يمنع الرفع، أو **(ب)** يُرفع تجريبياً خلف `SEO_INDEXABLE=false` والنطاق لم يُنقل، حتى يُحل.

1. **نص سياسة الخصوصية المعتمد** (`roadmap.md`، و`open-questions.md` «جاهزية الإطلاق»):
   - التسجيل يشترط الموافقة عليها، ولا تُنشر صفحة فيها 【ناقص】.
   - **التوصية (ب):** يُرفع ويُجرَّب، لكنه يمنع نقل النطاق وفتح التسجيل للعامة، لأن نظام حماية البيانات يوجبها قبل جمع البيانات.
2. **اعتماد الاستضافة خارج المملكة، وتقويم مخاطر النقل، واتفاقيتا معالجة البيانات** (`open-questions.md` «الاستضافة خارج المملكة» و«النشر على Hostinger»):
   - **التوصية (أ) للبيانات الحقيقية:** الرفع بلا بيانات شخصية جائز، ولا بيانات حقيقية ولا نقل نطاق قبل الاعتماد المكتوب واتفاقيتي Hostinger وHetzner. وهذا شرط قائم في القرار نفسه.
   - 【ناقص: هل اعتُمد Hostinger وتكلفته؟】
3. **قرار الملفات الخاصة: القرص المحلي (أ) أم Hetzner (ب)** (`open-questions.md` «النشر على Hostinger»):
   - **التوصية (أ) يمنع الرفع حتى يُقرر**، لأن قيمته في `.env` قبل أول نشر، ونقل الملفات بعده عمل إضافي.
   - **ومقترحي القرص المحلي**، كما في `deploy.md`: بلا تغيير في الشيفرة، والنسخ إلى Hetzner. وقيده رفع ملفات اللوحة حتى 32 ميجابايت.
4. **قرار البريد** (SMTP 365، أو مزود معاملات — `open-questions.md`):
   - **التوصية (ب):** يُرفع بـ SMTP 365 إن فُعّل للصندوق (الخيار ج في السؤال)، ويُقرر المزود قبل فتح تسجيل المسابقة. وبلا بريد لا تصل الاستعادة ولا التنبيهات، فيُجرَّب قبل النقل.
5. **مزود الرسائل النصية** (`roadmap.md`، و`open-questions.md` «التقنية والحسابات»):
   - **التوصية (ب):** يُرفع بلا مزود، والتوثيق بالجوال مطفأ، وخطر حجز رقم الغير يعالجه «تحرير الجوال».
   - ويمنع فتح تسجيل مسابقة البراهين، لأن مانعه في الوثائق صريح.
6. **مفتاحا Turnstile** (`cutover.md`، و`open-questions.md`):
   - **التوصية (ب):** يُرفع بدونهما. ويُضبطان قبل النقل، لأن بدونهما يُرفض ضيف الاستبيان ويبقى قفل الحسابات بالمحاولات الخاطئة.
7. **نصوص الجمعية وصورها، وكل 【ناقص】** (`open-questions.md` «الهوية والمحتوى» وما بعده):
   - المطلوب: اعتماد نصوص «عن الجمعية» والاستيراد، وصور شرائح البطل، ونص شريحة الحرم، ونص «التوجه الاستراتيجي»، وبنود المتجر، وملفات وثائق الحوكمة المعتمدة.
   - **التوصية (ب):** المستورد مسودات أصلاً، والإنتاج يخفي علامات 【ناقص】 عن الزوار. وتُراجع الجمعية على الخادم نفسه قبل النقل.
8. **إدارة DNS ومسجّل النطاق** (`cutover.md` «ما تحتاجه الجمعية»):
   - **التوصية (ب):** لا يلزم للرفع، ويمنع النقل وحده ([الملف المرافق](hostinger-domain-cutover.md)).
9. **اختبار التحمل وفحص ZAP وnuclei وsqlmap** (`readiness.md` §١٠ و§١٥، و`roadmap.md`):
   - **التوصية (ب):** بعد الرفع على الخادم نفسه قبل البيانات الحقيقية (القسم ٥)، وقبل فتح أي تسجيل كبير.
10. **قالب شهادة الجمعية** (`open-questions.md`):
    - **التوصية (ب):** لا يمنع شيئاً، فالشهادات لا تُصدر في الإنتاج قبله.
11. **طلبات v0.15.2 المفتوحة (#190 و#189) ووسم v0.15.2:**
    - **التوصية (أ) لـ #190 وحده:** إصلاحات أمنية، والرفع بعد يوم أو يومين، فيُنتظر دمجه ووسمه.
    - و#189 تحسين شكل لا يمنع.

## ٤. التراجع والنسخ

- **قبل كل نشر:** نسخة `sudo systemctl start ejaz-backup.service` ونجاحها في `journalctl -u ejaz-backup.service -n 50` (`deploy.md` «قائمة النشر» ٢). ولقطة hPanel قبل كل نشر كبير، وهي لقطة واحدة متاحة.
- **فشل النشر نفسه** (`deploy.md` «إن فشل النشر»):
  - قبل الصيانة: الإصدار القديم يعمل.
  - بعدها: `$DC exec -T app php artisan up` بعد التحقق، أو التراجع.
- **التراجع إلى الوسم السابق** (`cutover.md` «ب» بحزمة الحاويات):
  - `EJAZ_TAG` في `.env` إلى الوسم السابق، ثم `$DC up -d app horizon scheduler web`.
  - **لا `migrate:rollback`** في 0.15 (`deploy.md` «التراجع عن v0.15.0»). ومع حالتي وثيقة الرابط بلا ملف و«للاطلاع فقط».
  - وفي الرفع الأول لا وسم سابق: الرجوع بلقطة hPanel «قبل المشروع»، أو بحذف الحزمة وإعادة النشر.
- **الاستعادة من النسخة** عند تلف البيانات، بقرار صفوان ([`scripts/restore.md`](../../scripts/restore.md) القسم ٣ للحاويات). ونسخ Hostinger الأسبوعية احتياط ثالث، والاستعادة منها تمحو الخادم كله.
- **تراجع النطاق إلى الموقع القديم على hosting.com** ([`cutover.md`](../cutover.md) «التراجع»، و[الملف المرافق](hostinger-domain-cutover.md)):
  - سجلات `A` و`www` إلى قيمها المصوّرة قبل النقل.
  - `SEO_INDEXABLE=false` على البوابة.
  - سجلات البريد لا تُمس أصلاً.
- **ممنوع:** `cache:clear` و`optimize:clear` و`migrate:fresh` و`db:seed` بلا `--class` و`docker compose down -v` (`deploy.md`).

## ٥. الفحوص بعد الرفع

1. **السلامة:**
   - `$DC ps` كله healthy.
   - `$DC exec -T app php artisan ejaz:health` بلا ✗.
   - `$DC exec -T app php artisan migrate:status` بلا معلّق.
2. **الفحص الجامع:** `bash scripts/verify-server.sh https://ejaz.sa --server` على الخادم بسطر `127.0.0.1 ejaz.sa www.ejaz.sa` في `/etc/hosts`، ثم يُحذف السطر (`deploy.md` «Hostinger KVM» ٨). وفحصا الفهرسة يفشلان ما دام `SEO_INDEXABLE=false`، وهذا متوقع.
3. **robots وsitemap:**
   - قبل النقل: `robots.txt` فيه `Disallow: /`.
   - بعده: سطر `Sitemap`، و`sitemap.xml` يرد XML بروابط المنشور (`deploy.md` «الفهرسة»).
4. **TLS والرؤوس** (`readiness.md` §١٥): `testssl.sh` بدرجة A، وsecurityheaders.com، وTRACE يرد 405 أو 403.
5. **ZAP** (`docs/security/2026-10-13-zap-local-scan.md` لطريقة التشغيل):
   - الأساسي السلبي (`zap-baseline.py`) على الخادم قبل النقل.
   - والكامل الفعال (`zap-full-scan.py`)، ومعه nuclei وsqlmap كما في `readiness.md` §١٥، **قبل أي بيانات حقيقية وحدها**، لأنه يرسل هجمات وينشئ بيانات.
   - والنتيجة في `docs/security/`.
6. **التحمل** ([`tests/load/README.md`](../../tests/load/README.md)، و`staging-plan.md` «اليوم ٥»):
   - k6 على الخادم نفسه قبل البيانات الحقيقية.
   - السكربتات ترفض `ejaz.sa` و`www.ejaz.sa`، فيُستعمل عنوان الخادم أو نطاق مؤقت. 【ناقص: النطاق المؤقت للاختبار】
   - `ejaz:load-accounts` ثم `smoke` ثم `average` ثم `peak` ثم `spike`.
   - ثم حذف حسابات `load.test`.
   - و`PEAK` 【ناقص: مدة نافذة الذروة من الجمعية】.
   - والتقرير في `docs/load-tests/<التاريخ>.md`.
7. **اللقطات على 1440 و820 و390:** الرئيسية، و«عن الجمعية»، والحوكمة، و«مكتبة إعجاز»، و«الدورات والبرامج»، و«الخدمات الإلكترونية»، و«المتجر والتبرعات»، و«تواصل معنا»، والدخول والتسجيل، و«حسابي»، ولوحة الإدارة. بلا تمرير أفقي، ومن اليمين.
8. **البريد:** رسالة استعادة كلمة مرور لحساب تجريبي تصل بلا تصنيف مزعج (`cutover.md` ٣.٤).
9. **الرسائل النصية:** «رسالة اختبار» من «قنوات التواصل» بعد ضبط المزود. وإلا تُسجَّل باقية.
10. **النسخ:** أول نسخة، ثم تجربة الاستعادة ([`scripts/restore.md`](../../scripts/restore.md) القسم ١)، ثم تفعيل المؤقت. وتُسجَّل في `docs/backups/<التاريخ>.md`.
