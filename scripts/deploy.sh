#!/usr/bin/env bash
# نشر إصدار على خادم الإنتاج أو التجريبية — بوابة جمعية إعجاز (docs/deploy.md)
#
# التشغيل على الخادم، من مستخدم النشر المالك للشيفرة (ejaz-deploy، لا www-data — docs/deploy.md الملحق)، من جذر المشروع:
#   bash scripts/deploy.sh v1.2.0
#
# - يُنشر وسم (tag) لا فرع، فيُعرف ما يعمل على الخادم بالضبط.
# - لا cache:clear ولا optimize:clear: يصفّران قفل الدخول وحدود الرسائل ورموز التحقق (docs/security.md).
# - متعدد الخوادم: الترحيل والبذر على خادم واحد (RUN_MIGRATIONS=true)، والبقية بـ RUN_MIGRATIONS=false.
# - عند أي خطأ يتوقف السكربت ويبقى الموقع في وضع الصيانة، ليُراجَع قبل `php artisan up` (القسم «إن فشل النشر» في docs/deploy.md).
#
# متغيرات اختيارية:
#   RUN_MIGRATIONS=true|false   الترحيل والبذر (افتراضياً true)
#   BUILD_ASSETS=true|false     بناء الواجهة على الخادم (افتراضياً true). وfalse إن رُفعت public/build جاهزة من الفحص الآلي
#   MAINTENANCE_SECRET=...      رابط تجاوز الصيانة للمراجعة: https://ejaz.sa/<السر>
#   RELOAD_FPM_CMD="..."        أمر إعادة تحميل PHP-FPM لتفريغ OPcache (افتراضياً: sudo systemctl reload php8.4-fpm)
set -euo pipefail

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

TAG="${1:-}"
[ -n "$TAG" ] || fail "حدّد الوسم: bash scripts/deploy.sh v1.2.0"

RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
BUILD_ASSETS="${BUILD_ASSETS:-true}"
RELOAD_FPM_CMD="${RELOAD_FPM_CMD:-sudo systemctl reload php8.4-fpm}"

cd "$(dirname "$0")/.."

say "١/١١ فحوص ما قبل النشر"
[ -f .env ] || fail "لا ملف .env في $(pwd)"
grep -Eq '^APP_ENV=(production|staging)$' .env || fail "APP_ENV ليس production ولا staging"
grep -Eq '^APP_DEBUG=false$' .env || fail "APP_DEBUG يجب أن يكون false"
grep -Eq '^APP_URL=https://' .env || fail "APP_URL يجب أن يبدأ بـ https://"
grep -Eq '^SESSION_SECURE_COOKIE=true$' .env || fail "SESSION_SECURE_COOKIE يجب أن يكون true (والتجريبية أيضاً، فافتراضه آمن في production وحده)"
grep -Eq '^TRUSTED_PROXIES=.+' .env || echo "تنبيه: TRUSTED_PROXIES فارغ. خلف موزع أحمال تتعطل حدود الشبكات (docs/security.md)"
grep -Eq '^TRUSTED_PROXIES=.*\*' .env && echo "تنبيه: TRUSTED_PROXIES=* يصدّق رأس X-Forwarded-For من أي مصدر، فتُزوَّر حدود الشبكات. لا * إلا والخادم معزول خلف موزع يستبدل الرأس، والأفضل عناوين الموزع بعينها (docs/security.md)"
grep -Eq '^SMS_DRIVER=log$' .env && echo "تنبيه: SMS_DRIVER=log. رموز التحقق لا تصل حتى يُضبط المزود من «قنوات التواصل» في اللوحة (docs/guides/messaging.md)"
[ -z "$(git status --porcelain --untracked-files=no)" ] || fail "في المجلد تعديلات غير محفوظة. لا تُعدَّل الشيفرة على الخادم"
git fetch --tags --force --quiet origin
git rev-parse -q --verify "refs/tags/$TAG" >/dev/null || fail "الوسم $TAG غير موجود"

say "٢/١١ وضع الصيانة"
if [ -n "${MAINTENANCE_SECRET:-}" ]; then
  php artisan down --retry=60 --refresh=15 --secret="$MAINTENANCE_SECRET"
else
  php artisan down --retry=60 --refresh=15
fi
trap 'printf "\n\033[1;31m✗ فشل النشر عند السطر %s. الموقع باقٍ في وضع الصيانة — راجع docs/deploy.md\033[0m\n" "$LINENO" >&2' ERR

say "٣/١١ سحب الوسم $TAG"
git checkout --quiet --force "refs/tags/$TAG"

say "٤/١١ مكتبات PHP بلا أدوات التطوير"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

if [ "$BUILD_ASSETS" = "true" ]; then
  say "٥/١١ بناء الواجهة"
  npm ci --no-audit --no-fund
  npm run build
else
  say "٥/١١ بناء الواجهة متخطى (BUILD_ASSETS=false)"
  [ -f public/build/manifest.json ] || fail "لا public/build/manifest.json"
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
  say "٦/١١ ترحيل قاعدة البيانات"
  php artisan migrate --force

  say "٧/١١ الأدوار والصلاحيات من config/permissions.php"
  # آمن للتكرار: ينشئ الأدوار الجديدة ويزامن صلاحيات كل دور (RolesSeeder ثم PermissionsSeeder)
  php artisan db:seed --class=AccessSeeder --force
  # القائمة الرئيسية بسبعة عناصر (MainMenuSevenSeeder، النسخة 0.15): مرة واحدة على الخادم بعلامة في .deploy-state، فلا يعيد كل
  # نشر ترتيب ما رتّبه المحرر في «القائمة» ولا يخفي ما أظهره بعدها (كما في scripts/deploy-compose.sh)
  if [ ! -f .deploy-state/main-menu-seven ]; then
    php artisan db:seed --class=MainMenuSevenSeeder --force
    mkdir -p .deploy-state && touch .deploy-state/main-menu-seven
  fi
else
  say "٦-٧/١١ الترحيل والبذر متخطيان على هذا الخادم (RUN_MIGRATIONS=false)"
fi

say "٨/١١ ذاكرة المحتوى العام وحدها"
php artisan ejaz:refresh-content

say "٩/١١ ذاكرة الإعدادات والمسارات والقوالب والأحداث"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize
[ -L public/storage ] || php artisan storage:link

say "١٠/١١ إعادة تشغيل العمال وتفريغ OPcache"
# يُنهي Horizon بعد إكمال مهامه الجارية، ويعيد systemd تشغيله بالشيفرة الجديدة
php artisan horizon:terminate
$RELOAD_FPM_CMD

say "١١/١١ رفع وضع الصيانة"
php artisan up
trap - ERR

say "فحص ما بعد النشر"
APP_URL="$(grep -E '^APP_URL=' .env | cut -d= -f2-)"
curl -fsS -o /dev/null "$APP_URL/up" && echo "✓ $APP_URL/up" || echo "✗ $APP_URL/up لا يرد 200 — راجع السجل فوراً"
php artisan ejaz:health || echo "تنبيه: فشل فحص من فحوص الصحة أعلاه. نبض المجدول يحتاج دقيقة بعد أول نشر."

printf '\n\033[1;32m✓ نُشر %s\033[0m\n' "$TAG"
