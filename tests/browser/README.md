# اختبارات المتصفح ولقطات الشاشات

تتحقق من كل شاشة على ثلاثة عروض (1440 و820 و390): الاتجاه من اليمين، والعناصر الأساسية، وعدم وجود تمرير أفقي. وتحفظ لقطة كاملة لكل شاشة في `docs/design/screenshots/<الشاشة>-<العرض>.png` للمقارنة بصور التصميم (`docs/design/ui-reference.md`).

تعمل في حاوية Playwright الرسمية على شبكة Docker، فلا يُثبَّت متصفح على الجهاز.

## التشغيل

البيئة تعمل (`docker compose up -d`)، ثم:

```bash
# مستخدم تجريبي بكلمة مرور عشوائية لهذا التشغيل (البيئة المحلية فقط)
creds=$(docker compose exec -T app php artisan ejaz:demo-user --json)

docker compose run --rm -e DEMO_CREDENTIALS="$creds" browser
```

بدون المستخدم التجريبي تُختبر صفحات الزائر فقط.

## إضافة شاشة

أضفها إلى `guestScreens` أو `accountScreens` في `screens.spec.mjs`: الاسم، والمسار، ومحدد العنوان الرئيسي.

## لقطات المقاطع التي لا تظهر بلا محتوى

بعض المقاطع لا تظهر حتى يُدخل محتواها أو يُبنى قسمها، مثل إحصائيات الرئيسية وبطاقات الأقسام. ولمقارنتها بالتصميم تُلتقط مجموعة تجريبية باسم `<الشاشة>-demo-<العرض>.png`:

1. **في البيئة المحلية وحدها:**
   - أدخل من «إعدادات الموقع» قيماً معلَّمة بوضوح «وصف تجريبي».
   - وأضف مؤقتاً مسارات الأقسام التي تظهر بها البطاقات، مثل `governance.index`.
2. شغّل اللقطات، ثم أعد تسمية ملفات الشاشة إلى `-demo-`.
   - صفحتا عنصر المكتبة (`library-item` و`library-video`) لا تُلتقطان إلا من المجموعة التجريبية، بعنصرين معرّفاهما `demo-book` و`demo-video`.
   - وكذلك صفحتا الخبر والألبوم (`news-article` و`album`)، بمعرّفي `demo-news` و`demo-album`.
3. **احذف القيم والمسارات المؤقتة:**
   - احذف القيم من اللوحة نفسها، فتُمسح ذاكرتها.
   - وإن عُدّلت القاعدة مباشرة، فامسح الذاكرة كلها بـ `php artisan cache:clear` (في البيئة المحلية وحدها)، لأن `ejaz:refresh-content` لا يمس ذاكرة الإعدادات.
   - ثم أعد اللقطات الحقيقية.

لا تُحفظ القيم ولا المسارات التجريبية في قيت ولا في أي بذرة (seeder).

## فحص الوصول (WCAG 2.1 AA)

`a11y.spec.mjs`: قواعد axe-core على صفحات الزائر ولوحة المستخدم ولوحة الإدارة، ثم التنقل بلوحة المفاتيح (رابط التخطي، وظهور إطار التركيز، وعدم حبس التركيز، والقوائم المنسدلة وقائمة الجوال). يعمل مع اللقطات في التشغيل أعلاه، ووحده:

```bash
docker compose run --rm -e DEMO_CREDENTIALS="$creds" browser \
  npx playwright test --config=tests/browser/playwright.config.mjs a11y.spec.mjs --project=desktop --project=mobile
```

وعلى خادم محلي بلا حاويات المشروع (`php artisan serve`) والأصول مبنية (`npm run build`):

```bash
APP_URL=http://localhost:8181 php artisan serve --host=127.0.0.1 --port=8181

docker run --rm --ipc=host -v "$PWD:/work" -w /work \
  -e APP_PORT=8181 -e APP_HOST=host.docker.internal -e APP_UPSTREAM_PORT=8181 -e VITE_HOST=none \
  -e DEMO_CREDENTIALS="$creds" mcr.microsoft.com/playwright:v1.63.0-noble \
  npx playwright test --config=tests/browser/playwright.config.mjs a11y.spec.mjs --project=desktop --project=mobile
```

لوحة الإدارة تشترط التحقق الثنائي، فتُفحص بجلسة محفوظة لحساب إداري: ملف `storageState` من Playwright في `storage/playwright/` يُمرَّر بـ `-e ADMIN_STATE=storage/playwright/<الملف>.json`. وبدونه تُتخطى شاشاتها. والملف جلسة دخول حقيقية: في البيئة المحلية وحدها، ويُحذف بعد الفحص.

## التجربة الشاملة كمستخدم حقيقي

`full-site.spec.mjs` بإعداده `full-site.config.mjs`، وهو مستثنى من التشغيل أعلاه. يمر على الموقع كما يمر الزائر والمستفيد وكل بوابة:

- الزحف على كل رابط داخلي.
- التحويلات القديمة كلها.
- التسجيل، وتوثيق البريد، واستعادة كلمة المرور، والتحقق الثنائي.
- الخدمات الإلكترونية، والبرامج، والاستبيان، والتواصل.
- رحلة الاختبار الإلكتروني ومسابقة الأعمال.
- البوابات الخمس بما يُسمح لها وما يُمنع عنها.
- موارد لوحة الإدارة، والإنشاء والتعديل والحذف، والنماذج وطباعتها، وتنبيهات السجلات.
- العرض على الجوال والحاسب.

التقرير: `docs/reviews/2026-10-13-full-site-test.md`.

**يكتب بيانات** (حسابات وطلبات ومشاركات ورسائل)، فلا يعمل إلا على `localhost` و`127.0.0.1` و`*.test`، إلا بـ `FULL_SITE_ALLOW_REMOTE=1`. ويحتاج:

- نسخة تعمل ببيانات المعاينة:
  - حسابات `demo-<الدور>@ejaz-demo.test`.
  - مسابقتا `demo-exam` و`demo-works` بفيديو تعريفي.
  - برنامج `demo-course`.
  - فرص `demo-membership` و`demo-employment` و`demo-volunteering`.
  - استبيان عام.
  - نموذج معبأ معتمد.
- `APP_URL` في تلك النسخة مطابقاً لـ `FULL_SITE_URL`، لأن روابط البريد تُبنى منه.
- Mailpit على `MAILPIT_URL` (الافتراضي `http://localhost:8025`).
- متصفح Google Chrome مثبتاً (`FULL_SITE_CHANNEL`، الافتراضي `chrome`)، لأن Chromium في Playwright لا يشغّل H.264.

```bash
FULL_SITE_URL=http://localhost:8090 \
DEMO_PASSWORD='<كلمة مرور الحسابات التجريبية>' DEMO_TOTP_SECRET='<مفتاح التحقق الثنائي التجريبي>' \
FULL_SITE_ARTISAN='php artisan' FULL_SITE_SHOTS=docs/reviews/screenshots \
npx playwright test --config=tests/browser/full-site.config.mjs
```

- `FULL_SITE_ARTISAN`: أمر artisan للنسخة المختبرة نفسها، لتنبيهات السجلات. وبدونه تُتخطى تلك الخطوة.
- `FULL_SITE_SHOTS`: مجلد اللقطات. الافتراضي `storage/playwright/full-site/screenshots`، وهو خارج git.
- لا كلمة مرور ولا مفتاح في الملفات: كلها من البيئة.

**إعادة التشغيل:** رحلتا المسابقة والاستبيان، وطلب كل فرصة، مرة واحدة لكل حساب. فقبل كل تشغيل تُحذف مشاركات المستفيد التجريبي في قاعدة المعاينة وحدها، وإلا فشل الاختباران برسالة تقول ذلك:

```sql
delete from submission_works;
delete from competition_submissions where user_id = (select id from users where email = 'demo-member@ejaz-demo.test');
delete from program_registrations where user_id = (select id from users where email = 'demo-member@ejaz-demo.test');
delete from survey_responses where user_id = (select id from users where email = 'demo-member@ejaz-demo.test');
delete from service_requests where user_id = (select id from users where email = 'demo-member@ejaz-demo.test');
```
