# جاهز للتجربة — فرع التكامل `integration/2026-10-14`

التاريخ: 14 أكتوبر 2026. الفرع من `preview/local` (`b507fce`). يجمع كل العمل الجديد في نسخة واحدة للتجربة الحية على `localhost:8000`، وهو نفسه ما يُدمج في `main` بعد الفحص الآلي ومراجعة المشرف والتجربة.

## الفروع المدموجة (merge عادي، بلا إعادة كتابة تاريخ، بهذا الترتيب)

الفروع الأمنية الخمسة عشر بترتيب طلبات دمجها:

1. `fix/dev-ports-loopback` (#16)
2. `chore/sec-ci-npm-audit-dev` (#17)
3. `chore/sec-ci-pin-actions` (#18)
4. `fix/sec-ci-required-files` (#19)
5. `fix/sec-download-disposition` (#20)
6. `docs/sec-trusted-proxies` (#21)
7. `fix/sec-password-change-notice` (#22)
8. `fix/sec-login-network-limit` (#23)
9. `fix/sec-admin-idle-timeout` (#24)
10. `docs/sec-open-decisions` (#25)
11. `fix/sec-purge-form-entries` (#26)
12. `fix/sec-backup-files-expiry` (#27)
13. `docs/sec-restore-reanonymize` (#28)
14. `fix/sec-draw-void-limit` (#29)
15. `fix/sec-phone-verified-unique` (#30)

ثم:

16. `feat/admin-spa` (#32)
17. `fix/header-single-row` (#31)

## التعارضات وحلولها

القاعدة من خطة الدمج (القضية #6): الجمع لا الاختيار.

- **`docs/decisions.md`** (في 12 دمجاً) و**`docs/open-questions.md`** (في 3: النماذج المعبأة، والاستعادة، وإلغاء السحب): إضافات في آخر الملف أو القسم نفسه. جُمعت كلها بترتيب الدمج، وكلها بتاريخ 14 أكتوبر.
  - وحُذف بعد الجمع سؤال قديم عن نص رسائل التواصل، لأن `docs/sec-restore-reanonymize` استبدله بإجابته وسؤال المدة، وأبقاه الجمع بجانبهما (حفظة مستقلة).
- **`.github/workflows/ci.yml`** (عند `fix/sec-ci-required-files`):
  - بقيت الإجراءات مثبّتة ببصمة الالتزام من `chore/sec-ci-pin-actions`.
  - وحُذف كل شرط `if: steps.boot.outputs.ready == 'true'`، لأن الفرع حذف خطوة `boot` وجعل غياب ملف القفل فشلاً.
  - ومنها شرط خطوة التدقيق التقريري من `chore/sec-ci-npm-audit-dev`: دُمجت بلا تعارض، ولو بقي شرطها لتخطّاها الفحص بصمت دائماً، لأن الخطوة التي يقرأ مخرجها لم تعد موجودة.
  - واختبارها `tests/Feature/Platform/CiDependencyAuditTest.php` كان يشترط ذلك الشرط، ففشل بعد الدمج (الفشل الوحيد في الفحص الأول). صار يشترط أن تعمل الخطوة بلا أي شرط، وهو أشد من الأصل (حفظة مستقلة `961e61f`).
- **`app/Console/Commands/PurgeAnonymizedData.php`** و**`tests/Feature/Accounts/RetentionPurgeTest.php`** (عند `docs/sec-restore-reanonymize`):
  - استيراد `FormEntry` (من `fix/sec-purge-form-entries`) و`Inquiry` معاً.
  - وتعليق الأمر يذكر النماذج المعبأة ونص رسائل التواصل معاً.
  - وتحقق أن إعادة الإخفاء بعد الاستعادة (`ReapplyAnonymization`) تشمل النماذج المعبأة: تمر بإجراء الإخفاء نفسه، والحذف بعد المدة يقرأ `anonymized_at`.

## الترحيلات

ثلاثة ترحيلات (لا اثنان كما في المهمة) تحمل الطابع `2026_10_14_090000`، ولم يدخل أي منها `main`، فغُيّر اثنان في حفظة الدمج نفسها:

- `2026_10_14_090000_make_inquiry_text_nullable.php` — بقي كما هو.
- `2026_10_14_090100_create_competition_draw_void_requests_table.php` — كان `090000`.
- `2026_10_14_090200_make_phone_unique_only_when_verified.php` — كان `090000`.

وبالطابع الواحد كان لارافيل يرتّبها بأسمائها (السحب ثم الاستفسارات ثم الجوال)، وصار الاستفسارات ثم السحب ثم الجوال. ولا أثر للترتيب: كل ترحيل على جدول مستقل ولا يعتمد على غيره. وحُدّثت الإشارات إليها في `docs/decisions.md`. ولم يُعدَّل أي ترحيل مدموج في `main` (`git diff origin/main -- database/migrations` إضافات فقط).

## الفحص

- `composer check` ناجح كاملاً على الحفظة النهائية في المجلد الرئيسي: Pint ناجح (978 ملفاً)، وLarastan بلا أخطاء، و1980 اختباراً ناجحاً (9175 تحققاً).
- الفحص الأول في مجلد العمل المستقل: فشل اختبار واحد من 1980 (`CiDependencyAuditTest`، أثر دمج `ci.yml` أعلاه)، وعولج.
- `npm run build` ناجح (في مجلد العمل وفي حاوية `vite`).
- `php artisan migrate` على القاعدة المحلية: الترحيلات الثلاثة وحدها.

## التشغيل للتجربة (نُفّذ على جهاز صفوان)

- المجلد الرئيسي `ejaz-portal` على `integration/2026-10-14`، بعد انتهاء الفحص الآلي لطلب الدمج #32 وتوقف الجلسة المجاورة. وأُهمل تغيير `package-lock.json` (حذف سطر `"name"` وحده).
- بُني الفرع في مجلد عمل مستقل (`ejaz-wt-integration`) بقاعدة اختبار مستقلة (`ejaz_testing_int`)، فلم يُمس المجلد الرئيسي حتى التبديل.
- `.env` المحلي: حُذفت أسطر `FORWARD_*` الأربعة، و`REDIS_PASSWORD` عشوائية، ثم `docker compose up -d` وإعادة تشغيل الطوابير والمجدول (كانت تحمل إعداد Redis القديم).
- `php artisan migrate` (الترحيلات الثلاثة)، و`db:seed --class=PermissionsSeeder`، و`optimize:clear`.
- `http://localhost:8000` يرد 200، و`/admin` يحوّل إلى الدخول الموحد، وHorizon يعمل.
- حسابا التجربة (مشرف عام بتحقق ثنائي، ومستفيد) بـ `ejaz:demo-user`، وبياناتهما في ملف خارج المستودع على جهاز صفوان، وتحقق آلي أن كلمتي المرور فيه تطابقان القاعدة.
- المنافذ كلها الآن على `127.0.0.1`: الموقع لا يُفتح من جهاز آخر في الشبكة المحلية (مقصود في `fix/dev-ports-loopback`). والرابط المؤقت (`ejaz-demo`) مشروع مستقل لم يُمس.

## بعد السحب على أي جهاز تطوير (من `fix/dev-ports-loopback`)

1. احذف من `.env` كل قيمة `FORWARD_*` فيها `127.0.0.1:`، وإلا رفض `docker compose` التشغيل.
2. ضع `REDIS_PASSWORD` عشوائية (16 حرفاً فأكثر) في `.env`، أو `bash scripts/setup.sh`.
3. `docker compose up -d` ثم `php artisan migrate` ثم `php artisan optimize:clear`.
4. `php artisan db:seed --class=PermissionsSeeder` للصلاحية الجديدة `form_entries.view_all` (من `docs/sec-open-decisions`).
