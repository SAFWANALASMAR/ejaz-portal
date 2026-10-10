# إعداد حاويات التخزين المتوافقة مع S3

مستقل عن المزود (أوراكل كلاود المرشح، أو أي تخزين متوافق مع S3 داخل المملكة). الخطوات في `docs/deploy.md` «التخزين».

- `cors.json` — لحاوية الملفات الخاصة: الرفع المباشر لمقاطع المسابقات من متصفح البوابة وحدها. يُغيَّر النطاق إن اختلف `APP_URL`.
- `lifecycle-private.json` — لحاوية الملفات الخاصة: إلغاء الرفع غير المكتمل بعد يوم (`decisions.md`)، وحذف الإصدارات القديمة بعد 30 يوماً.
- `lifecycle-backups.json` — لحاوية النسخ: حذف نسخ القاعدة بعد 35 يوماً، حارساً خلف حذف `scripts/backup.sh` (30 يوماً).

التطبيق بأداة aws:

```bash
aws s3api --endpoint-url "$EP" put-bucket-cors --bucket <حاوية الملفات> --cors-configuration file://deploy/storage/cors.json
aws s3api --endpoint-url "$EP" put-bucket-versioning --bucket <حاوية الملفات> --versioning-configuration Status=Enabled
aws s3api --endpoint-url "$EP" put-bucket-lifecycle-configuration --bucket <حاوية الملفات> --lifecycle-configuration file://deploy/storage/lifecycle-private.json
aws s3api --endpoint-url "$EP" put-bucket-lifecycle-configuration --bucket <حاوية النسخ> --lifecycle-configuration file://deploy/storage/lifecycle-backups.json
```

بعض المزودين لا يدعم كل القواعد بواجهة S3 (أوراكل مثلاً تضبط دورة الحياة من لوحتها أو أداتها `oci`)، فتُطبَّق القواعد نفسها من هناك.
