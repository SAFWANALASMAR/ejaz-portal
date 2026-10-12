#!/usr/bin/env bash
# فحص الخادم بعد التجهيز أو النشر — بوابة جمعية إعجاز (docs/deploy/readiness.md)
#
# من أي جهاز فيه curl (فحوص الرابط وحدها):
#   bash scripts/verify-server.sh https://staging.ejaz.example --staging
#   bash scripts/verify-server.sh https://ejaz.sa
# وعلى الخادم من جذر المشروع، ومعه فحوص الحاويات والحدود والمؤقتات:
#   bash scripts/verify-server.sh https://ejaz.sa --server
#
# --staging: يُتوقع منع الفهرسة (Disallow: /). وبدونه يُتوقع سطر Sitemap (الإنتاج بعد نقل النطاق).
# --server: يفحص أيضاً ejaz:health، وحدود الرفع في PHP وnginx، والمجدول، ومؤقتي النسخ والصحة.
# لا يغيّر شيئاً، ويطبع ✓ أو ✗ لكل فحص، ويخرج برمز 1 إن فشل أي فحص.
set -uo pipefail

URL="${1:-}"
[ -n "$URL" ] || { echo "حدّد الرابط: bash scripts/verify-server.sh https://ejaz.sa [--staging] [--server]" >&2; exit 2; }
URL="${URL%/}"
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
ok()   { printf '\033[1;32m✓\033[0m %s\n' "$1"; }
bad()  { printf '\033[1;31m✗\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
check() { if eval "$2" >/dev/null 2>&1; then ok "$1"; else bad "$1"; fi; }
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

echo "== الرابط: $URL"
case "$URL" in https://*) ok "الرابط https" ;; *) bad "الرابط ليس https" ;; esac

HEADERS="$(curl -sS -o /dev/null -D - --max-time 20 "$URL/" || true)"
STATUS="$(printf '%s' "$HEADERS" | head -n1 | awk '{print $2}')"
[ "$STATUS" = "200" ] && ok "الرئيسية 200" || bad "الرئيسية ترد ${STATUS:-لا شيء}"
CSP="$(printf '%s' "$HEADERS" | grep -i '^content-security-policy:' | tr -d '\r')"
[ -n "$CSP" ] && ok "رأس CSP موجود" || bad "لا رأس CSP"
printf '%s' "$CSP" | grep -Eiq "img-src[^;]*( https:| \*)( |;|$)" && bad "img-src يسمح بكل نطاق (https: أو *)" || ok "img-src بنطاقات صريحة"
printf '%s' "$CSP" | grep -Eiq "connect-src[^;]*( https:| \*)( |;|$)" && bad "connect-src يسمح بكل نطاق" || ok "connect-src بنطاقات صريحة"
printf '%s' "$HEADERS" | grep -iq '^strict-transport-security:' && ok "HSTS" || bad "لا HSTS"
printf '%s' "$HEADERS" | grep -iq '^x-frame-options: *deny' && ok "X-Frame-Options: DENY" || bad "X-Frame-Options ليس DENY"
printf '%s' "$HEADERS" | grep -iq '^x-content-type-options: *nosniff' && ok "nosniff" || bad "لا nosniff"

check "/up يرد 200" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 '$URL/up')\" = 200 ]"
check "sitemap.xml يرد 200 بصيغة XML" "curl -sf --max-time 20 '$URL/sitemap.xml' | grep -q '<urlset'"
ROBOTS="$(curl -s --max-time 20 "$URL/robots.txt" || true)"
if [ "$STAGING" = 1 ]; then
  printf '%s' "$ROBOTS" | grep -qx 'Disallow: /' && ok "robots يمنع الفهرسة (تجريبية)" || bad "robots لا يمنع الفهرسة في التجريبية"
else
  printf '%s' "$ROBOTS" | grep -q "^Sitemap: $URL/sitemap.xml" && ok "robots يذكر خريطة الموقع (إنتاج)" || bad "robots بلا سطر Sitemap (SEO_INDEXABLE؟)"
fi
check "favicon.ico موجودة" "curl -sf --max-time 20 -o /dev/null '$URL/favicon.ico'"
check "صفحة 404 عربية" "curl -s --max-time 20 '$URL/no-such-page-$RANDOM' | grep -q 'lang=\"ar\"'"

echo "== جدول تحويلات الموقع السابق (docs/legacy-import/redirects.csv)"
if [ -f docs/legacy-import/redirects.csv ]; then
  TOTAL=0
  WRONG=0
  while IFS=, read -r source target kind; do
    [ "$source" = "source" ] && continue
    TOTAL=$((TOTAL + 1))
    encoded="$(urlencode "$source")"
    result="$(curl -s -o /dev/null --max-time 20 -w '%{http_code} %{redirect_url}' "$URL$encoded")"
    if [ "$kind" = "served" ]; then
      expected="200 "
    else
      expected="301 $URL$target"
    fi
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
  check "ejaz:health (القاعدة والذاكرة والطابور والتخزين الخاص والمجدول)" "${DC[*]} exec -T app php artisan ejaz:health"
  LIMITS="$("${DC[@]}" exec -T app php -r 'echo ini_get("upload_max_filesize")," ",ini_get("post_max_size")," ",ini_get("memory_limit");' 2>/dev/null || true)"
  [ "$LIMITS" = "32M 32M 512M" ] && ok "حدود PHP: $LIMITS" || bad "حدود PHP: ${LIMITS:-تعذر القراءة} (المتوقع 32M 32M 512M)"
  check "nginx: client_max_body_size 32M" "${DC[*]} exec -T web nginx -T 2>/dev/null | grep -q 'client_max_body_size 32M'"
  check "المجدول يعمل (حاوية scheduler)" "${DC[*]} ps --status running --services | grep -qx scheduler"
  check "Horizon يعمل" "${DC[*]} ps --status running --services | grep -qx horizon"
  check "قائمة المهام المجدولة" "${DC[*]} exec -T app php artisan schedule:list | grep -q 'ejaz:refresh-stats'"
  check "مؤقت النسخ الاحتياطي مفعّل" "systemctl is-active --quiet ejaz-backup.timer"
  check "مؤقت فحص الصحة مفعّل" "systemctl is-active --quiet ejaz-health.timer"
  check "آخر نسخة احتياطية نجحت" "[ \"\$(systemctl show -p Result --value ejaz-backup.service)\" = success ]"
  if grep -Eq '^COMPETITION_VIDEO_UPLOAD=s3$' .env; then
    check "CSP_CONNECT_SRC مضبوط للرفع المباشر" "grep -Eq '^CSP_CONNECT_SRC=https://.+' .env"
  fi
  if [ "$STAGING" = 1 ]; then
    check "SEO_INDEXABLE=false في التجريبية" "grep -Eq '^SEO_INDEXABLE=false$' .env"
  else
    check "SEO_INDEXABLE=true في الإنتاج" "grep -Eq '^SEO_INDEXABLE=true$' .env"
  fi
fi

echo
if [ "$FAILED" = 0 ]; then
  printf '\033[1;32mكل الفحوص سليمة.\033[0m\n'
  exit 0
fi
printf '\033[1;31mفشل %s من الفحوص.\033[0m\n' "$FAILED"
exit 1
