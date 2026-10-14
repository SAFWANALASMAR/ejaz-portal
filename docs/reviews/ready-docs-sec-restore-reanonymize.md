# جاهز للدمج — `docs/sec-restore-reanonymize`

التاريخ: 14 أكتوبر 2026. الفرع من `origin/main`. البند ٣ من المجموعة ٢ (حماية البيانات الشخصية) في مهمة المشرف: الاستعادة من نسخة احتياطية قد تعيد بيانات من أُخفيت بياناتهم بعد وقت النسخة.

## ما فيه

1. **خطوة جديدة في `scripts/restore.md`** (الاستعادة الفعلية، الخطوة ٦، وذُكرت في قسم الحاويات):
   - المصدر القاعدة السابقة للاستعادة (أو نسخة الخطوة ٢): `users.anonymized_at`، وقيود `anonymized` في `activity_log`، و`deletion_requested_at`.
   - استعلام يولّد أمراً لكل حساب أُخفي بعد وقت النسخة، بوقت إخفائه الأصلي.
   - تشغيلها على القاعدة المستعادة بعد مزامنة الملفات، ثم `ejaz:purge-anonymized-data`.
   - استعلام يولّد إعادة طلبات الحذف المعلّقة بعد النسخة.
   - إن ضاعت القاعدة السابقة ونسختها فلا مصدر، ويُذكر ذلك في توثيق الحادث.
2. **أمر `ejaz:reapply-anonymization {user} --at= --actor=`** (`app/Console/Commands/ReapplyAnonymization.php`) يستدعي الإجراء `app/Actions/Users/ReapplyAnonymization.php`:
   - يعيد طلب الحذف بوقت الإخفاء إن لم يكن في النسخة، ثم `AnonymizeUser` نفسه بصلاحيته وسجله باسم المشرف.
   - ثم يعيد وقت الإخفاء الأصلي، فتُحسب مدة الاحتفاظ منه لا من يوم الاستعادة.
   - يتخطى الحساب غير الموجود في النسخة والمخفى أصلاً.
   - يرفض: مشرفاً بلا صلاحية، أو بريداً غير موجود أو غير فعّال، أو وقتاً مفقوداً أو غير صالح أو لاحقاً للآن. والكل في معاملة واحدة، فلا يبقى طلب حذف معلّق عند الرفض.
3. **قرار** في `docs/decisions.md` (14 أكتوبر 2026).

## الاختبارات المانعة

في `tests/Feature/Accounts/ReapplyAnonymizationTest.php`، وفشلت كلها قبل الإصلاح:

- `re-anonymizes an account that came back from a backup, keeping the original time`
- `skips an account already anonymized or missing from the backup`
- `refuses an actor without the user permissions, an unknown actor, or a bad time`
- `documents the re-anonymization step after a restore`

## نتيجة الفحص

- Pint: ناجح. PHPStan: صفر أخطاء.
- الاختبارات: 1872 نجحت (8596 تحققاً)، ولا فشل، بعمليتين متوازيتين على `ejaz_testing_g2c`.

## بعد الدمج

- لا مكتبة، ولا جدولة، ولا إعداد جديد. وترحيل واحد من تحديث 14 أكتوبر أدناه.
- الخطوة تُجرَّب في أول تجربة استعادة على الخادم التجريبي.

## تحديث — قرار صفوان (14 أكتوبر 2026): نص رسائل التواصل بعد إخفاء هوية المرسل

### ما تغيّر

- **`AnonymizeUser`** يحذف من رسائل «تواصل معنا» المرتبطة بالحساب (من حسابه، أو من بريده الموثّق) النص الحر: الموضوع والرسالة، والملاحظة الداخلية معهما (`Inquiry::FREE_TEXT`). ويبقى الرقم المرجعي والنوع والحالة والتاريخ والمسؤول للإحصاء.
- **ترحيل جديد** `2026_10_14_090000_make_inquiry_text_nullable.php`: الموضوع والرسالة يقبلان null، والمحذوف null كبقية ما يُحذف من البيانات الشخصية. ولم يُعدَّل ترحيل الجدول الأصلي.
- **`ejaz:purge-anonymized-data`** يحذف كل يوم، ولو لم تُضبط مدة الاحتفاظ، نص كل رسالة باقٍ لحساب مخفى، بسجل `personal_data_purged` لكل رسالة، ولا يعيد ما عولج. فيشمل ما أُخفي قبل القرار، وما أعادته استعادة من نسخة.
- **بعد الاستعادة:** `ejaz:reapply-anonymization` يمر بـ `AnonymizeUser` نفسه فيحذف النص، ثم خطوة الاستعادة تشغّل `ejaz:purge-anonymized-data`.
- **اللوحة:** الموضوع والرسالة المحذوفان يظهران «حُذف النص مع إخفاء هوية المرسل» (`lang/ar/inquiries.php`) في القائمة وصفحة الرسالة.
- **الوثائق:** السؤال في `open-questions.md` مجاب، ومدة الاحتفاظ برسائل الزوار ومن لم يحذف حسابه باقية سؤالاً. وقرار في `decisions.md`. و`security.md` لا يذكر السلوك القديم فلم يُعدَّل.

### الاختبارات الجديدة

- `ContactTest`: `deletes the text of the sender messages on anonymization, keeping what the statistics need`.
- `ReapplyAnonymizationTest`: `deletes the message text on anonymization and keeps it deleted after a restore from a backup` — يُخفى الحساب، ثم تُعاد صفوفه كما في النسخة (النص موجود والحساب غير مخفى)، ثم يُشغَّل الأمر فلا يبقى النص.
- `RetentionPurgeTest`: `deletes the message text left from accounts anonymized before the decision, even without a retention period` — ولا يتكرر السجل في التشغيل الثاني.
- `InquiryAdminTest`: `shows a clear placeholder for the text removed with the sender identity, in the list and the message`.

### نتيجة الفحص

- Pint: ناجح (948 ملفاً). PHPStan: صفر أخطاء.
- الاختبارات: 1876 نجحت (8657 تحققاً)، ولا فشل، بعمليتين متوازيتين على `ejaz_testing_g2c`.

### بعد الدمج

- `php artisan migrate --force` (ترحيل جديد). وأول تشغيل مجدول لـ `ejaz:purge-anonymized-data` يحذف نص الرسائل القديمة لمن أُخفي قبل القرار.
