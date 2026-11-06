#!/usr/bin/env bash
# نشر إصدار بحزمة الحاويات (deploy/compose.production.yaml) — بوابة جمعية إعجاز (docs/deploy.md)
#
# التشغيل على الخادم من جذر المشروع، ومعه .env للإنتاج (من .env.production.example):
#   bash scripts/deploy-compose.sh v1.2.0
#
# - يُنشر وسم (tag)، وتُبنى الصورتان بالوسم نفسه (ejaz-app:v1.2.0 وejaz-web:v1.2.0) قبل وضع الصيانة،
#   فلا يتوقف الموقع إلا أثناء الترحيل وتبديل الحاويات. والصورة السابقة تبقى للتراجع (docs/cutover.md).
# - الترحيل والبذر بالصورة الجديدة قبل تبديل الحاويات.
# - لا cache:clear ولا optimize:clear (docs/security.md). وذاكرة الإعدادات تُبنى عند بدء كل حاوية.
# - عند أي خطأ يتوقف: قبل وضع الصيانة يبقى الإصدار القديم يعمل، وبعده يبقى الموقع في الصيانة للمراجعة.
set -euo pipefail

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

TAG="${1:-}"
[ -n "$TAG" ] || fail "حدّد الوسم: bash scripts/deploy-compose.sh v1.2.0"
# الوسم يدخل .env وأوامر git وأسماء الصور، فبحروف الوسوم المعتادة وحدها
printf '%s' "$TAG" | grep -Ex '[A-Za-z0-9._-]+' >/dev/null || fail "وسم غير صالح: $TAG"

cd "$(dirname "$0")/.."
export EJAZ_TAG="$TAG"
DC=(docker compose --env-file .env -f deploy/compose.production.yaml)

say "١/٩ فحوص ما قبل النشر"
[ -f .env ] || fail "لا ملف .env في $(pwd)"
grep -Eq '^APP_ENV=(production|staging)$' .env || fail "APP_ENV ليس production ولا staging"
grep -Eq '^APP_DEBUG=false$' .env || fail "APP_DEBUG يجب أن يكون false"
grep -Eq '^APP_URL=https://' .env || fail "APP_URL يجب أن يبدأ بـ https://"
grep -Eq '^SESSION_SECURE_COOKIE=true$' .env || fail "SESSION_SECURE_COOKIE يجب أن يكون true (والتجريبية أيضاً، فافتراضه آمن في production وحده)"
grep -Eq '^APP_KEY="?base64:.+' .env || fail "APP_KEY غير مضبوط: key:generate --show مرة، ثم القيمة في .env ومدير كلمات المرور (docs/deploy.md «ثانياً»)"
# قيمة مثال باقية بين <...> في سطر غير معلّق (مثل MAIL_HOST=<مزود البريد>): تُستبدل أو تُفرغ قبل النشر
! grep -Eq '^[A-Z0-9_]+="?<[^>]*>"?$' .env || fail "في .env قيمة مثال بين <...> لم تُستبدل: $(grep -E '^[A-Z0-9_]+="?<[^>]*>"?$' .env | cut -d= -f1 | tr '\n' ' ')"
grep -Eq '^TRUSTED_PROXIES=.+' .env || echo "تنبيه: TRUSTED_PROXIES فارغ. خلف موزع أحمال تتعطل حدود الشبكات (docs/security.md)"
grep -Eq '^TRUSTED_PROXIES=.*\*' .env && echo "تنبيه: TRUSTED_PROXIES=* يصدّق رأس X-Forwarded-For من أي مصدر، فتُزوَّر حدود الشبكات. لا * إلا والخادم معزول خلف موزع يستبدل الرأس، والأفضل عناوين الموزع بعينها (docs/security.md)"
grep -Eq '^SMS_DRIVER=log$' .env && echo "تنبيه: SMS_DRIVER=log. رموز التحقق لا تصل حتى يُضبط المزود من «قنوات التواصل» في اللوحة (docs/guides/messaging.md)"
{ grep -Eq '^BOT_CHALLENGE_DRIVER=turnstile$' .env && grep -Eq '^TURNSTILE_SITE_KEY=.+' .env && grep -Eq '^TURNSTILE_SECRET_KEY=.+' .env; } \
  || echo "تنبيه: Turnstile غير مضبوط بمفتاحيه (BOT_CHALLENGE_DRIVER=turnstile). الضيف في استبيان مفتوح للضيوف يُرفض حتى يُضبط (docs/decisions.md)"
[ -z "$(git status --porcelain --untracked-files=no)" ] || fail "في المجلد تعديلات غير محفوظة. لا تُعدَّل الشيفرة على الخادم"
git fetch --tags --force --quiet origin
git rev-parse -q --verify "refs/tags/$TAG" >/dev/null || fail "الوسم $TAG غير موجود"

say "٢/٩ سحب الوسم $TAG"
git checkout --quiet --force "refs/tags/$TAG"

say "٣/٩ بناء الصور (والموقع يعمل)"
# وصورة النسخ (ejaz-backup، ملف التعريف tools) بالوسم نفسه، فلا يبنيها مؤقت النسخ ليلاً ولا تبقى على وسم قديم
"${DC[@]}" build --pull app web backup

say "٤/٩ القاعدة والذاكرة (ملف التعريف bundled إن كانتا على الخادم)"
"${DC[@]}" up -d --wait pgsql redis 2>/dev/null || echo "لا خدمات مضمّنة (قاعدة وذاكرة مُدارتان؟) — يُكمل"

RUNNING="$("${DC[@]}" ps --status running --services 2>/dev/null | grep -x app || true)"
say "٥/٩ وضع الصيانة"
if [ -n "$RUNNING" ]; then
  "${DC[@]}" exec -T app php artisan down --retry=60 --refresh=15
else
  echo "أول نشر: لا حاوية تعمل بعد"
fi
trap 'printf "\n\033[1;31m✗ فشل النشر عند السطر %s. الموقع باقٍ في وضع الصيانة — راجع docs/deploy.md\033[0m\n" "$LINENO" >&2' ERR

say "٦/٩ ترحيل القاعدة والأدوار والصلاحيات بالصورة الجديدة"
"${DC[@]}" run --rm --no-deps app php artisan migrate --force
"${DC[@]}" run --rm --no-deps app php artisan db:seed --class=AccessSeeder --force
# القائمة الرئيسية بسبعة عناصر (MainMenuSevenSeeder، النسخة 0.15): مرة واحدة على الخادم بعلامة في .deploy-state، فلا يعيد كل
# نشر ترتيب ما رتّبه المحرر في «القائمة» ولا يخفي ما أظهره بعدها (مراجعة المشرف على #111، المنخفضة 5)
if [ ! -f .deploy-state/main-menu-seven ]; then
  "${DC[@]}" run --rm --no-deps app php artisan db:seed --class=MainMenuSevenSeeder --force
  mkdir -p .deploy-state && touch .deploy-state/main-menu-seven
fi

say "٧/٩ تبديل الحاويات (Horizon ينهي مهامه الجارية أولاً)"
# الوسم في .env، فتبقى الحاويات على هذا الإصدار بعد إعادة تشغيل الخادم (ejaz-stack.service) لا على latest
if grep -q '^EJAZ_TAG=' .env; then
  sed -i "s/^EJAZ_TAG=.*/EJAZ_TAG=$TAG/" .env
else
  printf '\nEJAZ_TAG=%s\n' "$TAG" >> .env
fi
"${DC[@]}" up -d --wait --remove-orphans app horizon scheduler web

say "٨/٩ ذاكرة المحتوى العام ورفع الصيانة"
"${DC[@]}" exec -T app php artisan ejaz:refresh-content
# فهرس المساعد الذكي من المحتوى المنشور: القوالب والمسارات الجديدة تغيّر روابط المقاطع (docs/guides/ai-assistant.md)
"${DC[@]}" exec -T app php artisan ejaz:assistant-index
"${DC[@]}" exec -T app php artisan up
trap - ERR

say "٩/٩ فحص ما بعد النشر"
APP_URL="$(grep -E '^APP_URL=' .env | cut -d= -f2-)"
# إلى هذا الخادم لا إلى ما في DNS: قبل نقل النطاق يشير ejaz.sa إلى الموقع القديم
HOST="$(printf '%s' "$APP_URL" | sed -E 's#^https://([^/:]+).*#\1#')"
curl -fsS -o /dev/null --resolve "${HOST}:443:127.0.0.1" "$APP_URL/up" && echo "✓ $APP_URL/up" || echo "✗ $APP_URL/up لا يرد 200 من هذا الخادم — راجع: ${DC[*]} logs app web"
"${DC[@]}" exec -T app php artisan ejaz:health || echo "تنبيه: فشل فحص من فحوص الصحة أعلاه. نبض المجدول يحتاج دقيقة بعد أول نشر."

printf '\n\033[1;32m✓ نُشر %s. التراجع: EJAZ_TAG=<الوسم السابق> في .env ثم %s up -d app horizon scheduler web\033[0m\n' "$TAG" "${DC[*]}"
