#!/usr/bin/env bash
# النسخ الاحتياطي اليومي — بوابة جمعية إعجاز (docs/security.md «النسخ الاحتياطي»، والاستعادة في scripts/restore.md)
#
# حاوية النسخ عند مزود ثانٍ غير مزود الخادم والملفات (المرشح Hetzner Object Storage — decisions.md، 18 أكتوبر 2026).
#
# ما يُنسخ:
#   ١. قاعدة البيانات: pg_dump بالصيغة المخصصة (مضغوطة)، مشفّرة بمفتاح GPG عام، فلا يفكها خادم مخترق. تُرفع إلى db/.
#   ٢. الملفات، مشفّرة عند العميل قبل الرفع بطبقة crypt في rclone (أسماؤها ومحتواها)، إلى files/:
#      - القرصان المحليان storage/app/private وstorage/app/public متى وُجدا.
#      - والقرص الخاص على S3 في الإنتاج (حاوية الملفات عند Contabo) متى ضُبط BACKUP_SOURCE_S3_BUCKET: يُبث كل ملف
#        من حاوية الملفات إلى حاوية النسخ عبر الخادم ويُشفَّر في الطريق، بلا تخزين على قرصه.
#      ولا --sse: Hetzner لا يدعم إلا SSE-C، والتشفير عند العميل يغني عنه، فلا يُحفظ ملف خاص بلا تشفير.
#   ٣. مدة الاحتفاظ: بقاعدة دورة الحياة في حاوية النسخ وحدها (deploy/storage/lifecycle-backups.json)، لا من الخادم.
#      فمفتاح الخادم كتابة وسرد بلا حذف (PutObject وListBucket بلا DeleteObject)، ومع قفل الكائنات والإصدارات على
#      حاوية النسخ (شرط — deploy/storage/README.md) لا يمحو من يخترق الخادم النسخ ولا يكتب فوقها.
#      ولذلك rclone copy لا sync: sync يحذف من الوجهة ما حُذف من المصدر، والمفتاح بلا DeleteObject فيفشل.
#      والقاعدة تحذف نسخ القاعدة ونسخ الملفات (files/) بعد 30 يوماً، والإصدارات القديمة بعد 30 يوماً من استبدالها.
#      ولئلا ينتهي ملف حي لم يتغير، يُعاد رفع الملفات كلها مرة في الأسبوع (BACKUP_FILES_REFRESH_WEEKDAY)،
#      فالملف المحذوف من الخادم أو حاوية الملفات وحده يتوقف تجديده فيُحذف من النسخ بعد المدة (decisions.md — 14 أكتوبر 2026).
#
# الأدوات في صورة النسخ (deploy/docker/Dockerfile، المرحلة backup): pg_dump (إصدار القاعدة نفسه 17)، وgpg، وaws، وrclone.
# المتغيرات من .env.backup على الخادم (صلاحية 600، خارج قيت)، ولا سر في هذا الملف:
#   PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD   اتصال القاعدة (مستخدم قراءة يكفي)
#   BACKUP_BUCKET            اسم حاوية النسخ (منفصلة عن حاوية الملفات الحية، وعند مزود آخر)
#   BACKUP_S3_ENDPOINT       عنوان واجهة S3 لحاوية النسخ (مثل https://fsn1.your-objectstorage.com)
#   AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION   مفاتيح كتابة بلا حذف على حاوية النسخ وحدها
#   BACKUP_GPG_RECIPIENT     معرّف المفتاح العام لتشفير القاعدة (المفتاح الخاص خارج الخادم عند المسؤول)
#   BACKUP_GPG_PUBLIC_KEY_FILE  (اختياري) ملف المفتاح العام يُستورد في حلقة مفاتيح مؤقتة (حاوية النسخ في compose)
#   BACKUP_CRYPT_PASSWORD BACKUP_CRYPT_SALT   كلمتا سر طبقة crypt للملفات (password وpassword2 في rclone)، بنصهما لا
#                            مموَّهتين. ونسختهما خارج الخادم مع مفتاح GPG الخاص، وإلا تعذر فك نسخ الملفات (scripts/restore.md)
#   BACKUP_SYNC_FILES        true|false (افتراضياً true): نسخ الملفات أصلاً
#   BACKUP_FILES_REFRESH_WEEKDAY  يوم إعادة رفع الملفات كلها: 1 (الاثنين) إلى 7 (الأحد)، افتراضياً 7
#   BACKUP_SOURCE_S3_BUCKET  حاوية الملفات الخاصة على S3 (AWS_BUCKET في .env التطبيق)، أو none صراحةً إن كان القرص الخاص محلياً
#   BACKUP_FILES_MAX_DURATION  أقصى مدة لنسخ الملفات في التشغيل الواحد (افتراضياً 20h)، فلا يمنع نسخة القاعدة التالية
#   BACKUP_SOURCE_S3_ENDPOINT BACKUP_SOURCE_S3_REGION   عنوانها ومنطقتها (AWS_ENDPOINT وAWS_DEFAULT_REGION في .env التطبيق)
#   BACKUP_SOURCE_ACCESS_KEY_ID BACKUP_SOURCE_SECRET_ACCESS_KEY   مفتاح قراءة وحدها على حاوية الملفات إن أمكن عند المزود
#                            (deploy/storage/README.md)، ولا يُستعمل هنا إلا مصدراً للقراءة
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
SYNC_FILES="${BACKUP_SYNC_FILES:-true}"
REFRESH_WEEKDAY="${BACKUP_FILES_REFRESH_WEEKDAY:-7}"
STAMP="$(date -u +%Y-%m-%dT%H%M%SZ)"
S3=(aws s3 --endpoint-url "$BACKUP_S3_ENDPOINT" --only-show-errors)

# إعداد الملفات يُفحص قبل أي رفع، فلا يفشل النسخ كل يوم بعد رفع القاعدة
if [ "$SYNC_FILES" = "true" ]; then
  command -v rclone >/dev/null 2>&1 || fail "الأداة rclone غير مثبتة"
  for var in BACKUP_CRYPT_PASSWORD BACKUP_CRYPT_SALT; do
    [ -n "${!var:-}" ] || fail "المتغير $var غير مضبوط: لا تُرفع ملفات بلا تشفير"
  done
  # صريح لا افتراضي: نسيانه في الإنتاج يترك الملفات الخاصة كلها بلا نسخة مع نجاح ظاهر
  [ -n "${BACKUP_SOURCE_S3_BUCKET:-}" ] \
    || fail "المتغير BACKUP_SOURCE_S3_BUCKET غير مضبوط: اسم حاوية الملفات الخاصة، أو none إن كان القرص الخاص محلياً"
  if [ "$BACKUP_SOURCE_S3_BUCKET" != "none" ]; then
    for var in BACKUP_SOURCE_S3_ENDPOINT BACKUP_SOURCE_ACCESS_KEY_ID BACKUP_SOURCE_SECRET_ACCESS_KEY; do
      [ -n "${!var:-}" ] || fail "المتغير $var غير مضبوط (BACKUP_SOURCE_S3_BUCKET مضبوط)"
    done
  fi
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
umask 077

if [ -n "${BACKUP_GPG_PUBLIC_KEY_FILE:-}" ]; then
  [ -s "$BACKUP_GPG_PUBLIC_KEY_FILE" ] || fail "ملف المفتاح العام $BACKUP_GPG_PUBLIC_KEY_FILE غير موجود"
  export GNUPGHOME="$WORK/gnupg"
  mkdir -p "$GNUPGHOME"
  gpg --batch --quiet --import "$BACKUP_GPG_PUBLIC_KEY_FILE"
fi

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

# ٢. الملفات، مشفّرة عند العميل. الحاويات في rclone من متغيرات البيئة لا من ملف إعداد، فلا سر على القرص:
#    backup حاوية النسخ، وvault طبقة crypt فوق backup:<الحاوية>/files، وsource حاوية الملفات (قراءة وحدها)
if [ "$SYNC_FILES" = "true" ]; then
  export RCLONE_CONFIG_BACKUP_TYPE=s3
  export RCLONE_CONFIG_BACKUP_PROVIDER=Other
  export RCLONE_CONFIG_BACKUP_ENDPOINT="$BACKUP_S3_ENDPOINT"
  export RCLONE_CONFIG_BACKUP_REGION="${AWS_DEFAULT_REGION:-}"
  export RCLONE_CONFIG_BACKUP_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-}"
  export RCLONE_CONFIG_BACKUP_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-}"
  # المفتاح بلا صلاحية إنشاء الحاوية ولا فحصها، وبلا GetObject فلا يقرأ النسخ: لا HEAD على الكائنات قبل الرفع ولا بعده
  # (السرد يكفي للمقارنة، وحجم الرفع يُتحقق منه من رده)
  export RCLONE_CONFIG_BACKUP_NO_CHECK_BUCKET=true
  export RCLONE_CONFIG_BACKUP_NO_HEAD=true
  export RCLONE_CONFIG_BACKUP_NO_HEAD_OBJECT=true

  export RCLONE_CONFIG_VAULT_TYPE=crypt
  export RCLONE_CONFIG_VAULT_REMOTE="backup:$BACKUP_BUCKET/files"
  # من المدخل لا من سطر الأمر، فلا تظهر كلمتا السر في قائمة العمليات
  RCLONE_CONFIG_VAULT_PASSWORD="$(printf '%s' "$BACKUP_CRYPT_PASSWORD" | rclone obscure -)"
  RCLONE_CONFIG_VAULT_PASSWORD2="$(printf '%s' "$BACKUP_CRYPT_SALT" | rclone obscure -)"
  export RCLONE_CONFIG_VAULT_PASSWORD RCLONE_CONFIG_VAULT_PASSWORD2

  # copy لا sync (أعلاه)، وبلا أي خيار حذف.
  # في اليوم العادي لا يُرفع إلا الجديد والمتغير: --update مع --use-server-modtime يتخطى ملفاً نسخته أحدث من آخر تعديل
  # له، بوقت الرفع من السرد وحده (بلا HEAD). وفي يوم التجديد --ignore-times يعيد رفع كل ملف حي، فيبدأ عدّ مدته من جديد
  # ولا يتجاوز نسخ الملفات BACKUP_FILES_MAX_DURATION (افتراضياً 20h)، فينتهي التشغيل قبل موعد الغد ولا يتخطى المؤقت نسخة
  # القاعدة التالية (oneshot). ونسخة أولى أو تجديد أطول يكمل ما بقي في التشغيل التالي (deploy/readiness.md §١٤)
  COPY=(rclone copy --config /dev/null --stats-one-line --stats 10m --exclude "livewire-tmp/**"
    --max-duration "${BACKUP_FILES_MAX_DURATION:-20h}" --cutoff-mode soft)
  if [ "$(date -u +%u)" = "$REFRESH_WEEKDAY" ]; then
    COPY+=(--ignore-times)
    log "التجديد الأسبوعي: إعادة رفع الملفات كلها"
  else
    COPY+=(--update --use-server-modtime)
  fi

  for disk in private public; do
    dir="$APP_ROOT/storage/app/$disk"
    if [ -d "$dir" ]; then
      log "نسخ storage/app/$disk مشفّراً"
      "${COPY[@]}" "$dir" "vault:$disk"
    else
      log "تنبيه: storage/app/$disk غير موجود فلم يُنسخ (ربط حجم storage؟)"
    fi
  done

  if [ "$BACKUP_SOURCE_S3_BUCKET" != "none" ]; then
    export RCLONE_CONFIG_SOURCE_TYPE=s3
    export RCLONE_CONFIG_SOURCE_PROVIDER=Other
    export RCLONE_CONFIG_SOURCE_ENDPOINT="$BACKUP_SOURCE_S3_ENDPOINT"
    export RCLONE_CONFIG_SOURCE_REGION="${BACKUP_SOURCE_S3_REGION:-}"
    export RCLONE_CONFIG_SOURCE_ACCESS_KEY_ID="$BACKUP_SOURCE_ACCESS_KEY_ID"
    export RCLONE_CONFIG_SOURCE_SECRET_ACCESS_KEY="$BACKUP_SOURCE_SECRET_ACCESS_KEY"
    export RCLONE_CONFIG_SOURCE_NO_CHECK_BUCKET=true

    log "نسخ حاوية الملفات $BACKUP_SOURCE_S3_BUCKET مشفّرة"
    "${COPY[@]}" "source:$BACKUP_SOURCE_S3_BUCKET" "vault:s3-private"
  fi
  log "✓ نُسخت الملفات"
fi

log "✓ اكتمل النسخ الاحتياطي $STAMP"
