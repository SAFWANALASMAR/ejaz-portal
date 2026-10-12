# إعداد حاويات التخزين المتوافقة مع S3

مستقل عن المزود (أوراكل كلاود المرشح، أو أي تخزين متوافق مع S3 داخل المملكة). الخطوات في `docs/deploy.md` «التخزين».

- `cors.json` — لحاوية الملفات الخاصة: الرفع المباشر لمقاطع المسابقات من متصفح البوابة وحدها. يُغيَّر النطاق إن اختلف `APP_URL`.
- `lifecycle-private.json` — لحاوية الملفات الخاصة: إلغاء الرفع غير المكتمل بعد يوم (`decisions.md`)، وحذف الإصدارات القديمة بعد 30 يوماً، وحذف ملفات الرفع المؤقتة للوحة (`livewire-tmp/`) بعد يوم. فرفع اللوحة حتى 200 ميجابايت يذهب من المتصفح إلى الحاوية مباشرة (`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3`)، بـ CORS نفسه (`PUT` من نطاق البوابة).
- `lifecycle-backups.json` — لحاوية النسخ: حذف نسخ القاعدة بعد 30 يوماً. وهي وحدها تحذف: مفتاح الخادم كتابة بلا حذف (`PutObject` و`ListBucket` بلا `DeleteObject`)، ولأن الكتابة تسمح بالكتابة فوق نسخة قديمة، **فالإصدارات (versioning) أو قفل الكائنات (Object Lock، وضع الامتثال 30 يوماً) شرط** على حاوية النسخ، لا اقتراح. بهما وحدهما لا يمحو من يخترق الخادم النسخ (`deploy/provider-checklist.md`).

```bash
aws s3api --endpoint-url "$EP" put-bucket-versioning --bucket <حاوية النسخ> --versioning-configuration Status=Enabled
```

التطبيق بأداة aws:

```bash
aws s3api --endpoint-url "$EP" put-bucket-cors --bucket <حاوية الملفات> --cors-configuration file://deploy/storage/cors.json
aws s3api --endpoint-url "$EP" put-bucket-versioning --bucket <حاوية الملفات> --versioning-configuration Status=Enabled
aws s3api --endpoint-url "$EP" put-bucket-lifecycle-configuration --bucket <حاوية الملفات> --lifecycle-configuration file://deploy/storage/lifecycle-private.json
aws s3api --endpoint-url "$EP" put-bucket-lifecycle-configuration --bucket <حاوية النسخ> --lifecycle-configuration file://deploy/storage/lifecycle-backups.json
```

بعض المزودين لا يدعم كل القواعد بواجهة S3 (أوراكل مثلاً تضبط دورة الحياة من لوحتها أو أداتها `oci`)، فتُطبَّق القواعد نفسها من هناك.
