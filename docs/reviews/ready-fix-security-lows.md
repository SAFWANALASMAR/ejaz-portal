# جاهز للدمج — `fix/security-lows`

التاريخ: 13 أكتوبر 2026. الفرع من `origin/preview/local` عند `91634c0`. يصلح المنخفضة الثلاث الباقية بخطة في `docs/security/2026-10-12-security-audit.md`، وحُدّث التقرير نفسه (القسم «ما أُصلح بعد التقرير»).

## ما فيه

1. **F8 — سر التحقق الثنائي المؤكد لا يُستبدل بطلب من الجلسة:** `app/Actions/Fortify/EnableTwoFactorAuthentication.php`، مربوط كإجراء الإيقاف القائم.
   - `force` على تحقق مؤكد يُرفض (403)، ويبقى السر كما هو.
   - تُسجَّل المحاولة في السجل الأمني (`two_factor_rotation_refused`)، ويصل صاحب الحساب بريد بها (`TwoFactorRotationRefused`).
   - الإعداد الأول كما هو.
2. **تنبيه رموز التحقق الثنائي الخاطئة الكثيرة على حساب في اليوم:** `app/Actions/Accounts/WarnRepeatedTwoFactorFailures.php`، من مستمع أحداث الدخول.
   - عند `TWO_FACTOR_ALERT_THRESHOLD`:
     - بريد للمشرفين العامين الفعّالين، برقم الحساب وحده، مرة لكل حساب في اليوم بتوقيت الرياض.
     - سطر `WARNING`.
     - قيد `two_factor_failures_alerted`.
   - الافتراضي 5، و0 يوقفه، وأقل من 30 سقف المحاولات.
   - ووُثّق في `docs/monitoring.md`.
3. **حد معدل لرفع ملفات اللوحة المؤقت:** `app/Support/UploadLimits.php` لكل حساب، `UPLOADS_PER_MINUTE`=30 و`UPLOADS_PER_HOUR`=300، و0 بلا حد. يُحتسب في موضعين بمفتاحين:
   - **بدء الرفع (`_startUpload`):** بخطاف Livewire في `AppServiceProvider`. منه يمر كل رفع، حتى المباشر إلى التخزين في الإنتاج.
   - **مسار الرفع المحلي:** `['auth', 'throttle:uploads']`، فالرابط الموقّع لا يُستعمل بلا دخول.
   - ويتسع لحقل الأرشيف (20 ملفاً).
4. **إعدادات جديدة في `.env.example`:** `TWO_FACTOR_ALERT_THRESHOLD`، و`UPLOADS_PER_MINUTE`، و`UPLOADS_PER_HOUR`.

## الاختبارات المانعة

في `tests/Feature/Security/AuditFollowUpTest.php`. كل منها يفشل بدون إصلاحه، وتحقق من ذلك `code-reviewer` بقراءة الشيفرة:

- `refuses to rotate a confirmed two-factor secret, logs the attempt and tells the owner`
- `alerts the super admin once a day when wrong two-factor codes pile up on one account`
- `rate-limits temporary panel uploads per account`
- `rate-limits starting panel uploads, which every upload passes through, even straight to the storage`
- `refuses a temporary upload without a signed-in account, even with a signed link`
- `turns the alert off and the upload limit off at zero`

## نتيجة الفحص

- Pint: ناجح. PHPStan: صفر أخطاء.
- **الاختبارات:** 1865 اختباراً، نجح 1864، وتُخطي 1، ولا فشل. بعمليتين متوازيتين على `ejaz_testing_seclows`.
- **المراجعتان:** بلا حرجة ولا عالية.
  - **`security-reviewer`:** متوسطة واحدة عولجت، وهي أن حد الرفع لم يكن يشمل الرفع المباشر إلى التخزين في الإنتاج. صار يُحتسب عند بدء الرفع.
    - والمنخفضات عولجت: مسار الرفع خلف الدخول، والصفر بلا حد، والبريد برقم الحساب لا بريده.
    - وما بقي من F8 سُجّل في التقرير «امتداد F8»: عرض السر ورموز الاسترداد، وشاشة تصفير.
  - **`code-reviewer`:** المتوسطات عولجت:
    - إحصاء التقرير.
    - إشعار F8 كما في خطته.
    - وصف «كثيرة في اليوم» بدل «متتابعة».
  - والمنخفضات عولجت:
    - اليوم بتوقيت الرياض.
    - العتبة أقل من 30.
    - الصفر بلا عدّاد زائد.
    - اختبار الصفر.
    - تعليق مسار الرفع.
    - تبعة F8 في التقرير.

## بعد الدمج

- لا ترحيل، ولا مكتبة جديدة.
- يُحدَّث `docs/HANDOFF.md`: الباقي من الفحص الأمني «امتداد F8» وF10.
- في الإنتاج: القيم الافتراضية تكفي، ولا يلزم ضبط جديد.
