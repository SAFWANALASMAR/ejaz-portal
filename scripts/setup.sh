#!/usr/bin/env bash
# تجهيز بيئة التطوير لأول مرة — بوابة جمعية إعجاز
# التشغيل من جذر المشروع:  bash scripts/setup.sh
# على ويندوز: يُشغَّل من Git Bash.
set -euo pipefail

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1"; exit 1; }

cd "$(dirname "$0")/.."

say "١/٧ التحقق من الأدوات"
command -v docker >/dev/null 2>&1 || fail "Docker غير مثبت. ثبّت Docker Desktop ثم أعد التشغيل."
docker info >/dev/null 2>&1      || fail "Docker مثبت لكنه لا يعمل. افتح Docker Desktop وانتظر حتى يصبح جاهزاً."
docker compose version >/dev/null 2>&1 || fail "docker compose غير متاح. حدّث Docker Desktop."
command -v git >/dev/null 2>&1    || fail "Git غير مثبت."

say "٢/٧ ملف الإعدادات .env"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "أُنشئ .env من .env.example"
else
  echo ".env موجود مسبقاً — لم يُعدَّل"
fi

# على ماك ولينكس: نطابق رقم المستخدم داخل الحاوية مع مستخدم الجهاز
case "$(uname -s)" in
  Darwin|Linux)
    sed -i.bak "s/^UID=.*/UID=$(id -u)/; s/^GID=.*/GID=$(id -g)/" .env && rm -f .env.bak
    ;;
esac

say "٣/٧ بناء صورة PHP (قد يأخذ دقائق في المرة الأولى)"
docker compose build app

say "٤/٧ تشغيل قاعدة البيانات والذاكرة المؤقتة والبريد"
docker compose up -d --wait pgsql redis mailpit

say "٥/٧ تثبيت مكتبات PHP"
docker compose run --rm --no-deps app composer install --no-interaction

if ! grep -q '^APP_KEY=base64' .env; then
  docker compose run --rm --no-deps app php artisan key:generate --ansi
fi

say "٦/٧ إنشاء جداول قاعدة البيانات"
docker compose run --rm app php artisan migrate --force

say "٧/٧ تشغيل كل الخدمات"
docker compose up -d

cat <<'EOF'

✓ البيئة جاهزة

  الموقع:            http://localhost:8000
  صندوق البريد:       http://localhost:8025

الخطوة التالية في أول مرة فقط: افتح كلود كود في مجلد المشروع واكتب:
  /bootstrap
EOF
