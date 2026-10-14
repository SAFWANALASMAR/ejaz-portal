# جاهز للدمج — `fix/sec-draw-void-limit`

التاريخ: 14 أكتوبر 2026. الفرع من `origin/main` عند `54679b4`. البند ١ من المجموعة ٣ في مهمة الإصلاحات الأمنية (منطق العمل): تكرار السحب الإلكتروني بتواطؤ. بموافقة صفوان.

## ما فيه

1. **إلغاء سحب واحد لكل مسابقة بقرار مشرف عام واحد:** `app/Actions/Competitions/VoidDraw.php`.
   - الإلغاء الأول كما كان: للمشرف العام بسبب إلزامي، ويُتاح بعده سحب جديد لغير من ألغى.
   - أي إلغاء بعده **طلب لا إلغاء**: يُسجَّل في `competition_draw_void_requests`، ويبقى السحب معتمداً، فلا سحب جديد.
2. **اعتماد الطلب من مشرف عام ثانٍ غير الطالب:** `app/Actions/Competitions/ApproveDrawVoid.php` و`CompetitionDrawPolicy::approveVoid`.
   - نتيجة السياسة صريحة، فلا يجعلها `Gate::after` صحيحة لطالب الإلغاء.
   - يُسجَّل الإلغاء باسم الطالب وسببه، ومعه `approved_by`، فيبقى الطالب ممنوعاً من إعادة السحب (`RunDraw` كما كان).
3. **ترحيل جديد** `2026_10_14_090000_create_competition_draw_void_requests_table.php`، بلا لمس الترحيلين المدموجين:
   - جدول الطلبات بمشغّل يمنع التعديل والحذف والتفريغ، كسجلي السحب والإلغاء.
   - عمود `approved_by` في `competition_draw_voids`.
4. **اللوحة** (`DrawsRelationManager`):
   - زر «إلغاء السحب» يرسل طلباً بعد الإلغاء الأول، بوصف يوضح ذلك.
   - الحالة «إلغاء بانتظار الاعتماد» بلون التنبيه وسبب الطلب.
   - زر «اعتماد الإلغاء» للمشرف العام الآخر وحده، ومعتمد الإلغاء في صفحة السحب.
5. **سجل العمليات:** حدثان جديدان `draw_void_requested` و`draw_void_approved` بتسمياتهما العربية.
6. **القرار** في `docs/decisions.md` (14 أكتوبر 2026)، وسؤال لصفوان في `docs/open-questions.md`: مصير الطلب المعلّق (أ / ب / ج).

## الاختبارات المانعة

في `tests/Feature/Competitions/DrawVoidLimitTest.php`. فشل أربعة منها قبل الإصلاح، ونجحت بعده:

- `refuses a second void without a second super admin, and keeps the draw active`
- `does not let one super admin repeat the draw alone, even across several voids`
- `keeps void requests append-only in the database`
- `requests and approves a second void from the panel`

والخامس `voids the first draw of a competition directly, without a second approval` يحرس السلوك القائم للإلغاء الأول. واختبارات `DrawTest.php` كلها ناجحة بلا تعديل.

## نتيجة الفحص

- Pint: ناجح. PHPStan: صفر أخطاء.
- **الاختبارات:** 1875 اختباراً، نجحت كلها (8617 تأكيداً)، ولا فشل ولا تخطٍّ. بعمليتين متوازيتين على `ejaz_testing_g3a`.

## ما بقي

- قرار صفوان في الطلب المعلّق: يبقى (المبني)، أم يُرفض بسبب، أم يسقط بعد مدة.
- عند النشر: `php artisan migrate --force` للترحيل الجديد.
