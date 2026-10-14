# جاهز للدمج — `fix/sec-download-disposition`

التاريخ: 14 أكتوبر 2026. الفرع من `origin/main` عند `54679b4`. البند 4 من مهمة الإصلاحات الأمنية الأولى (تحصين)، بموافقة صفوان على تقرير الفحص.

## ما فيه

- **رأس `Content-Disposition` في `app/Support/PrivateFileResponse.php`** صار يُبنى بـ `HeaderUtils::makeDisposition()` من Symfony بدل وصل الاسم بالنص:
  - تُنزع من الاسم محارف التحكم (ومنها `\r` و`\n`)، فلا يحقن الاسم رأساً جديداً.
  - فواصل المسار `/` و`\` تصير `_` (يرفضها Symfony).
  - الاسم كاملاً يصل في `filename*` بترميز UTF-8، فالاسم العربي سليم.
  - ومعه اسم احتياطي ASCII بلا اقتباس ولا `%`، وإن خلا فهو `file`.
- الرأس نفسه يمر إلى الرابط الموقّع على التخزين السحابي (`ResponseContentDisposition`).
- الأسماء الممررة اليوم ثابتة من الشيفرة (`intro.mp4`، و`media-N`، و`{uuid}.pdf`)، فلا ثغرة مستغلة الآن. الإصلاح يمنعها إن مُرر اسم من المستخدم لاحقاً.
- لا إعداد جديد ولا قرار معماري، فلا قيد في `decisions.md`.

## الاختبار المانع

`tests/Feature/Security/DownloadDispositionTest.php` (3 اختبارات، 12 تأكيداً). فشلت الثلاثة قبل الإصلاح، ونجحت بعده:

- `keeps a newline or a quote in the file name from breaking the header`: اسم فيه `"` و`\r\n` و`Set-Cookie:`.
- `sends an Arabic file name in filename* with an ASCII fallback`: اسم عربي فيه `/`.
- `passes the same safe header to the signed storage link`: فرع التخزين السحابي.

## نتيجة الفحص

- Pint: ناجح (945 ملفاً).
- PHPStan: صفر أخطاء.
- **الاختبارات:** 1871 اختباراً ناجحاً (8583 تأكيداً)، ولا فشل. بعمليتين متوازيتين على `ejaz_testing_sec4`.

## ما بقي

- لا شيء في هذا البند.
