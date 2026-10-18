# إعداد حاويات التخزين المتوافقة مع S3

مستقل عن المزود: المرشح Contabo Object Storage لحاوية الملفات، وHetzner Object Storage لحاوية النسخ عند شركة ثانية (`docs/decisions.md`، 18 أكتوبر 2026)، أو أي تخزين متوافق مع S3. الخطوات في `docs/deploy.md` («أولاً» الخطوة 10)، وما يُتحقق منه عند كل مزود في `docs/deploy/provider-checklist.md`.

- `cors.json` — لحاوية الملفات الخاصة: الرفع المباشر لمقاطع المسابقات من متصفح البوابة وحدها. يُغيَّر النطاق إن اختلف `APP_URL`.
- `lifecycle-private.json` — لحاوية الملفات الخاصة: إلغاء الرفع غير المكتمل بعد يوم (`decisions.md`)، وحذف الإصدارات القديمة بعد 30 يوماً، وحذف ملفات الرفع المؤقتة للوحة (`livewire-tmp/`) بعد يوم. فرفع اللوحة حتى 200 ميجابايت يذهب من المتصفح إلى الحاوية مباشرة (`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3`)، بـ CORS نفسه (`PUT` من نطاق البوابة).
- `lifecycle-backups.json` — لحاوية النسخ: حذف نسخ القاعدة (`db/`) ونسخ الملفات (`files/`) بعد 30 يوماً، والإصدارات القديمة بعد 30 يوماً من استبدالها. و`scripts/backup.sh` يعيد رفع الملفات الحية كلها مرة في الأسبوع، فلا تنتهي نسخة ملف باقٍ، والمحذوف من الخادم أو حاوية الملفات يُحذف من النسخ بعد المدة (`decisions.md` — 14 و19 أكتوبر 2026). وهي وحدها تحذف: مفتاح الخادم كتابة بلا حذف (أدناه)، ولأن الكتابة تسمح بالكتابة فوق نسخة قديمة، **فقفل الكائنات (Object Lock، وضع الامتثال `COMPLIANCE` 30 يوماً، ومعه الإصدارات) شرط** على حاوية النسخ، لا اقتراح ولا الإصدارات وحدها: مفتاح Hetzner على كل حاويات مشروعه، وتقييده بالعملية (منع `DeleteObjectVersion` و`PutBucketLifecycleConfiguration` و`PutBucketPolicy`) لم يُجرَّب بعد، فالقفل وحده يمنع مخترق الخادم من محو النسخ في مدته (`deploy/provider-checklist.md`).
  - و`files/` كلها مشفّرة عند العميل (طبقة `crypt` في rclone): القرصان المحليان، وحاوية الملفات الخاصة باسم مشفّر تحت `files/` أيضاً (`vault:s3-private`)، فتسري عليها القاعدة نفسها. ولا `--sse`: Hetzner لا يدعم إلا SSE-C.

## المفاتيح (decisions.md — 19 أكتوبر 2026)

- **مفتاح الخادم على حاوية النسخ** (`AWS_*` في `.env.backup`): `PutObject` و`ListBucket` و`GetBucketLocation` و`ListBucketMultipartUploads` و`ListMultipartUploadParts` و`AbortMultipartUpload` وحدها. **بلا `DeleteObject` ولا `DeleteObjectVersion` ولا `GetObject`**: لا يحذف ولا يقرأ النسخ (rclone بلا HEAD: `no_head` و`no_head_object` في `scripts/backup.sh`). ولذلك `rclone copy` لا `sync`. ومُجرَّب على MinIO بهذه الصلاحيات وحدها (`tests/backup/`).
  - وعند Hetzner: إن لم تُقيَّد الصلاحية بالعملية في مفتاح المشروع، فبسياسة الحاوية (bucket policy) تمنعه الحذف والقراءة، ويُجرَّب الرفض قبل الإنتاج (`provider-checklist.md`). وقفل الامتثال يمنع المحو في مدته على كل حال.
- **مفتاح قراءة على حاوية الملفات** (`BACKUP_SOURCE_*` في `.env.backup`): `GetObject` و`ListBucket` وحدها، بمستخدم Contabo مستقل عن مفتاح التطبيق.
  - **القيد:** Contabo لا يوثّق تقييد مفتاح بعملية (`provider-checklist.md`). فإن تعذر مفتاح قراءة وحدها، فالمفتاح بصلاحية كاملة على حاوية الملفات، و`scripts/backup.sh` لا يستعمله إلا مصدراً لـ `rclone copy` (لا كتابة ولا حذف في الشيفرة)، ويبقى في `.env.backup` (600) كمفتاح التطبيق في `.env`. ويُسجَّل ما ثبت منه في `provider-checklist.md`.
- **مفتاح إدارة حاوية النسخ** (القراءة والإصدارات والاستعادة): عند المسؤول خارج الخادم، لا في `.env.backup` (`scripts/restore.md`).

والحاويتان عند مزودين اثنين، فلكل منهما عنوانه ومنطقته ومفاتيحه. والأوامر بأداة aws:

## حاوية الملفات (عند مزود الخادم — المرشح Contabo)

```bash
# مفاتيح مستخدم Contabo لهذه الحاوية، لا مفاتيح Hetzner
EP_FILES=https://eu2.contabostorage.com
aws s3api --endpoint-url "$EP_FILES" --region default put-bucket-cors --bucket <حاوية الملفات> --cors-configuration file://deploy/storage/cors.json
aws s3api --endpoint-url "$EP_FILES" --region default put-bucket-versioning --bucket <حاوية الملفات> --versioning-configuration Status=Enabled
aws s3api --endpoint-url "$EP_FILES" --region default put-bucket-lifecycle-configuration --bucket <حاوية الملفات> --lifecycle-configuration file://deploy/storage/lifecycle-private.json
```

## حاوية النسخ (عند المزود الثاني — المرشح Hetzner)

عند Hetzner قفل الكائنات **يُفعَّل عند إنشاء الحاوية وحده** ولا يُضاف بعده، والإصدارات معه دائمة. فتُنشأ به، ثم تُضبط مدته الافتراضية ودورة حياتها، ولا تُستعمل قبل 15 دقيقة:

```bash
# مفتاح إدارة مشروع حاوية النسخ عند Hetzner، لا مفتاح الخادم ولا مفاتيح Contabo
EP_BACKUP=https://<الموقع، مثل fsn1>.your-objectstorage.com
aws s3api --endpoint-url "$EP_BACKUP" --region <الموقع> create-bucket --bucket <حاوية النسخ> --object-lock-enabled-for-bucket
aws s3api --endpoint-url "$EP_BACKUP" --region <الموقع> put-object-lock-configuration --bucket <حاوية النسخ> \
  --object-lock-configuration '{"ObjectLockEnabled":"Enabled","Rule":{"DefaultRetention":{"Mode":"COMPLIANCE","Days":30}}}'
aws s3api --endpoint-url "$EP_BACKUP" --region <الموقع> put-bucket-lifecycle-configuration --bucket <حاوية النسخ> --lifecycle-configuration file://deploy/storage/lifecycle-backups.json
```

- وإن فشل أمر الإنشاء فيُعطَّل `addressing_style = virtual` في `~/.aws/config`، كما في دليل Hetzner.
- وفي الخادم التجريبي وضع الحوكمة (`GOVERNANCE`) أو مدة يوم، وإلا تعذر حذف الحاوية في الإتلاف (`docs/deploy/staging-plan.md`).
- وعند مزود بلا قفل الكائنات (لا Hetzner)، فالإصدارات شرط أدنى، ومعها تجربة رفض محو الإصدارات وتعديل دورة الحياة بمفتاح الخادم قبل أي بيانات حقيقية:

```bash
aws s3api --endpoint-url "$EP_BACKUP" put-bucket-versioning --bucket <حاوية النسخ> --versioning-configuration Status=Enabled
```

## قواعد لا يدعمها كل مزود

بعض المزودين لا يدعم كل القواعد بواجهة S3، فتُطبَّق القواعد نفسها من لوحته أو بصيغته. ومن ذلك (`docs/deploy/provider-checklist.md`):
- Contabo يوثّق قواعد الانتهاء وحدها، فقاعدتا إلغاء الرفع غير المكتمل وحذف الإصدارات القديمة تُجرَّبان. والرفع المتروك لمقاطع المسابقات يلغيه `ejaz:prune-video-uploads` يومياً.
- أمثلة Hetzner كلها بعنصر `Prefix` فارغ في القاعدة نفسها، ولا مثال ببادئة غير فارغة ولا بـ `Filter`، فيُجرَّب قبول الملف وأثره. والقواعد عنده تسري على الموجود والجديد معاً، فتُجرَّب على حاوية تجربة أولاً.
