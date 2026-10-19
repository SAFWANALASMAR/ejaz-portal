#!/usr/bin/env bash
# فحص الخادم بعد التجهيز أو النشر — بوابة جمعية إعجاز (docs/deploy/readiness.md)
#
# من أي جهاز فيه curl (فحوص الرابط وحدها):
#   bash scripts/verify-server.sh https://staging.ejaz.sa --staging
#   bash scripts/verify-server.sh https://ejaz.sa
# وعلى الخادم من جذر المشروع، ومعه فحوص الحاويات والحدود والمؤقتات:
#   bash scripts/verify-server.sh https://ejaz.sa --server
#
# --staging: يُتوقع منع الفهرسة (Disallow: /). وبدونه يُتوقع سطر Sitemap (الإنتاج بعد نقل النطاق).
# --server: يفحص أيضاً ejaz:health، وحدود الرفع في PHP وnginx، والمجدول وHorizon، ومؤقتي النسخ والصحة.
# لا يغيّر شيئاً، ويطبع ✓ أو ✗ لكل فحص (أو ! لتحذير لا يُحسب فشلاً)، ويخرج برمز 1 إن فشل أي فحص.
#
# لا grep -q بعد أنبوب: يخرج عند أول تطابق فيأخذ curl إشارة SIGPIPE، ومع pipefail يُحسب الفحص فاشلاً.
set -uo pipefail

URL="${1:-}"
URL="${URL%/}"
# الرابط يدخل أوامر الفحص، فبحروف الرابط الآمنة وحدها
if ! printf '%s' "$URL" | grep -Ex 'https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?' >/dev/null; then
  echo "حدّد رابطاً صحيحاً بلا مسار: bash scripts/verify-server.sh https://ejaz.sa [--staging] [--server]" >&2
  exit 2
fi
shift
STAGING=0
SERVER=0
for arg in "$@"; do
  case "$arg" in
    --staging) STAGING=1 ;;
    --server) SERVER=1 ;;
    *) echo "خيار غير معروف: $arg" >&2; exit 2 ;;
  esac
done

cd "$(dirname "$0")/.."
FAILED=0
ok()  { printf '\033[1;32m✓\033[0m %s\n' "$1"; }
bad() { printf '\033[1;31m✗\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
# تحذير يُطبع ولا يُحسب فشلاً
warn() { printf '\033[1;33m!\033[0m %s\n' "$1"; }
# الفحص دالة تُمرَّر باسمها، لا نص يُنفَّذ
check() { if "${@:2}" >/dev/null 2>&1; then ok "$1"; else bad "$1"; fi; }
has()   { printf '%s' "$1" | grep -E "$2" >/dev/null; }
# قيمة من .env، بعلامات اقتباس أو بدونها
envval() { grep -E "^$1=" .env 2>/dev/null | tail -n1 | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
# ترميز المسار العربي بايتاً بايتاً (UTF-8)، مع إبقاء / و? و= و& كما هي
urlencode() {
  local LC_ALL=C s="$1" out="" c i
  for ((i = 0; i < ${#s}; i++)); do
    c="${s:i:1}"
    case "$c" in
      [a-zA-Z0-9._~/?=\&-]) out+="$c" ;;
      *) out+="$(printf '%%%02X' "'$c")" ;;
    esac
  done
  printf '%s' "$out"
}
status_of() { curl -s -o /dev/null --max-time 20 -w '%{http_code}' "$1"; }

echo "== الرابط: $URL"
case "$URL" in https://*) ok "الرابط https" ;; *) bad "الرابط ليس https" ;; esac

HEADERS="$(curl -sS -o /dev/null -D - --max-time 20 "$URL/" | tr -d '\r' || true)"
STATUS="$(printf '%s\n' "$HEADERS" | head -n1 | awk '{print $2}')"
[ "$STATUS" = "200" ] && ok "الرئيسية 200" || bad "الرئيسية ترد ${STATUS:-لا شيء}"
CSP="$(printf '%s\n' "$HEADERS" | grep -i '^content-security-policy:' || true)"
[ -n "$CSP" ] && ok "رأس CSP موجود" || bad "لا رأس CSP"
has "$CSP" "img-src[^;]*( https:| \*)( |;|$)" && bad "img-src يسمح بكل نطاق (https: أو *)" || ok "img-src بنطاقات صريحة"
has "$CSP" "connect-src[^;]*( https:| \*)( |;|$)" && bad "connect-src يسمح بكل نطاق" || ok "connect-src بنطاقات صريحة"
printf '%s\n' "$HEADERS" | grep -i '^strict-transport-security:' >/dev/null && ok "HSTS" || bad "لا HSTS"
printf '%s\n' "$HEADERS" | grep -i '^x-frame-options: *deny' >/dev/null && ok "X-Frame-Options: DENY" || bad "X-Frame-Options ليس DENY"
printf '%s\n' "$HEADERS" | grep -i '^x-content-type-options: *nosniff' >/dev/null && ok "nosniff" || bad "لا nosniff"
# رأس التطبيق لا يُكرَّر من nginx (deploy/nginx: يُضاف الرأس متى غاب عن رد التطبيق وحده)
[ "$(printf '%s\n' "$HEADERS" | grep -ciE '^(x-frame-options|content-security-policy):')" = 2 ] && ok "X-Frame-Options وCSP مرة واحدة" || bad "X-Frame-Options أو CSP مكرر أو غائب"
# رد قبل وسائط المسار (405) برؤوس الحماية من nginx
METHOD_HEADERS="$(curl -sS -o /dev/null -D - --max-time 20 -X DELETE "$URL/" | tr -d '\r' || true)"
printf '%s\n' "$METHOD_HEADERS" | grep -E '^HTTP/[0-9.]+ 405' >/dev/null && printf '%s\n' "$METHOD_HEADERS" | grep -i '^x-frame-options: *deny' >/dev/null && printf '%s\n' "$METHOD_HEADERS" | grep -i '^x-content-type-options: *nosniff' >/dev/null \
  && ok "رد 405 برؤوس الحماية" || bad "رد 405 بلا رؤوس الحماية (add_header في nginx؟)"

[ "$(status_of "$URL/up")" = 200 ] && ok "/up يرد 200" || bad "/up لا يرد 200"
SITEMAP="$(curl -s --max-time 30 "$URL/sitemap.xml" || true)"
has "$SITEMAP" '<urlset' && ok "sitemap.xml بصيغة XML" || bad "sitemap.xml لا يرد خريطة"
ROBOTS="$(curl -s --max-time 20 "$URL/robots.txt" | tr -d '\r' || true)"
if [ "$STAGING" = 1 ]; then
  printf '%s\n' "$ROBOTS" | grep -Fx 'Disallow: /' >/dev/null && ok "robots يمنع الفهرسة (تجريبية)" || bad "robots لا يمنع الفهرسة في التجريبية"
else
  printf '%s\n' "$ROBOTS" | grep -Fx "Sitemap: $URL/sitemap.xml" >/dev/null && ok "robots يذكر خريطة الموقع (إنتاج)" || bad "robots بلا سطر Sitemap (SEO_INDEXABLE؟)"
fi
[ "$(status_of "$URL/favicon.ico")" = 200 ] && ok "favicon.ico موجودة" || bad "لا favicon.ico"
NOT_FOUND="$(curl -s --max-time 20 "$URL/no-such-page-$RANDOM" || true)"
has "$NOT_FOUND" 'lang="ar"' && ok "صفحة 404 عربية" || bad "صفحة 404 ليست العربية"

echo "== جدول تحويلات الموقع السابق (docs/legacy-import/redirects.csv)"
if [ -f docs/legacy-import/redirects.csv ]; then
  TOTAL=0
  WRONG=0
  while IFS=, read -r source target kind; do
    [ "$source" = "source" ] && continue
    TOTAL=$((TOTAL + 1))
    result="$(curl -s -o /dev/null --max-time 20 -w '%{http_code} %{redirect_url}' "$URL$(urlencode "$source")")"
    if [ "$kind" = "served" ]; then expected="200 "; else expected="301 $URL$target"; fi
    if [ "$result" != "$expected" ]; then
      WRONG=$((WRONG + 1))
      [ "$WRONG" -le 10 ] && echo "   $source ← $result (المتوقع $expected)"
    fi
  done < docs/legacy-import/redirects.csv
  [ "$WRONG" = 0 ] && ok "التحويلات كلها ($TOTAL)" || bad "$WRONG من $TOTAL تحويلاً لا تطابق"
else
  bad "لا ملف docs/legacy-import/redirects.csv"
fi

if [ "$SERVER" = 1 ]; then
  echo "== الخادم"
  DC=(docker compose --env-file .env -f deploy/compose.production.yaml)
  running() { "${DC[@]}" ps --status running --services 2>/dev/null | grep -x "$1" >/dev/null; }
  check "ejaz:health (القاعدة والذاكرة والطابور والتخزين الخاص والمجدول)" "${DC[@]}" exec -T app php artisan ejaz:health
  LIMITS="$("${DC[@]}" exec -T app php -r 'echo ini_get("upload_max_filesize")," ",ini_get("post_max_size")," ",ini_get("memory_limit");' 2>/dev/null || true)"
  [ "$LIMITS" = "32M 32M 512M" ] && ok "حدود PHP: $LIMITS" || bad "حدود PHP: ${LIMITS:-تعذر القراءة} (المتوقع 32M 32M 512M)"
  NGINX="$("${DC[@]}" exec -T web nginx -T 2>/dev/null || true)"
  has "$NGINX" 'client_max_body_size 32M' && ok "nginx: client_max_body_size 32M" || bad "nginx: client_max_body_size ليس 32M"
  check "المجدول يعمل (حاوية scheduler)" running scheduler
  check "Horizon يعمل" running horizon
  SUPERVISORS="$("${DC[@]}" exec -T app php artisan horizon:supervisors 2>/dev/null || true)"
  has "$SUPERVISORS" 'supervisor-videos' && ok "عمال طابور المقاطع (supervisor-videos)" || bad "لا عمال لطابور المقاطع في Horizon (APP_ENV؟)"
  SCHEDULE="$("${DC[@]}" exec -T app php artisan schedule:list 2>/dev/null || true)"
  has "$SCHEDULE" 'ejaz:refresh-stats' && ok "قائمة المهام المجدولة" || bad "قائمة المهام المجدولة فارغة"
  check "مؤقت النسخ الاحتياطي مفعّل" systemctl is-active --quiet ejaz-backup.timer
  check "مؤقت فحص الصحة مفعّل" systemctl is-active --quiet ejaz-health.timer
  # Result يكون success قبل أي تشغيل، فيُشترط أن الخدمة انتهت مرة على الأقل
  BACKUP_RESULT="$(systemctl show -p Result --value ejaz-backup.service 2>/dev/null || true)"
  BACKUP_RAN="$(systemctl show -p ExecMainExitTimestamp --value ejaz-backup.service 2>/dev/null || true)"
  # 75 من backup.sh: القاعدة رُفعت والملفات انقطعت بالمدة، فيُكمَل في التشغيل التالي (تحذير لا فشل)
  BACKUP_STATUS="$(systemctl show -p ExecMainStatus --value ejaz-backup.service 2>/dev/null || true)"
  if [ "$BACKUP_RESULT" = success ] && [ -n "$BACKUP_RAN" ]; then
    ok "آخر نسخة احتياطية نجحت ($BACKUP_RAN)"
  elif [ "$BACKUP_STATUS" = 75 ] && [ -n "$BACKUP_RAN" ]; then
    warn "القاعدة منسوخة والملفات جزئية ($BACKUP_RAN) — scripts/restore.md «نسخ ملفات جزئي»"
  elif [ -n "$BACKUP_RAN" ]; then
    bad "آخر نسخة احتياطية فشلت (${BACKUP_RESULT:-؟}، رمز ${BACKUP_STATUS:-؟}) — journalctl -u ejaz-backup.service"
  else
    bad "لا نسخة احتياطية ناجحة بعد (systemctl start ejaz-backup.service)"
  fi
  if [ "$(envval COMPETITION_VIDEO_UPLOAD)" = s3 ]; then
    ORIGIN="$(envval CSP_CONNECT_SRC | cut -d, -f1)"
    ORIGIN="${ORIGIN%/}"
    [ -n "$ORIGIN" ] && printf '%s' "$CSP" | grep -F " $ORIGIN" >/dev/null \
      && ok "connect-src في الرأس يحوي نطاق التخزين ($ORIGIN)" \
      || bad "connect-src في الرأس لا يحوي CSP_CONNECT_SRC (${ORIGIN:-فارغ})، فيتعطل الرفع المباشر"
  fi
  # استبيان الرضا للضيوف بـ Turnstile (decisions.md — 19 أكتوبر 2026)
  [ "$(envval BOT_CHALLENGE_DRIVER)" = turnstile ] && [ -n "$(envval TURNSTILE_SITE_KEY)" ] && [ -n "$(envval TURNSTILE_SECRET_KEY)" ] \
    && ok "Turnstile بمفتاحيه للاستبيان" || bad "Turnstile غير مضبوط (BOT_CHALLENGE_DRIVER=turnstile والمفتاحان)، فيُرفض ضيف الاستبيان"
  # ملفات الحوكمة والمكتبة برابط تخزين موقّع: الصوت بـ media-src، وعارض PDF بـ frame-src، في رأس كل صفحة لأن التنقّل
  # السريع يُبقي سياسة أول صفحة. مطابقة حرفية للأصل داخل التوجيه، لا تعبير نمطي
  if [ "$(envval FILESYSTEM_PRIVATE_DISK)" = s3 ]; then
    for directive in media-src:CSP_MEDIA_SRC frame-src:CSP_FRAME_SRC; do
      ORIGIN="$(envval "${directive#*:}" | cut -d, -f1)"
      ORIGIN="${ORIGIN%/}"
      SOURCES="$(printf '%s' "${CSP#*: }" | tr ';' '\n' | sed 's/^ *//' | grep "^${directive%%:*} " || true)"
      case " $SOURCES " in
        *" $ORIGIN "*) [ -n "$ORIGIN" ] && ok "${directive%%:*} في الرأس يحوي نطاق التخزين ($ORIGIN)" || bad "${directive#*:} فارغ، فلا تعمل ملفات الحوكمة والمكتبة" ;;
        *) bad "${directive%%:*} في الرأس لا يحوي ${directive#*:} (${ORIGIN:-فارغ})، فلا تعمل ملفات الحوكمة والمكتبة" ;;
      esac
    done
  fi
  if [ "$STAGING" = 1 ]; then
    [ "$(envval SEO_INDEXABLE)" = false ] && ok "SEO_INDEXABLE=false في التجريبية" || bad "SEO_INDEXABLE ليس false في التجريبية"
  else
    [ "$(envval SEO_INDEXABLE)" = true ] && ok "SEO_INDEXABLE=true في الإنتاج" || bad "SEO_INDEXABLE ليس true في الإنتاج"
  fi
fi

echo
if [ "$FAILED" = 0 ]; then
  printf '\033[1;32mكل الفحوص سليمة.\033[0m\n'
  exit 0
fi
printf '\033[1;31mفشل %s من الفحوص.\033[0m\n' "$FAILED"
exit 1
