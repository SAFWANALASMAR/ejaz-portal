# جاهزية نشر v0.15.0 (`fix/v0.15-deploy-readiness`)

المصدر: نتائج مراجعة النشر وقاعدة البيانات قبل وسم v0.15.0 (3 نوفمبر 2026). ولا مكتبة جديدة.

## القرار

1. **`MainMenuSevenSeeder` في `scripts/deploy.sh`** (الخادم بلا حاويات) كما في `scripts/deploy-compose.sh`: بعد `AccessSeeder`، مرة واحدة بعلامة `.deploy-state/main-menu-seven`، وداخل `RUN_MIGRATIONS=true` وحده، فلا تشغّله الخوادم الأخرى. كان غائباً، فيبقى الشريط القديم على الخادم بلا حاويات.
2. **فهارس المفاتيح الأجنبية** في ترحيل جديد `2026_11_03_090000_index_evidence_and_achievement_foreign_keys`: `evidence_galleries` (`program_id` و`token_issued_by` و`created_by`)، و`evidence_gallery_items` (`album_id` و`archive_item_id`)، و`achievements` (`created_by` و`updated_by`). فُحصت بـ `pg_indexes` قبله: لا فهرس على أي منها، لأن PostgreSQL لا يفهرس المفتاح الأجنبي تلقائياً، فكان حذف مستخدم أو برنامج أو ألبوم أو عنصر أرشيف يمسح الجدول كله ليطبّق `nullOnDelete` أو `cascadeOnDelete`. بأسماء صريحة، والرجوع يحذفها بأسمائها. ولا يُعدَّل ترحيل الإنشاء لأنه دُفع إلى `main`.
3. **`REDIS_QUEUE_RETRY_AFTER=960` في `.env.example`** كما في `.env.production.example`: `config/queue.php` يقرؤه من قبل (افتراضه 90)، وأطول مهلة مهمة 900 ثانية (`ProcessSubmissionVideo` و`supervisor-videos`)، والباقي 120 فأقل.
4. **`$backoff = [30, 120]` في `GenerateHeroVariants`:** المحاولة الثانية بعد 30 ثانية لا فوراً. ومع `$tries = 2` لا يُستعمل إلا الأول، و120 لمحاولة ثالثة إن رُفع `tries` (القيمة كما طُلبت). و`SendExamReceipt` في فرع `fix/v0.15-security-lows`.
5. **التراجع عن v0.15.0 بالوسم وحده، بلا `migrate:rollback`** (`docs/deploy.md` «الترقية إلى v0.15.0»): ترحيلاته إضافة في البنية يتركها الإصدار السابق، والرجوع يحذف ما كُتب بعد النشر. وتلف البيانات نفسها بالاستعادة من النسخة بقرار صفوان.
