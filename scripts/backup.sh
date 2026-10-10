#!/usr/bin/env bash
# النسخ الاحتياطي اليومي — بوابة جمعية إعجاز (docs/security.md «النسخ الاحتياطي»، والاستعادة في scripts/restore.md)
#
# ما يُنسخ:
#   ١. قاعدة البيانات: pg_dump بالصيغة المخصصة (مضغوطة)، مشفّرة بمفتاح GPG عام، فلا يفكها خادم مخترق.
#   ٢. الملفات: القرص الخاص والقرص العام، متى كانا محليين، بمزامنة إلى حاوية متوافقة مع S3 داخل المملكة.
#      وإن كان القرص الخاص نفسه على S3 في الإنتاج، فحمايته بإصدارات الحاوية (versioning) ونسخها إلى حاوية
#      ثانية في منطقة أخرى داخل المملكة (docs/deploy.md)، ويُضبط BACKUP_SYNC_FILES=false.
#   ٣. مدة الاحتفاظ: تُحذف نسخ القاعدة الأقدم من BACKUP_RETENTION_DAYS. ويُستحسن معها قاعدة دورة حياة في الحاوية.
#
# الأدوات على الخادم: pg_dump (إصدار القاعدة نفسه 17)، وgpg، وaws (CLI يعمل مع أي تخزين متوافق مع S3).
# المتغيرات من /etc/ejaz/backup.env (صلاحية 600، خارج قيت)، ولا سر في هذا الملف:
#   PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD   اتصال القاعدة (مستخدم قراءة يكفي)
#   BACKUP_BUCKET            اسم الحاوية (منفصلة عن حاوية الملفات الحية)
#   BACKUP_S3_ENDPOINT       عنوان واجهة S3 للمزود في المنطقة (مثل https://<namespace>.compat.objectstorage.me-jeddah-1.oraclecloud.com)
#   AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION   مفاتيح كتابة على حاوية النسخ وحدها
#   BACKUP_GPG_RECIPIENT     معرّف المفتاح العام للتشفير (المفتاح الخاص خارج الخادم عند المسؤول)
#   BACKUP_RETENTION_DAYS    افتراضياً 30
#   BACKUP_SYNC_FILES        true|false (افتراضياً true)
#   APP_ROOT                 جذر المشروع (افتراضياً مجلد هذا السكربت الأب)
set -euo pipefail

log()  { printf '[%s] %s\n' "$(date -u +%FT%TZ)" "$1"; }
fail() { log "✗ $1" >&2; exit 1; }

for var in PGHOST PGDATABASE PGUSER BACKUP_BUCKET BACKUP_S3_ENDPOINT BACKUP_GPG_RECIPIENT; do
  [ -n "${!var:-}" ] || fail "المتغير $var غير مضبوط"
done
for tool in pg_dump gpg aws; do
  command -v "$tool" >/dev/null 2>&1 || fail "الأداة $tool غير مثبتة"
done

APP_ROOT="${APP_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
SYNC_FILES="${BACKUP_SYNC_FILES:-true}"
STAMP="$(date -u +%Y-%m-%dT%H%M%SZ)"
S3=(aws s3 --endpoint-url "$BACKUP_S3_ENDPOINT" --only-show-errors)

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
umask 077

# ١. قاعدة البيانات
DUMP="$WORK/ejaz-$STAMP.dump.gpg"
log "نسخ قاعدة البيانات $PGDATABASE"
pg_dump --format=custom --compress=6 --no-owner --no-privileges \
  | gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "$DUMP"
[ -s "$DUMP" ] || fail "ملف النسخة فارغ"
sha256sum "$DUMP" | awk '{print $1}' > "$DUMP.sha256"

"${S3[@]}" cp "$DUMP" "s3://$BACKUP_BUCKET/db/$(basename "$DUMP")"
"${S3[@]}" cp "$DUMP.sha256" "s3://$BACKUP_BUCKET/db/$(basename "$DUMP").sha256"
log "✓ رُفعت db/$(basename "$DUMP") ($(du -h "$DUMP" | cut -f1))"

# ٢. الملفات المحلية. بلا --delete، فلا يمحو حذفٌ خاطئ في الخادم نسخته الاحتياطية
if [ "$SYNC_FILES" = "true" ]; then
  for disk in private public; do
    dir="$APP_ROOT/storage/app/$disk"
    if [ -d "$dir" ]; then
      log "مزامنة storage/app/$disk"
      "${S3[@]}" sync "$dir" "s3://$BACKUP_BUCKET/files/$disk/" --sse AES256 --exclude "livewire-tmp/*"
    fi
  done
  log "✓ زُومنت الملفات"
fi

# ٣. الاحتفاظ: حذف نسخ القاعدة الأقدم من المدة. الاسم يحمل تاريخه، فالمقارنة نصية
CUTOFF="$(date -u -d "-$RETENTION_DAYS days" +%Y-%m-%d)"
"${S3[@]}" ls "s3://$BACKUP_BUCKET/db/" | awk '{print $4}' | while read -r key; do
  day="$(printf '%s' "$key" | sed -nE 's/^ejaz-([0-9]{4}-[0-9]{2}-[0-9]{2})T.*/\1/p')"
  if [ -n "$day" ] && [[ "$day" < "$CUTOFF" ]]; then
    "${S3[@]}" rm "s3://$BACKUP_BUCKET/db/$key"
    log "حُذفت db/$key (أقدم من $RETENTION_DAYS يوماً)"
  fi
done

log "✓ اكتمل النسخ الاحتياطي $STAMP"
