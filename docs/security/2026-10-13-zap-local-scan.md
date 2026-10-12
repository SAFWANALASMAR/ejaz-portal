# فحص الاختراق المحلي بـ OWASP ZAP — 13 أكتوبر 2026

**النتيجة:**
- لا ثغرة عالية.
- ولا ثغرة متوسطة في سلوك التطبيق نفسه.
- لا حقن SQL، ولا XSS، ولا تجاوز مسار، ولا تضمين ملفات، ولا كشف أخطاء، ولا تجاوز لحماية CSRF.
- المتوسطتان الظاهرتان سببهما Alpine وFilament، لا التطبيق، وهما موثقتان في `decisions.md` منذ 7 أكتوبر.
- عولجت الملاحظات القابلة للعلاج.
- والفحص الفعال: 0 فشل، و135 قاعدة ناجحة.

## النطاق والقواعد

- **الهدف:** النسخة المحلية وحدها: الفرع `chore/quality`، و`php artisan serve` على المنفذ 8182، والقاعدة المحلية `ejaz_quality`.
- **لا إنتاج ولا ejaz.sa:** الفحص كله داخل الجهاز.
- **المحتوى:** محتوى الأرشيف المستورد كاملاً، منشوراً في هذه القاعدة المحلية وحدها، فيزحف الفاحص على صفحات حقيقية: الوثائق، والمكتبة، والمبادرات، والتحويلات القديمة.
- **الأداة:** صورة ZAP الرسمية `ghcr.io/zaproxy/zaproxy:stable` في حاوية مؤقتة تُحذف بعد التشغيل (`--rm`). لا تثبيت على الجهاز، ولا حساب.
- **الفحصان:**
  - الأساسي (`zap-baseline.py`): زحف وقواعد سلبية.
  - الكامل (`zap-full-scan.py`): زحف، ثم هجمات فعالة على كل مسار ومعامل ونموذج، بحد 40 دقيقة.
- **بلا دخول:** اللوحة والحساب فُحصا من الخارج وحده (صفحات الدخول والتسجيل والاستعادة ونماذجها). وفحص ما خلف الدخول مغطى باختبارات الصلاحيات في المستودع، وبالفحوص الأمنية الداخلية (`2026-10-10-security-audit.md` و`2026-10-12-security-audit.md`).

## طريقة الإعادة

```bash
# في مجلد المشروع، وقاعدة محلية فيها محتوى منشور
# على 127.0.0.1 وحده، فلا تنكشف القاعدة المحلية للشبكة. وDocker Desktop يصل إليه عبر host.docker.internal
APP_URL=http://host.docker.internal:8182 php artisan serve --host=127.0.0.1 --port=8182

# في طرفية أخرى، والتقارير في مجلد مؤقت خارج المستودع
docker run --rm --add-host=host.docker.internal:host-gateway -v "<مجلد مؤقت>:/zap/wrk:rw" \
  ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://host.docker.internal:8182 -m 5 -J baseline.json -r baseline.html -I
docker run --rm --add-host=host.docker.internal:host-gateway -v "<مجلد مؤقت>:/zap/wrk:rw" \
  ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://host.docker.internal:8182 -m 5 -J full.json -r full.html -I \
  -z "-config scanner.maxScanDurationInMins=40 -config scanner.threadPerHost=4"
```

التقارير الكاملة (HTML وJSON) لا تُحفظ في قيت، وهذا الملف خلاصتها.

## الملاحظات وما عولج منها

### عولجت في هذا الفرع

- **Cross-Origin-Opener-Policy وCross-Origin-Resource-Policy غائبان (منخفضة):**
  - صارت ردود التطبيق كلها تحمل `Cross-Origin-Opener-Policy: same-origin` و`Cross-Origin-Resource-Policy: same-origin`، من `SecurityHeaders`، مع اختبار في `FoundationTest`.
  - ظهرت في الفحص الأساسي، واختفت من الفحص الفعال بعد الإصلاح.
- **ملفات جذر `public` بلا رؤوس حماية في nginx (منخفضة):**
  - الأيقونة المفضلة وصورها و`/images/` كانت تمر بالكتلة العامة بلا `nosniff`.
  - صارت لها كتلة في `deploy/docker/nginx.conf` و`deploy/nginx/ejaz.conf`، فيها `nosniff` و`Cross-Origin-Resource-Policy` وHSTS.
  - وحُذفت كتلة `= /favicon.ico` من إعداد الخادم بلا حاويات، لأنها كانت تتقدم عليها.
  - وتحقق `nginx -t` من صحة الإعداد في صورة nginx الرسمية.

### من خادم التطوير المحلي، ولا تكون في الإنتاج (منخفضة)

- **`X-Powered-By`، و`X-Content-Type-Options` وPermissions-Policy غائبة عن ملفات `build/` والخطوط والأيقونة:**
  - مصدرها `php artisan serve`، فهو يقدّم الملفات الثابتة بنفسه.
  - وفي الإنتاج يقدّمها nginx بـ `nosniff` في كتل `/build/` و`/fonts/` و`/storage/` وملفات الجذر، ويخفي `X-Powered-By` (`fastcgi_hide_header`، و`expose_php = Off` في `deploy/php/production.ini`).
  - ويُتحقق منها على الخادم التجريبي بعد تجهيزه بإعادة هذا الفحص نفسه (`scripts/verify-server.sh` يفحص رؤوس الرئيسية وحدها، لا رؤوس الملفات الثابتة).

### مقبولة بقرار موثق

- **`script-src 'unsafe-eval'` (متوسطة):**
  - Alpine داخل Livewire وFilament يحتاجه، ونسخته الآمنة لا تدعم تعابير Filament (`decisions.md`، 7 أكتوبر).
  - والسكربتات المضمنة لا تعمل إلا برمز يتولد لكل طلب (nonce)، فالسكربت المحقون لا يعمل.
- **`style-src 'unsafe-inline'` (متوسطة):**
  - Filament وLivewire يضعان أنماطاً مضمنة.
  - والأنماط لا تنفّذ شيفرة، وخطرها محصور في تغيير المظهر.
- **كعكة `XSRF-TOKEN` بلا HttpOnly (منخفضة):** مقصود في لارافيل، ليقرأها JavaScript ويرسلها رأساً مع الطلب. وكعكة الجلسة نفسها HttpOnly.
- **Cross-Origin-Embedder-Policy غائب (منخفضة):** `require-corp` يمنع تضمين مقاطع يوتيوب في المكتبة المرئية والرئيسية، وهي من المحتوى المعتمد.
- **«تحويل كبير» (منخفضة):** ردود التحويل في لارافيل تحمل صفحة HTML صغيرة فيها رابط الوجهة، ولا بيانات حساسة فيها. والمسارات الظاهرة: `/store` إلى رابط المتجر المعتمد، والنماذج بعد الإرسال.
- **«عنصر HTML يتحكم فيه المستخدم» في `/governance?category=` (معلوماتية):** القيمة تُفحص بقائمة التصنيفات المعروفة (`GovernanceIndexRequest`)، وتُهرَّب في القالب. والفحص الفعال لم يجد XSS فيها.

## الخلاصة لما قبل الإطلاق

- يُعاد الفحص نفسه على **الخادم التجريبي** بعد تجهيزه (`docs/deploy/staging-plan.md`)، لا على الإنتاج، فتظهر رؤوس nginx الفعلية.
- والفحص بعد الدخول (اللوحة والحساب) يُضاف هناك بسياق مصادقة في ZAP، بحساب تجريبي على التجريبية وحدها.
