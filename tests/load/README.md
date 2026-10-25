# اختبار التحمل والتزامن

المرجع: `docs/performance.md` و`docs/plans/competitions.md` (البند ٧) و`docs/plans/launch-readiness.md`. الأداة k6، ولا تُضاف إلى المشروع مكتبةً: تُثبَّت على جهاز التشغيل وحده.

## أين يُجرى

على **البيئة التجريبية** بمواصفات الإنتاج، لا على جهاز التطوير. وقبل فتح أي تسجيل كبير.

**لا تشغيل على الإنتاج أبداً:** كل سكربت يتوقف إن كان `BASE_URL` هو `ejaz.sa` أو `www.ejaz.sa` (`lib.js`).

## السكربتات

- `browse.js` — زائر بلا حساب: الرئيسية، ثم ثلاثة أقسام عشوائية، ثم صفحة عنصر. أغلب الحركة وقت الذروة.
- `login.js` — صفحة الدخول، والدخول، ولوحة الحساب، والخروج. يقيس bcrypt والجلسات في Redis.
- `program-registration.js` — التسجيل في برنامج (`PROGRAM`): قفل المقاعد وقائمة الانتظار تحت التزامن.
- `competition-exam.js` — رحلة المتقدم في المسابقة على امتداد وقت الإجابة (`EXAM_MINUTES`): حفظ تلقائي كل 30–60 ثانية من آلاف الجلسات المفتوحة معاً، ثم الإرسال. على مسارات المشاركة القائمة، وتُضاف مسارات الاختبار المؤقت إن دُمج فرعه.
- `competition-entry.js` — دفعة التقديم: كل حساب يقدّم مرة بأسرع ما يمكن، مع رفع مقطع مجزأ اختيارياً (`VIDEO`).
- `lib.js` — المشترك: حارس الإنتاج، وملفات التصاعد، والدخول، ورمز CSRF.

## ملفات التصاعد (`PROFILE`) والتزامن في الذروة (`PEAK`)

السكربتات الأربعة الأولى بمنفّذ `ramping-vus`، و`PEAK` عدد المستخدمين الافتراضيين المتزامنين في الذروة:

- `smoke` — مستخدمان لدقيقة: تجربة السكربت نفسه قبل أي تشغيل كبير.
- `average` — ربع الذروة 15 دقيقة: الحمل العادي، ومعياره p95 أقل من 300 ملي ثانية للصفحات العامة (`performance.md`).
- `peak` — تصاعد 10 دقائق إلى `PEAK` ثم ثبات 20 دقيقة: الذروة المتوقعة.
- `spike` — قفزة في دقيقة إلى `PEAK`: لحظة فتح التسجيل أو الإعلان.
- `stress` — إلى مثلي `PEAK`: لمعرفة نقطة الانكسار، ولا يدخل في معايير القبول.

### من 30,000 مستخدم إلى `PEAK`

التزامن لا يساوي عدد المستخدمين، بل:

```
PEAK ≈ عدد من يصلون في نافذة الذروة × مدة الجلسة بالدقائق ÷ مدة النافذة بالدقائق
```

- العدد: **30,000** هامشاً فوق المتوقع مبدئياً للمسابقة (**17,500** — صفوان، 6 أكتوبر 2026).
- مدة النافذة وتوزيع الدخول 【ناقص: من الجمعية حسب تجارب التسجيل السابقة】، فلا قيمة معتمدة لـ `PEAK` بعد.
- **مثال حسابي للتوضيح فقط، لا تقدير:** لو وصل الثلاثون ألفاً كلهم في ساعتين، والجلسة 20 دقيقة، فالتزامن نحو 30,000 × 20 ÷ 120 = 5,000. ولو في يوم كامل فنحو 420.
- يُشغَّل `peak` بالقيمة المحسوبة، ثم `spike` بها، ثم `stress` لمعرفة الهامش. و`competition-entry.js` بـ `ACCOUNTS=30000` يتحقق أن كل الحسابات قدّمت فعلاً.
- مولّد k6 واحد يحمل بضعة آلاف من المستخدمين الافتراضيين بحسب جهازه، وما فوقها بعدة مولّدات (يُقسم `PEAK` و`ACCOUNTS` بينها، ولكل مولّد مدى حسابات منفصل).

### العتبات في السكربتات

- `http_req_failed` أقل من 1%، و`checks` أعلى من 99%، ولا رد 5xx.
- الصفحات والدخول والتسجيل: p95 أقل من 1.5 ثانية تحت الذروة.
- الحفظ التلقائي في المسابقة: p95 أقل من ثانية، والإرسال أقل من ثانيتين.
- يفشل k6 بعتبة مكسورة (رمز خروج غير صفري)، ويُذكر ذلك في التقرير.

### أمثلة

```bash
export LOAD_PASSWORD=...   # من مخرجات ejaz:load-accounts
k6 run -e BASE_URL=https://staging.example -e PROFILE=smoke tests/load/browse.js
k6 run -e BASE_URL=https://staging.example -e PROFILE=peak -e PEAK=2000 tests/load/browse.js
k6 run -e BASE_URL=https://staging.example -e PASSWORD="$LOAD_PASSWORD" -e PROFILE=spike -e PEAK=1000 tests/load/login.js
k6 run -e BASE_URL=https://staging.example -e PASSWORD="$LOAD_PASSWORD" -e PROGRAM=<slug> -e PROFILE=spike -e PEAK=1000 tests/load/program-registration.js
k6 run -e BASE_URL=https://staging.example -e PASSWORD="$LOAD_PASSWORD" -e COMPETITION=<slug> -e EXAM_MINUTES=20 -e PROFILE=peak -e PEAK=3000 tests/load/competition-exam.js
```

### فحوص ما بعد التسجيل في البرنامج

لا مؤكد فوق المقاعد، ولا تسجيل مكرر لحساب واحد (الناتجان صفر):

```sql
select count(*) from programs p
where p.seats is not null
  and (select count(*) from program_registrations r where r.program_id = p.id and r.status = 'confirmed') > p.seats;

select count(*) from (
  select program_id, user_id from program_registrations
  where status <> 'cancelled' group by program_id, user_id having count(*) > 1
) duplicates;
```

وأثناء كل تشغيل تُراقب لوحة Horizon، و`php artisan ejaz:health`، والمعالج والذاكرة واتصالات القاعدة وذاكرة Redis (`docs/monitoring.md`).

---

## المسابقات: التقديم (`competition-entry.js`)

## التجهيز على البيئة التجريبية

1. مسابقة منشورة مفتوحة، بأسئلتها، وبسؤال فيديو إن أُريد اختبار الرفع.
2. حسابات الاختبار (لا تُنشأ في الإنتاج):
   ```bash
   php artisan ejaz:load-accounts 30000
   ```
   يطبع كلمة مرور عشوائية لهذا التشغيل. والتشغيل الثاني يبقي الحسابات السابقة ومشاركاتها، ويعطيها كلمة مرور التشغيل الجديد.
   ولا يعمل إلا ومزود الرسائل `log`، لأن أرقام الحسابات بصيغة سعودية، فلا يصل إشعار إلى أرقام أشخاص.
   وكلمة المرور تُمرَّر إلى k6 من متغير بيئة مُصدَّر (`export LOAD_PASSWORD=...` ثم `-e PASSWORD="$LOAD_PASSWORD"`)، لا مكتوبة في الأمر نفسه.
3. **حدود الطلبات لكل عنوان:** المولّد من عنوان واحد يصطدم بحد الدخول والحفظ. فإما مولّدات من عناوين متعددة، أو رفع الحدود مؤقتاً في التجريبية وحدها، ويُذكر ذلك في التقرير. والسقف العام للدخول في البوابة (`LOGIN_FAILURES_PER_MINUTE`، افتراضياً 1,200 خاطئة في الدقيقة) لا يحد `login.js` لأن دخوله ناجح، ويحده حد المشترك (`LOGIN_ATTEMPTS_PER_SUBSCRIBER_PER_MINUTE`، افتراضياً 300 في الدقيقة لكل عنوان): يُرفع للاختبار، ويُضبط للإنتاج على ما يتحمله المعالج في النتيجة.
4. **الطابور:** عمال Horizon شغالون، فالمعالجة والتصحيح والبريد في الخلفية.
5. مقطع MP4 قصير صالح (`sample.mp4`) بحجم يقارب حجم المتوقع من المتقدمين.
6. `BASE_URL` هو `APP_URL` نفسه بالضبط (النطاق والمنفذ)، لأن روابط الرفع مطلقة منه، والكعكات لا تُرسل لنطاق آخر.

جُرّب السكربت على البيئة المحلية بثلاثة حسابات (تجربة للسكربت لا اختبار تحمل): كل الفحوص نجحت، ولا خطأ خادم.

## التشغيل

البيئة التجريبية تعمل بـ `APP_ENV=staging`، وإلا رفض أمر الحسابات العمل.

```bash
k6 run \
  -e BASE_URL=https://staging.example \
  -e COMPETITION=competition-slug \
  -e PASSWORD='كلمة مرور التشغيل' \
  -e ACCOUNTS=30000 \
  -e VUS=500 \
  -e VIDEO=./sample.mp4 \
  tests/load/competition-entry.js
```

- `ACCOUNTS`: 30,000، هامشاً فوق المتوقع مبدئياً (17,500).
- `VUS` و`DURATION`: من حجم نافذة الذروة وتوزيع الدخول، وهما 【ناقص: من الجمعية حسب تجارب التسجيل السابقة】.

## معايير القبول

- `http_req_failed` أقل من 1%، ولا رد 5xx.
- `checks` أعلى من 99%.
- `entries_confirmed` (مشاركات أُرسلت برقم مرجعي) يساوي `ACCOUNTS`، والعمال شغالون حتى تجهز المقاطع قبل الإرسال.
- زمن الصفحات: p95 أقل من 1.5 ثانية تحت الحمل.
- **لا تقديم مكرر:** بعد الاختبار، يجب أن يكون الناتج صفراً:
  ```sql
  select count(*) from (
    select competition_id, user_id from competition_submissions
    group by competition_id, user_id having count(*) > 1
  ) duplicates;
  ```
  والقيد الفريد في قاعدة البيانات يضمنه أصلاً، والاختبار يتحقق من أنه لم يُنتج أخطاء 5xx.
- **المقاطع:** لا مقطع باقٍ «قيد المعالجة» بعد انتهاء الطابور.

## التزامن في الاختبارات الآلية

تعمل في `composer check` على كل تغيير (`tests/Feature/Competitions/SubmissionTest.php`):
- القيد الفريد يرفض المشاركة الثانية للمتقدم نفسه.
- طلب متزامن أنشأ المسودة أولاً: يعيد الإجراء مسودته بلا خطأ ولا تكرار.
- حفظ تلقائي يصل بعد إرسال متزامن: يُرفض ولا يعدّل المرسلة.
- طلبا إنهاء رفع متزامنان: معالجة واحدة (انتقال شرطي).

## التقرير

يُحفظ في `docs/load-tests/<التاريخ>.md`: الإعداد، والأرقام، وما فشل، والإجراء.

---

## مسابقة الطلاب: بدء جماعي (`student-exam.js`)

طلاب يبدؤون الاختبار في لحظة واحدة، كبداية نافذة مرحلة في معامل المدارس (`plans/student-competition.md`). كل طالب يدخل، وينتظر لحظة البدء المشتركة (`START_AT`، ثوانٍ منذ 1970)، ثم «ابدأ الاختبار»، ثم يجيب كل الصفحات على مهل (`ANSWER_SECONDS`)، ثم يسلّم.

1. مسابقة طلاب منشورة بمرحلة جارية وبنك أسئلة لفئتها المفتوحة الأولى.
2. `php artisan ejaz:load-accounts 300` ثم `php artisan ejaz:load-students <slug> 300` (المحلية والتجريبية وحدهما، وأرقام هوية بصيغة صحيحة في مدى يبدأ بـ 19).
3. حد الدخول لكل عنوان (`LOGIN_ATTEMPTS_PER_SUBSCRIBER_PER_MINUTE`) يُرفع للتشغيل من مولّد واحد، أو تُوزَّع الدخولات على دقائق قبل `START_AT`. وحدود الاختبار نفسها لكل حساب لا لكل عنوان، فلا تُرفع.

```bash
k6 run -e BASE_URL=http://localhost:8140 -e PASSWORD="$LOAD_PASSWORD" -e COMPETITION=<slug> -e ROUND=<uuid> \
       -e STUDENTS=300 -e START_AT=$(( $(date +%s) + 120 )) tests/load/student-exam.js
```

بعده: محاولة واحدة لكل طالب، وكلها مسلَّمة ومصححة (الناتجان 300 ثم صفر):

```sql
select count(*) from competition_attempts where status = 'submitted' and score is not null;
select count(*) from (select round_id, submission_id from competition_attempts group by 1, 2 having count(*) > 1) d;
```
