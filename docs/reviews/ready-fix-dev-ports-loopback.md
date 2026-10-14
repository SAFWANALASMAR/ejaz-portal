# جاهز للدمج — `fix/dev-ports-loopback`

التاريخ: 14 أكتوبر 2026. طلب الدمج #16. البند ٦ من «الإصلاحات الأمنية ١ — تحصين»، وبند ⬜ في `roadmap.md` (المرحلة 0).

## ما فيه

- `compose.yaml`: كل المنافذ على `127.0.0.1` (الموقع، وVite، وPostgreSQL، وRedis، وMailpit). والقالب يضيف العنوان بنفسه، فقيمة المنفذ في `.env` رقم وحده.
- Redis بكلمة مرور حتى في التطوير (`--requirepass`)، و`docker compose` يرفض التشغيل بدون `REDIS_PASSWORD`.
- `scripts/setup.sh` يولّد `REDIS_PASSWORD` عشوائية إن كانت فارغة أو `null`، و`.env.example` بلا قيمة.
- Mailpit بإصدار مثبّت `v1.31.4` بدل `latest`.
- `tests/Feature/Platform/DevComposeTest.php`: كل منفذ على 127.0.0.1، وRedis بكلمة مرور، ولا صورة بوسم `latest`. فشل الاختبار على القالب القديم، ونجح بعد الإصلاح.
- `docs/workflow.md` و`docs/roadmap.md` (✅).

## الفحص

- Pint: ناجح (945 ملفاً). PHPStan: صفر أخطاء.
- الاختبارات: 1871 ناجحة (8593 تحققاً).
- `docker compose config`: المنافذ كلها على 127.0.0.1، وبلا كلمة مرور Redis يرفض برسالة واضحة.
- Redis في مشروع تجريبي معزول: بلا كلمة مرور `NOAUTH`، وبها `PONG`، والفحص الصحي سليم.

## بعد الدمج (على كل جهاز تطوير)

1. احذف من `.env` كل قيمة `FORWARD_*` فيها `127.0.0.1:`، وإلا رفض التشغيل (`invalid IP address`).
2. `bash scripts/setup.sh`، أو ضع `REDIS_PASSWORD` عشوائية (16 حرفاً فأكثر) في `.env` يدوياً.
3. `docker compose up -d` لإعادة إنشاء الحاويات.

لا ترحيل، ولا مكتبة جديدة.
