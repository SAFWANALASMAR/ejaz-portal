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

- لا ترحيل، ولا مكتبة، ولا جدولة، ولا إعداد جديد.
- الخطوة تُجرَّب في أول تجربة استعادة على الخادم التجريبي.
