# الاستعادة من النسخة الاحتياطية وتجربتها الدورية

المرجع: `docs/security.md` («تجربة استعادة دورية موثقة»)، والنسخ في `scripts/backup.sh`.

## ما يلزم

- المفتاح الخاص لـ GPG (عند المسؤول، لا على خادم الإنتاج)، وعبارة مروره.
- مفاتيح **قراءة** على حاوية النسخ، وعنوان واجهة S3 للمزود.
- `pg_restore` بإصدار القاعدة نفسه (17)، و`gpg`، و`aws`.

## ١. تجربة الاستعادة الشهرية (لا تمس الإنتاج)

تُجرى أول كل شهر على خادم التجريبية أو جهاز معزول، وتُسجَّل نتيجتها في `docs/backups/<التاريخ>.md`.

```bash
export AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=...   # مفاتيح قراءة، من مخزن الأسرار لا مكتوبة في الأمر
EP=https://<namespace>.compat.objectstorage.<region>.oraclecloud.com
BUCKET=<حاوية النسخ>

# آخر نسخة
aws s3 --endpoint-url "$EP" ls "s3://$BUCKET/db/" | sort | tail -2
KEY=ejaz-2026-10-10T023000Z.dump.gpg

aws s3 --endpoint-url "$EP" cp "s3://$BUCKET/db/$KEY" .
aws s3 --endpoint-url "$EP" cp "s3://$BUCKET/db/$KEY.sha256" .
[ "$(sha256sum "$KEY" | cut -d' ' -f1)" = "$(cat "$KEY.sha256")" ] && echo "البصمة سليمة"

gpg --decrypt --output ejaz.dump "$KEY"

# قاعدة تجربة باسم مختلف، لا قاعدة التطبيق
createdb ejaz_restore_test
pg_restore --no-owner --no-privileges --exit-on-error --dbname=ejaz_restore_test ejaz.dump
```

### التحقق

1. أعداد الجداول الأساسية معقولة مقارنة بالإنتاج وقت النسخ:
   ```sql
   select (select count(*) from users) users,
          (select count(*) from activity_log) activity,
          (select count(*) from migrations) migrations;
   ```
2. نسخة من التطبيق بإصدار الإنتاج نفسه على قاعدة التجربة (`DB_DATABASE=ejaz_restore_test`، وبريد ورسائل وهمية: `MAIL_MAILER=log` و`SMS_DRIVER=log`، وطوابير متوقفة) تفتح الرئيسية ولوحة الإدارة، و`php artisan migrate:status` بلا ترحيل معلّق.
3. ملف خاص عشوائي من `files/private/` يُنزَّل ويُفتح.
4. **الإتلاف بعد التجربة:** `dropdb ejaz_restore_test` و`shred -u ejaz.dump "$KEY"`، لأن فيها بيانات شخصية حقيقية.

سجّل: التاريخ، والنسخة، والمدة من البدء حتى تحقق الخطوة ٢ (زمن الاستعادة الفعلي)، وأي مشكلة.

## ٢. الاستعادة الفعلية في الإنتاج (عند كارثة)

بقرار صفوان، وبعد تحديد النسخة (آخر نسخة سليمة قبل الحادث).

1. `php artisan down` على كل خوادم التطبيق، وإيقاف `ejaz-horizon` و`ejaz-scheduler.timer`.
2. نسخة من الحال الراهنة قبل الاستعادة (`pg_dump` كما في السكربت)، فلا يضيع شيء إن كان الحادث جزئياً.
3. تنزيل النسخة وفحص بصمتها وفك تشفيرها كما أعلاه.
4. الاستعادة في قاعدة جديدة (`ejaz_restored`) لا فوق الحالية، ثم تبديل `DB_DATABASE` في `.env` إليها بعد التحقق، ثم `php artisan config:cache`.
5. الملفات: `aws s3 sync s3://$BUCKET/files/private/ storage/app/private/` (أو استعادة إصدارات الحاوية إن كان القرص الخاص على S3).
6. **إعادة إخفاء من أُخفيت بياناتهم بعد وقت النسخة** (حق الحذف في نظام حماية البيانات الشخصية). النسخة تعيدهم ببياناتهم، وتعيد ما حذفه منهم `ejaz:purge-anonymized-data`.
   - المصدر القاعدة السابقة للاستعادة (الباقية باسمها القديم، أو نسخة الخطوة ٢ مستعادة في قاعدة جانبية): الحقلان `anonymized_at` و`deletion_requested_at` في `users`، وقيود الإخفاء في سجل العمليات `activity_log`.
   - وقت النسخة `T` من اسم ملفها، بتوقيت UTC (مثل `2026-10-10 02:30:00`).
   1. توليد الأوامر من القاعدة السابقة، حساباً حساباً بوقت إخفائه الأصلي:
      ```sql
      -- على القاعدة السابقة للاستعادة، لا المستعادة
      select format('php artisan ejaz:reapply-anonymization %s --at=%s --actor=<بريد المشرف>',
                    u.id, to_char(u.anonymized_at, 'YYYY-MM-DD"T"HH24:MI:SS"Z"'))
      from users u
      where u.anonymized_at is not null
        and (u.anonymized_at >= 'T'
             or u.id in (select subject_id from activity_log
                         where event = 'anonymized' and subject_type = 'App\Models\User' and created_at >= 'T'))
      order by u.id;
      ```
   2. تشغيلها على القاعدة المستعادة، بعد الخطوة ٥ (فتُحذف شهادات الحضور التي أعادتها مزامنة الملفات):
      - يُخفي بإجراء اللوحة نفسه وصلاحيته، ويسجّل العملية باسم المشرف.
      - يُبقي وقت الإخفاء الأصلي، فتُحسب مدة الاحتفاظ منه لا من يوم الاستعادة.
      - الحساب المنشأ بعد النسخة غير موجود فيها فيُتخطى، والمخفى أصلاً يُتخطى.
   3. ثم `php artisan ejaz:purge-anonymized-data`، فيُحذف من جديد ما تجاوز مدة الاحتفاظ.
   4. طلبات الحذف المعلّقة بعد وقت النسخة (طُلبت ولم تُعالج) تعود إلى قائمة اللوحة. تُولَّد من القاعدة السابقة وتُشغَّل على المستعادة:
      ```sql
      select format('update users set deletion_requested_at = %L where id = %s and deletion_requested_at is null;',
                    deletion_requested_at, id)
      from users where deletion_requested_at >= 'T' and anonymized_at is null;
      ```
   5. وإن ضاعت القاعدة السابقة ونسخة الخطوة ٢ معاً، فلا مصدر في الخادم لمن أُخفي بعد النسخة: يُذكر ذلك في توثيق الحادث (الخطوة ١٠).
7. **حذف الملفات الشخصية التي أعادتها مزامنة الملفات بلا سجل يشير إليها:** مرفقات النماذج المعبأة وتواقيعها، ومرفقات الطلبات، مما حُذف بعد مدة الاحتفاظ أو حذفه صاحبه بعد وقت النسخة. والموقع متوقف، فلا رفع جارٍ، فالعمر صفر:
   1. `php artisan ejaz:prune-orphan-private-files --dry-run --days=0`، ومراجعة العدد والمسارات.
   2. `php artisan ejaz:prune-orphan-private-files --days=0`. وإن توقف لأن الحذف بدا جماعياً (أكثر من نصف الملفات)، فراجع القاعدة المستعادة أولاً، ثم أعده بـ `--force` إن كان مقصوداً.
   - ويعمل الأمر أسبوعياً في المجدول بعمر يوم (`PRIVATE_ORPHAN_DAYS`).
8. تشغيل العمال والمجدول، ثم `php artisan up`، ثم `php artisan ejaz:health`.
9. **لا `cache:clear`.** وإن لزم مسح ذاكرة المحتوى: `php artisan ejaz:refresh-content`.
10. توثيق الحادث: ما فُقد بين آخر نسخة والحادث، وإبلاغ من يلزم وفق نظام حماية البيانات الشخصية إن مس الحادث بيانات شخصية.

## ٣. بحزمة الحاويات (`deploy/compose.production.yaml`)

القاعدة في الحاوية `pgsql`، فتُنفَّذ أوامر PostgreSQL فيها. على جهاز التجربة (أو الخادم التجريبي) بعد فك التشفير:

```bash
DC="docker compose --env-file .env -f deploy/compose.production.yaml"
$DC exec -T pgsql createdb -U ejaz ejaz_restore_test
$DC exec -T pgsql pg_restore -U ejaz --no-owner --no-privileges --exit-on-error -d ejaz_restore_test < ejaz.dump
$DC exec -T pgsql psql -U ejaz -d ejaz_restore_test -c "select count(*) from users"
$DC exec -T pgsql dropdb -U ejaz ejaz_restore_test      # الإتلاف بعد التجربة
```

وفي الاستعادة الفعلية: الاستعادة في قاعدة جديدة (`ejaz_restored`)، ثم `DB_DATABASE=ejaz_restored` في `.env`، ثم إعادة الإخفاء كما في الخطوة ٦ أعلاه (`$DC run --rm app php artisan ejaz:reapply-anonymization ...` ثم `ejaz:purge-anonymized-data`)، ثم حذف الملفات بلا سجل كما في الخطوة ٧ (`$DC run --rm app php artisan ejaz:prune-orphan-private-files --days=0`)، ثم `$DC up -d app horizon scheduler` (تُبنى ذاكرة الإعدادات عند بدء الحاويات)، ثم `ejaz:health`.
