# جاهز للدمج — `refactor/cleanup-2`

التاريخ: 13 أكتوبر 2026. الفرع من `origin/preview/local` عند `d0c3cdb`. تنظيف بلا تغيير سلوك، وهو البند الإضافي في `roadmap.md` من المراجعة النهائية (12 أكتوبر).

## ما فيه

1. **إجراء واحد لسجل الاطلاع والتنزيل:** `app/Actions/Activity/LogAccess`، بدل `LogDocumentAccess` و`LogJobFileAccess` و`LogRegisterAccess` و`LogFileDownload`.
   - كل مستدعٍ يمرر اسم سجل وحدته وحدثه وخصائصه كما كانت. واستدعاءات الجمعية العمومية صارت `FileDownloaded` مع `['file' => …]` صراحة، كما كان يكتبها `LogFileDownload`.
   - ويرفض اسم سجل ليس في تصفية سجل العمليات (`ActivityResource::LOGS`)، فلا يضيع قيد بخطأ إملائي. والاختبار الجديد `tests/Feature/Admin/LogAccessTest.php` يفحص هذا، ويفحص أسماء السجلات في كل المستدعين.
2. **فحص «PDF أو صورة معاد ترميزها» في مكان واحد:** `app/Support/PrivateUpload::check`، بدل نسخه في `SaveArchiveItem` و`SaveJobFileDocument` و`SaveRegisterEntry`.
   - ما بقي كما كان:
     - الترتيب: فحص «الملف مستعمل» أولاً.
     - حقل الرفض ورسالته لكل وحدة.
     - حد الحجم قبل القراءة.
     - الحذف عند الرفض داخل المجلد وحده.
     - الحجم والامتداد.
   - وفحص البكسلات الخاص بملفات الوظائف صار من `ImageSanitizer` (السقف نفسه، 40 مليوناً)، والنتيجة نفسها: حذف ورفض.
3. **حارس المسار الواحد:** `app/Support/StoragePath::inDirectory`، بدل:
   - `PrivatePdf::inDirectory`.
   - نسخ `SaveDocument` و`PublicImages`.
   - جسم `LibraryItem::isOwnFile`.
   - و`CompetitionMedia` تفوّض إليه.
4. **مساعدات الاختبار في `tests/Pest.php`:**
   - `imageBytes()` بدل `tinyImage` و`pngBytes` و`pngOf`، والتوليد اليدوي في ثلاثة اختبارات، بالأبعاد والصيغ نفسها.
   - `storedPrivateFile()` بدل `uploadedPdf`، ونسخ حفظ الملف في `archiveFile` و`registerFile` و`jobFileUpload` و`formFile` و`meetingAttachment` و`requestWithFile`، بالمحتوى نفسه.
   - لم تتغير أي نتيجة متوقعة في الاختبارات القائمة.

## نتيجة الفحص

- Pint: ناجح. PHPStan: صفر أخطاء.
- **الاختبارات:** 1859 اختباراً (منها 3 جديدة)، نجح 1858، وتُخطي 1، ولا فشل. على قاعدة `ejaz_testing_cleanup2`، بثلاث عمليات متوازية، بعد بناء الواجهة.
- **المراجعتان:** `code-reviewer` و`security-reviewer`، بلا حرجة ولا عالية.
  - `security-reviewer`: لا متوسطة. والمنخفضة الوحيدة، وهي أن اسم السجل نص حر، عولجت بالرفض والاختبار.
  - `code-reviewer`: المتوسطتان عولجتا:
    - اسم السجل، بالرفض نفسه.
    - إحالة الخارطة إلى هذا الملف، وهو موجود الآن.
  - والمنخفضات عولجت:
    - ثوابت `PrivateUpload` خاصة، وتعليق حد الحجم.
    - تحرير نسخة الملف من الذاكرة قبل فحص الـ PDF.
    - توحيد بقية توليد الصور في الاختبارات.

## بعد الدمج

- لا ترحيل، ولا مكتبة، ولا تغيير في الواجهة.
- في `roadmap.md` يصير البند ✅.
