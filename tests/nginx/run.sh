#!/usr/bin/env bash
# فحص إعداد nginx للإنتاج (deploy/docker/nginx.conf) على صورة nginx بعينها، كما تنسخه مرحلة web في deploy/docker/Dockerfile:
#   ١. nginx -t بلا تحذير ولا خطأ.
#   ٢. الرؤوس لطلبات ثابتة (تحويل http، والصفحة بلا تطبيق خلفها، وملف ثابت، وwww، وملف مخفي): تُطبع مرتبة بلا
#      المتغيّر (Date وETag وما إليهما)، فتُقارن بين صورتين بـ diff، ويُفحص أن رؤوس الحماية وCSP حاضرة.
#
#   bash tests/nginx/run.sh nginx:1.30-alpine > /tmp/new.txt
#   bash tests/nginx/run.sh nginx:1.27-alpine > /tmp/old.txt && diff /tmp/old.txt /tmp/new.txt
#
# يحتاج docker وopenssl وcurl. لا يمس غير حاوية مؤقتة باسم عشوائي ومجلد مؤقت يُحذفان في النهاية.
set -euo pipefail

IMAGE="${1:-}"
[ -n "$IMAGE" ] || { echo "حدّد الصورة: bash tests/nginx/run.sh nginx:1.30-alpine" >&2; exit 2; }

cd "$(dirname "$0")/../.."
work="$(mktemp -d)"
name="ejaz-nginx-check-$$"
cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; rm -rf "$work"; }
trap cleanup EXIT

# شهادة ذاتية للنطاق التجريبي، وملف ثابت واحد في public
mkdir -p "$work/tls" "$work/public/build"
# openssl في Git Bash على ويندوز لا يفهم مسار /c/...، فيُمرَّر له بصيغة ويندوز (ولا أثر لهذا على لينكس)
native() { if command -v cygpath >/dev/null; then cygpath -m "$1"; else printf '%s' "$1"; fi; }
# ومثله /dev/null لـ curl على ويندوز
if command -v cygpath >/dev/null; then DEVNULL=NUL; else DEVNULL=/dev/null; fi
MSYS2_ARG_CONV_EXCL='*' openssl req -x509 -nodes -newkey rsa:2048 -days 1 -subj "/CN=ejaz.test" \
  -keyout "$(native "$work/tls/privkey.pem")" -out "$(native "$work/tls/fullchain.pem")" >/dev/null 2>&1
echo 'console.log(1)' > "$work/public/build/app.js"
chmod -R a+rX "$work"

# خيارات docker قبل -- والأمر بعدها: run --rm -- nginx -t
run() {
  local opts=()
  while [ "$1" != "--" ]; do opts+=("$1"); shift; done
  shift
  # وفي Git Bash يحوّل التحويل التلقائي طرف الحاوية من -v إلى مسار ويندوز، فيُطفأ وتُمرَّر المصادر بصيغة ويندوز (ولا أثر لهذا على لينكس)
  MSYS_NO_PATHCONV=1 docker run "${opts[@]}" \
    -e SITE_HOST=ejaz.test \
    --add-host app:127.0.0.1 \
    -v "$(native "$PWD")/deploy/docker/nginx.conf:/etc/nginx/templates/default.conf.template:ro" \
    -v "$(native "$work")/tls:/etc/nginx/tls:ro" \
    -v "$(native "$work")/public:/var/www/html/public:ro" \
    "$IMAGE" "$@"
}

echo "== $(run --rm -- nginx -v 2>&1 | tail -1)"

# ١. nginx -t بعد توليد الإعداد من القالب (نقطة الدخول الرسمية تولّده متى كان الأمر nginx)
test_output="$(run --rm -- nginx -t 2>&1)"
if printf '%s\n' "$test_output" | grep -E '\[(warn|emerg|alert|crit|error)\]' >&2; then
  echo "✗ nginx -t: تحذير أو خطأ" >&2
  exit 1
fi
printf '%s\n' "$test_output" | grep -q 'test is successful' || { printf '%s\n' "$test_output" >&2; echo "✗ nginx -t لم ينجح" >&2; exit 1; }
echo "== nginx -t: ناجح بلا تحذير"

# ٢. الرؤوس
run -d --name "$name" -p 127.0.0.1::80 -p 127.0.0.1::443 -- >/dev/null
http_port="$(docker port "$name" 80/tcp | head -1 | sed 's/.*://')"
https_port="$(docker port "$name" 443/tcp | head -1 | sed 's/.*://')"
for _ in $(seq 1 30); do
  curl -s -o "$DEVNULL" "http://127.0.0.1:$http_port/" && break
  sleep 1
done

headers() {
  curl -sk -o "$DEVNULL" -D - "$@" | tr -d '\r' \
    | grep -viE '^(date|expires|etag|last-modified|content-length|server|connection|keep-alive|accept-ranges):' \
    | sed '/^$/d' | sort
}

resolve=(--resolve "ejaz.test:$https_port:127.0.0.1" --resolve "www.ejaz.test:$https_port:127.0.0.1")
echo "== http://ejaz.test/"; headers -H 'Host: ejaz.test' "http://127.0.0.1:$http_port/"
echo "== https://ejaz.test/ (بلا تطبيق خلفه)"; headers "${resolve[@]}" "https://ejaz.test:$https_port/"
echo "== https://ejaz.test/build/app.js"; headers "${resolve[@]}" "https://ejaz.test:$https_port/build/app.js"
echo "== https://www.ejaz.test/"; headers "${resolve[@]}" "https://www.ejaz.test:$https_port/"
echo "== https://ejaz.test/.env"; headers "${resolve[@]}" "https://ejaz.test:$https_port/.env"

# رؤوس الحماية وCSP من nginx على الصفحة بلا تطبيق خلفها (ما لا يمر بـ SecurityHeaders — NginxFallbackHeadersTest)
page="$(headers "${resolve[@]}" "https://ejaz.test:$https_port/")"
for header in x-frame-options x-content-type-options referrer-policy permissions-policy cross-origin-opener-policy \
  cross-origin-resource-policy strict-transport-security content-security-policy; do
  printf '%s\n' "$page" | grep -qi "^$header:" || { echo "✗ الرأس $header غائب" >&2; exit 1; }
done
echo "== رؤوس الحماية وCSP حاضرة"
