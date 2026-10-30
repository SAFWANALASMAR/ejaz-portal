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
   - وقارئ الكتب «للقراءة فقط» (`library-read-only.spec.mjs`): كتاب منشور من صفحتين فأكثر، مطفأ فيه «يُتاح تنزيل الملف»، ومعرّفه في `READ_ONLY_SLUG`. مع الأصول المبنية: `docker compose run --rm --no-deps -e VITE_HOST=none -e READ_ONLY_SLUG=demo-book browser npx playwright test --config=tests/browser/playwright.config.mjs library-read-only.spec.mjs`. ولقطاته في `docs/design/screenshots/library-read-only/` (`SHOT_PREFIX`، وafter افتراضياً).
3. **احذف القيم والمسارات المؤقتة:**
   - احذف القيم من اللوحة نفسها، فتُمسح ذاكرتها.
   - وإن عُدّلت القاعدة مباشرة، فامسح الذاكرة كلها بـ `php artisan cache:clear` (في البيئة المحلية وحدها)، لأن `ejaz:refresh-content` لا يمس ذاكرة الإعدادات.
   - ثم أعد اللقطات الحقيقية.

لا تُحفظ القيم ولا المسارات التجريبية في قيت ولا في أي بذرة (seeder).

## فحص الوصول (WCAG 2.1 AA)

صورة Playwright في كل أوامر هذا الدليل `v1.64.1-noble`، مطابقة لـ `@playwright/test` في `package.json`. وخدمة `browser` في `compose.yaml` ما زالت `v1.63.0` في main، ويصححها #95.

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
  -e DEMO_CREDENTIALS="$creds" mcr.microsoft.com/playwright:v1.64.1-noble \
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

## الزحف النهائي وجولة العرض

تُشغَّلان من جذر المستودع (المسارات نسبية له، والمخرجات في `storage/playwright/` المستثنى من git)، على نسخة محلية وحدها، وبالبيئة نفسها: `FULL_SITE_URL` و`DEMO_PASSWORD` و`DEMO_TOTP_SECRET` و`DEMO_DOMAIN`.

- **الزحف** (`final-crawl.mjs`، يقرأ فقط): كل رابط داخلي للزائر والمستفيد بحالته ولغته واتجاهه وأخطاء وحدة التحكم، والتمرير الأفقي على الجوال، وكل صف في `docs/legacy-import/redirects.csv`، وكل ملف مرتبط. `node tests/browser/final-crawl.mjs`، والنتيجة في `storage/playwright/final-check/crawl.json`.
- **جولة العرض** (`demo-tour.spec.mjs`، فيديو صامت بعناوين عربية تُحقن أثناء التسجيل وحده): `npx playwright test --config=tests/browser/demo-tour.config.mjs`، و`TOUR=full` أو `TOUR=short` لأحدهما. والفيديو WebM في `storage/playwright/demo-tour/`.

## لوحة الإدارة بوضع SPA

`admin-spa.spec.mjs` على العروض الثلاثة: التنقّل بين ثلاث صفحات من القائمة الجانبية بلا إعادة تحميل كاملة، وحقل التوقيع في نموذج وُصل إليه بالتنقّل (رسم، ومسح، وحفظ)، وتنزيل ملف تصدير، وانتهاء مهلة الخمول يُخرج المشرف إلى صفحة الدخول بتحميل كامل.

يحتاج مشرفاً تجريبياً بتحقق ثنائي ونموذجاً بحقل توقيع (`ejaz:demo-user --admin`، البيئة المحلية وحدها، وكلمة المرور والمفتاح عشوائيان في كل تشغيل)، وخادماً ثانياً بمهلة خمول دقيقة واحدة، حتى لا ينتظر الاختبار نصف ساعة. و`--no-reload` لازم، لأن `artisan serve` بدونه لا يمرر متغيرات البيئة إلى عماله:

```bash
docker compose run -d --name ejaz-spa-app -e ADMIN_IDLE_MINUTES=1 app php artisan serve --host=0.0.0.0 --port=8000 --no-reload
creds=$(docker compose exec -T app php artisan ejaz:demo-user --admin --json)

docker compose run --rm -e APP_HOST=ejaz-spa-app -e ADMIN_IDLE_MINUTES=1 -e ADMIN_SPA_CREDENTIALS="$creds" browser \
  npx playwright test --config=tests/browser/playwright.config.mjs admin-spa.spec.mjs

docker rm -f ejaz-spa-app
```

- بلا `ADMIN_SPA_CREDENTIALS` يُتخطى الملف كله، وبلا `ADMIN_IDLE_MINUTES` يُتخطى اختبار الخمول وحده.
- كل تشغيل يحفظ نموذجاً معبأً تجريبياً في قاعدة التطوير، ولقطة حقل التوقيع في `docs/design/screenshots/admin-spa-signature-<العرض>.png`.
- رمز التحقق الثنائي لا يُقبل مرتين، فكل دخول ينتظر نافذة جديدة (حتى 30 ثانية).

## التنقّل السريع في الواجهة العامة

`public-spa.spec.mjs` على العروض الثلاثة: التنقّل بين أربع صفحات عامة بلا إعادة تحميل (علامة في `window` تبقى)، والتركيز على عنوان الصفحة `h1` (أو المحتوى الرئيسي بتسميته في صفحة بلا عنوان) وإعلان عنوان التبويب والعنصر النشط بعده، ورأس الصفحة بعدد عناصره نفسه عبر التنقّلات ولا تحذير «multiple instances» من Livewire، وزرا الرجوع والتقدّم، وقائمة الجوال والقوائم المنسدلة بعد التنقّل، والشريط بصف واحد وقياسه على الرأس الجديد، وتنزيل وثيقة من الحوكمة، وفحص axe على صفحة وُصل إليها بالتنقّل، والدخول بتحميل كامل، والتحميل الكامل بعد نشر يغيّر الأصول، وصفحة الدخول كاملة حين يصل التنقّل إلى الحساب بلا جلسة، وشريط التقدّم أثناء تنقّل بطيء، ورابط مقطع يوتيوب يعود مكان مشغّله فلا يعمل المقطع عند الرجوع، ولا أخطاء في وحدة التحكم. ومع المستخدم التجريبي: التنقّل بين صفحات الحساب من قائمته والرجوع فيها من الخادم، وصفحة الدخول لا صفحة الحساب بزر الرجوع بعد الخروج، وإعادة طلب صفحة الحساب عند عودتها من ذاكرة الرجوع.

ووظائف الصفحة بعد التنقّل، وبعد تنقّل يعلنه Livewire ولا يبدّل الصفحة (حدثا `navigating` و`navigated` على المحتوى نفسه، ورابط `#`)، وبعد رابط إلى الصفحة نفسها يبدّلها:

- المعرض: العارض يفتح، والتالي صورة واحدة لكل ضغطة، وEsc يغلقه.
- العدّاد التنازلي للبث يتقدّم.
- صفحة الخطأ تُوضع مكان الصفحة بلا تحميل كامل، والرجوع والتقدّم بعدها (ومعهما رابط التخطي) لا يذهبان إلى `/undefined`.
- ومع المستخدم التجريبي:
  - الحفظ التلقائي في المسابقة، ومنه حفظ ينتظر مهلته أثناء تنقّل لا يبدّل الصفحة.
  - حارس رفع المقطع: الرفع يمر بتنقّل لا يبدّل الصفحة وبالرجوع داخلها بعد تحديثها، وتأكيد بنصه عند التنقّل وزر الرجوع، و`beforeunload` عند التحميل الكامل، ويزول باكتمال الرفع، والرجوع بعده يمر بالصفحات بمحتواها. والمغادرة المؤكدة توقفه فلا يُرسل الإنهاء ولا يُسأل ثانية.
  - زر المتابعة في تسجيل الاختبار الإلكتروني يُفعَّل حين تكتمل الحقول.

الأصول مبنية (`npm run build`) بلا خادم Vite، و`--no-deps` حتى لا يبدأ خادم Vite:

```bash
creds=$(docker compose exec -T app php artisan ejaz:demo-user --json)

docker compose run --rm --no-deps -e VITE_HOST=none -e DEMO_CREDENTIALS="$creds" browser \
  npx playwright test --config=tests/browser/playwright.config.mjs public-spa.spec.mjs
```

- اختبار التنزيل يحتاج وثيقة منشورة في الحوكمة، وبدونها يُتخطى، والتخطي يعني أن التنزيل لم يُختبر. ووثائق الاستيراد مسودات، فتُنشأ قبل التشغيل وثيقة تجريبية معلَّمة بوضوح في البيئة المحلية وحدها، ثم تُحذف بعده، ولا تُحفظ في قيت ولا في بذرة:

```bash
# قبل التشغيل
docker compose exec -T app php artisan tinker --execute '$d = App\Models\Document::factory()->create(["slug" => "demo-spa-test", "title" => "وثيقة تجريبية لاختبار التنقل (تُحذف)"]); Storage::disk(App\Models\Document::disk())->put($d->file, "%PDF-1.4\n%%EOF"); Artisan::call("ejaz:refresh-content");'
# بعده
docker compose exec -T app php artisan tinker --execute '$d = App\Models\Document::where("slug", "demo-spa-test")->first(); Storage::disk(App\Models\Document::disk())->delete($d->file); $d->forceDelete(); Artisan::call("ejaz:refresh-content");'
```

- واختبارات وظائف الصفحة تحتاج ألبوماً بثلاث صور، وبثاً قادماً بعد 30 يوماً (بعد مدة الإعلان في الرأس، فلا تتغير القائمة)، ومسابقة بسؤال نصي وسؤال فيديو، واختباراً إلكترونياً. وبدونها تُتخطى. تُنشأ معلَّمة بوضوح في البيئة المحلية وحدها، وتُحذف بعده مع مشاركات المستخدم التجريبي فيها، ولا تُحفظ في قيت ولا في بذرة. والصور على القرص العام، فيلزمه `php artisan storage:link` مرة:

```bash
# قبل التشغيل
docker compose exec -T app php artisan tinker --execute '$img = file_get_contents(public_path("icon-192.png")); foreach ([1, 2, 3] as $i) { Storage::disk("public")->put("albums/demo-spa-$i.png", $img); } App\Models\Album::factory()->create(["slug" => "demo-spa-album", "title" => "ألبوم تجريبي لاختبار التنقل (يُحذف)", "published_on" => now(), "photos" => ["albums/demo-spa-1.png", "albums/demo-spa-2.png", "albums/demo-spa-3.png"]]); App\Models\LiveStream::factory()->create(["title" => "بث تجريبي لاختبار التنقل (يُحذف)", "starts_at" => now()->addDays(30), "ends_at" => now()->addDays(30)->addHours(2)]); App\Models\Competition::factory()->withQuestions()->create(["slug" => "demo-spa-entry", "title" => "مسابقة تجريبية لاختبار التنقل (تُحذف)"]); App\Models\Competition::factory()->exam()->create(["slug" => "demo-spa-exam", "title" => "اختبار تجريبي لاختبار التنقل (يُحذف)"]); Artisan::call("ejaz:refresh-content");'
# بعده
docker compose exec -T app php artisan tinker --execute 'App\Models\Album::where("slug", "demo-spa-album")->forceDelete(); Storage::disk("public")->delete(["albums/demo-spa-1.png", "albums/demo-spa-2.png", "albums/demo-spa-3.png"]); App\Models\LiveStream::where("title", "بث تجريبي لاختبار التنقل (يُحذف)")->forceDelete(); foreach (App\Models\Competition::whereIn("slug", ["demo-spa-entry", "demo-spa-exam"])->get() as $c) { foreach ($c->submissions()->withTrashed()->get() as $s) { $s->videos()->each(fn ($v) => $v->delete()); $s->forceDelete(); } $c->questions()->delete(); $c->criteria()->delete(); $c->forceDelete(); } Artisan::call("ejaz:refresh-content");'
```

- ذاكرة الرجوع في المتصفح (bfcache) لا تعمل في متصفح Playwright المؤتمت ولو أُزيل مفتاح إطفائها (`BackForwardCacheDisabledForDelegate`)، فيُختبر مسار الخادم بعد الخروج، وقرار `page.js` بحدث `pageshow` من الذاكرة يُطلق على الصفحة نفسها. والتجربة الفعلية لها في متصفح عادي: الدخول، ثم صفحة عامة، ثم «حسابي»، ثم الخروج، ثم زر الرجوع، والمتوقع صفحة الدخول.
- اختبار التحميل الكامل بعد النشر يحتاج الأصول المبنية، فيُتخطى مع خادم Vite (التشغيل الافتراضي أعلى الملف).
- اختبار القائمة المنسدلة يتبع رابط «البرامج» من بذرة القائمة (`MenuSeeder`)، لأن صفحات «عن الجمعية» مسودات حتى تعتمدها الجمعية.

## لوحة الإحصائيات

`admin-stats.spec.mjs` على العروض الثلاثة: المجموعات الأربع بعناوينها وأرقامها، ووقت آخر حساب، ورسم الزيارات في آخر 30 يوماً مرسوماً فعلاً بنصه البديل لقارئ الشاشة، والاتجاه من اليمين، وبلا تمرير أفقي ولا أخطاء في وحدة التحكم ولا مخالفة axe في العناصر. واللقطات في `docs/design/screenshots/admin-stats-<العرض>.png`.

يحتاج مشرفاً تجريبياً بتحقق ثنائي (`ejaz:demo-user --admin`)، والأصول مبنية (`npm run build`). ويحتاج قبل التشغيل:

- **زيارة معدودة واحدة على الأقل في آخر 30 يوماً**، منقولة إلى القاعدة. تكفي صفحة عامة من متصفح، أو طلب curl الذي في الأوامر أدناه. وبدونها يظهر الرسم فارغاً، فيفشل الاختبار عند صورة الرسم.
- **حساباً للإحصائيات** (`ejaz:refresh-stats`)، وإلا لم تظهر المجموعات.

```bash
curl -s -o /dev/null -A 'Mozilla/5.0' "http://localhost:${APP_PORT:-8000}/"
docker compose exec -T app php artisan ejaz:refresh-stats
creds=$(docker compose exec -T app php artisan ejaz:demo-user --admin --json)

docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_STATS_CREDENTIALS="$creds" browser \
  npx playwright test --config=tests/browser/playwright.config.mjs admin-stats.spec.mjs
```

- بلا `ADMIN_STATS_CREDENTIALS` يُتخطى الملف. ومع `admin-spa.spec.mjs` في تشغيل واحد يُمرَّر المتغيران من `$creds` نفسه، وآخر نافذة للرمز في ملف مشترك.
- كل عرض دخول بالتحقق الثنائي، والحساب نفسه لا يتجاوز عشر محاولات في ربع ساعة (`two-factor` في `FortifyServiceProvider`). فإن تكرر التشغيل ورُدّ بـ 429، فيُنتظر ربع ساعة.
- الأرقام في اللقطات من قاعدة التطوير كما هي، والزيارات من تصفح حقيقي لها. ولا تُدخل أرقام ولا أيام زيارات تجريبية في قيت ولا في بذرة.

## مسابقة الطلاب

`student-competition.spec.mjs` على العروض الثلاثة: رحلة الطالب كاملة (صفحة المسابقة، والتسجيل، والبدء، والصفحات بالعداد والنص والمقطع، والمراجعة، والتسليم، و«تم استلام إجاباتك»، و«مشاركاتي» بلا درجة قبل النشر وبها بعده)، ورحلة المدير في اللوحة على الحاسب والجوال (المراحل، والبنك، والنصوص، والحقول، والمسجلون والاطلاع على رقم الهوية، والنتائج، ونافذة السحب، والنموذج، والقائمتان). وفي كل صفحة: الاتجاه من اليمين، وبلا تمرير أفقي ولا أخطاء في وحدة التحكم ولا مخالفة axe. واللقطات في `docs/reviews/screenshots/2026-10-25-competition/`.

يحتاج المشرف التجريبي أولاً ثم المسابقة التجريبية (تمنح المشرف دور المدير التنفيذي للاطلاع على رقم الهوية)، و`COMPETITION_NATIONAL_ID_HMAC_KEY` في `.env`. وكل تشغيل يبدأ المسابقة التجريبية من جديد:

```bash
admin=$(docker compose exec -T app php artisan ejaz:demo-user --admin --json)
demo=$(docker compose exec -T app php artisan ejaz:demo-student-competition --json)

docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_STATS_CREDENTIALS="$admin" -e STUDENT_COMPETITION="$demo" browser \
  npx playwright test --config=tests/browser/playwright.config.mjs student-competition.spec.mjs
```

## قائمة القبول

`director-acceptance.spec.mjs` اختبار متصفح لكل بند يُختبر آلياً في `docs/plans/2026-10-30-acceptance-checklist.md`، على العروض الثلاثة. وكل اختبار فيه يتحقق أيضاً من `dir="rtl"` ومن عدم التمرير الأفقي.

- **بنود main** اختبارات فعلية: الصفحات العامة، والحوكمة ووثيقة، وترتيب الرئيسية، وصفحة المسابقة، وحساب المستفيد بعد رابط إدارة في المتصفح نفسه (#96)، والعودة إلى التسجيل بعد الدخول من صفحة المسابقة.
- **بنود الفروع** مكتوبة كاملة ومعلَّمة `test.fixme(true, 'يُفعَّل بعد دمج <الفرع>')`. **كل فرع يزيل علامة بنوده** في طلب دمجه، ويعدّل المحدد إن سمّى مكوّنه بغير الاسم المكتوب، ولا يكتب الاختبار من جديد.
- **ما يحتاج غائباً يُتخطى برسالة:** المسابقة غير المنشورة، والوثيقة أو البرنامج غير الموجود، والحساب غير الممرَّر.

```bash
# حساب مستفيد تجريبي (البيئة المحلية وحدها)، والمسابقة المنشورة
creds=$(docker compose exec -T app php artisan ejaz:demo-user --json)

docker compose run --rm -e DEMO_CREDENTIALS="$creds" -e COMPETITION_SLUG=brahin-1448 browser \
  npx playwright test --config=tests/browser/playwright.config.mjs director-acceptance.spec.mjs
```

- `BENEFICIARY_CREDENTIALS`: يتقدم على `DEMO_CREDENTIALS` إن كان المستخدم التجريبي يدخل اللوحة، لأن اختبار #96 لمستفيد لا يدخلها.
- `PRIZE_TOTAL`: مجموع الجوائز كما يُعرض (الافتراضي `20,000`). و`LIBRARY_READONLY_PATH`: مسار كتاب «للقراءة فقط» منشور. و`EVIDENCE_GALLERY_PATH`: مسار معرض شواهد صحيح.
- **على حاويات شجرة عمل أخرى** بلا تشغيل حاويات جديدة: المتصفح على شبكة حاوية التطبيق، ومجلد المكتبات من حجمها للقراءة. الأمر لـ **Git Bash على ويندوز** (`MSYS_NO_PATHCONV=1` يمنع تحويل المسارات، و`pwd -W` يعطي مسار ويندوز لـ Docker). وفي لينكس أو ماك: بلا المتغير، و`$PWD` مكان `$(pwd -W)`:

```bash
MSYS_NO_PATHCONV=1 docker run --rm --ipc=host --network container:<المشروع>-app-1 \
  -v "$(pwd -W):/work" -v <المشروع>_node_modules:/work/node_modules:ro -w /work \
  -e APP_PORT=<APP_PORT في .env المشروع> -e APP_HOST=127.0.0.1 -e APP_UPSTREAM_PORT=8000 \
  mcr.microsoft.com/playwright:v1.64.1-noble \
  npx playwright test --config=tests/browser/playwright.config.mjs director-acceptance.spec.mjs
```

- **معايير التفوق** (`-g excellence`): axe بوسوم WCAG **2.2** AA (`wcag22aa` مع وسوم 2.1)، والعنوان والوصف وOpen Graph، والبيانات المنظمة، و404، ورؤوس الحماية، وأهداف اللمس، وأبعاد الصور، والخطوط، و«Slow 3G». وما فشل في القياس الأول معلَّم `fixme` بسببه «فجوة حالية». أما `a11y.spec.mjs` فباقٍ على وسوم 2.1 لصفحات الحساب واللوحة.

## معايير التفوق: Lighthouse

بلا مكتبة في المشروع: `npx` يجلب Lighthouse بإصدار مثبّت في حاوية Playwright نفسها، ويستعمل متصفحها. والأداء يُقاس على الخادم التجريبي أو نسخة إنتاج محلية (الأصول مبنية، وopcache بلا فحص الملفات، و`SEO_INDEXABLE=true`)، لأن خادم Vite وفهرسة التطوير المطفأة يخفضان درجتي الأداء ومحركات البحث. والأرقام تُكتب في وصف طلب الدمج وفي قائمة القبول (القسم ١٧).

على خادم يصل إليه المتصفح مباشرة (التجريبي مثلاً):

```bash
docker run --rm --ipc=host -v "$PWD:/lh" -w /lh mcr.microsoft.com/playwright:v1.64.1-noble sh -c '
  export CHROME_PATH=$(ls -d /ms-playwright/chromium-*/chrome-linux*/chrome | head -1)
  for p in / /competitions/brahin-1448 /programs/<برنامج> /library/books/<كتاب>; do
    npx -y lighthouse@13.5.0 "https://<الخادم>$p" --quiet --chrome-flags="--headless=new --no-sandbox" \
      --only-categories=performance,accessibility,best-practices,seo \
      --output=json --output=html --output-path="/lh/lighthouse$(echo $p | tr / _)"
  done'
```

- الافتراضي محاكاة الجوال، وهو المعيار (≥ 95 في المعايير الأربعة، وLCP أقل من 2.0 ث، وCLS أقل من 0.05).
- **على حاويات شجرة عمل محلية:** على شبكة حاوية التطبيق كما في «قائمة القبول»، مع سكربت صغير ينقل منفذ `APP_URL` إلى `127.0.0.1:8000` ومنفذ Vite (5173) إلى `vite:5173` قبل Lighthouse، كما يفعل `global-setup.mjs`. وبه قيس خط الأساس في قائمة القبول.
- ورؤوس الحماية: securityheaders.com على الخادم التجريبي بـ HTTPS (المعيار A+). والمكتبات: `composer audit` و`npm audit`.
