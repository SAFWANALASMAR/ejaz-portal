#!/usr/bin/env bash
# الفحوص نصوص تُقيَّم في check (eval)، فمتغيراتها تُقرأ هناك عمداً
# shellcheck disable=SC2016,SC2034
# تجربة scripts/backup.sh فعلياً على MinIO محلي (tests/backup/README.md). من جذر المشروع: bash tests/backup/run.sh
# تُثبت: الرفع بلا SSE ينجح، والملفات في حاوية النسخ مشفّرة أسماءً ومحتوى، والاستعادة تعيدها مطابقة (sha256)،
# ومفتاح النسخ بلا حذف لا يُفشل التشغيل ولا يمحو شيئاً، ومفتاح حاوية الملفات للقراءة وحدها، والسكربت القديم (--sse) يفشل.
set -euo pipefail
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/../.."
DC=(docker compose -f tests/backup/compose.yaml)
PASS=0; FAILED=0
check() { if eval "$2"; then echo "PASS  $1"; PASS=$((PASS + 1)); else echo "FAIL  $1"; FAILED=$((FAILED + 1)); fi; }
tools() { "${DC[@]}" exec -T tools bash -c "$1"; }

if ! docker volume inspect ejazbk-minio-bin >/dev/null 2>&1; then
  echo "بناء MinIO من مصدره (مرة واحدة)"
  docker volume create ejazbk-minio-bin >/dev/null
  docker run --rm -v ejazbk-minio-bin:/out -e CGO_ENABLED=0 -e GOFLAGS=-buildvcs=false golang:1.24-bookworm sh -c \
    'go install github.com/minio/minio@RELEASE.2025-04-22T22-12-26Z && go install github.com/minio/mc@RELEASE.2025-04-16T18-13-26Z && cp /go/bin/minio /go/bin/mc /out/'
fi

"${DC[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
"${DC[@]}" build backup >/dev/null
"${DC[@]}" up -d --wait pgsql >/dev/null
"${DC[@]}" up -d contabo hetzner tools >/dev/null
trap '"${DC[@]}" down -v --remove-orphans >/dev/null 2>&1 || true' EXIT

# التخزينان: حاوية الملفات بمستخدم قراءة وحدها، وحاوية النسخ بقفل الكائنات (يفعّل الإصدارات) وبمستخدم كتابة بلا حذف
tools '
set -e
export PATH=/opt/minio:$PATH
until mc alias set c http://contabo:9000 rootadmin rootadmin-test-only >/dev/null 2>&1; do sleep 1; done
until mc alias set h http://hetzner:9000 rootadmin rootadmin-test-only >/dev/null 2>&1; do sleep 1; done
mc mb -q c/live-files
mc mb -q --with-lock h/backups
mc retention set -q --default COMPLIANCE 1d h/backups
cat > /work/reader.json <<J
{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Action":["s3:GetObject","s3:ListBucket","s3:GetBucketLocation"],"Resource":["arn:aws:s3:::live-files","arn:aws:s3:::live-files/*"]}]}
J
cat > /work/writer.json <<J
{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Action":["s3:PutObject","s3:ListBucket","s3:GetBucketLocation","s3:ListBucketMultipartUploads","s3:ListMultipartUploadParts","s3:AbortMultipartUpload"],"Resource":["arn:aws:s3:::backups","arn:aws:s3:::backups/*"]}]}
J
mc admin policy create c reader /work/reader.json >/dev/null && mc admin user add c reader reader-secret-test >/dev/null && mc admin policy attach c reader --user reader >/dev/null
mc admin policy create h writer /work/writer.json >/dev/null && mc admin user add h writer writer-secret-test >/dev/null && mc admin policy attach h writer --user writer >/dev/null
mc alias set cr http://contabo:9000 reader reader-secret-test >/dev/null
mc alias set hw http://hetzner:9000 writer writer-secret-test >/dev/null

# ملفات المصدر: حاوية الملفات، والقرصان المحليان، ومجلد رفع مؤقت يُستثنى
mkdir -p /work/src/s3/competitions /work/src/s3/livewire-tmp /work/app/storage/app/private/forms /work/app/storage/app/public/news
head -c 3000000 /dev/urandom > /work/src/s3/competitions/video-secret-1.mp4
printf "SECRET-FORM-CONTENT %s\n" {1..200} > /work/src/s3/attachment-secret.pdf
echo tmp > /work/src/s3/livewire-tmp/upload.tmp
printf "PRIVATE-LOCAL-CONTENT %s\n" {1..100} > /work/app/storage/app/private/forms/signature-secret.png
echo "public image" > /work/app/storage/app/public/news/cover.webp
mc cp -q -r /work/src/s3/ c/live-files/ >/dev/null
'

# مفتاح GPG تجريبي للقاعدة: العام للنسخ، والخاص يبقى في /work/gnupg-admin بدور جهاز المسؤول
"${DC[@]}" run --rm -T --entrypoint bash backup -c '
set -e
export GNUPGHOME=/work/gnupg-admin; mkdir -p -m 700 $GNUPGHOME
gpg --batch --quiet --passphrase "" --quick-gen-key "backup-test@ejaz.test" default default never
gpg --batch --armor --export backup-test@ejaz.test > /work/backup-public.asc
chmod -R a+rwX /work' >/dev/null 2>&1

ENVS=(-e PGHOST=pgsql -e PGDATABASE=ejaz -e PGUSER=ejaz -e PGPASSWORD=test-only
  -e BACKUP_BUCKET=backups -e BACKUP_S3_ENDPOINT=http://hetzner:9000
  -e AWS_ACCESS_KEY_ID=writer -e AWS_SECRET_ACCESS_KEY=writer-secret-test -e AWS_DEFAULT_REGION=us-east-1
  -e BACKUP_GPG_RECIPIENT=backup-test@ejaz.test -e BACKUP_GPG_PUBLIC_KEY_FILE=/work/backup-public.asc
  -e APP_ROOT=/work/app
  -e BACKUP_CRYPT_PASSWORD=crypt-password-test-only -e BACKUP_CRYPT_SALT=crypt-salt-test-only
  -e BACKUP_SOURCE_S3_BUCKET=live-files -e BACKUP_SOURCE_S3_ENDPOINT=http://contabo:9000 -e BACKUP_SOURCE_S3_REGION=us-east-1
  -e BACKUP_SOURCE_ACCESS_KEY_ID=reader -e BACKUP_SOURCE_SECRET_ACCESS_KEY=reader-secret-test)
TODAY="$(date -u +%u)"
NOT_TODAY=$(( TODAY % 7 + 1 ))

# ١. السكربت قبل الإصلاح (--sse AES256، الإيداع 1a6fcf35) على تخزين لا يدعم SSE-S3، كـ Hetzner: يفشل بعد رفع القاعدة
git show 1a6fcf35:scripts/backup.sh > tests/backup/.old-backup.sh
old_log="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" -v "$PWD/tests/backup/.old-backup.sh:/old.sh:ro" --entrypoint bash backup /old.sh 2>&1)" && old_rc=0 || old_rc=$?
rm -f tests/backup/.old-backup.sh
check "السكربت القديم بـ --sse يفشل على تخزين بلا SSE-S3، بخطأ التشفير" '[ "$old_rc" -ne 0 ] && echo "$old_log" | grep -qiE "KMS|NotImplemented|encryption"'

# حاوية الملفات الخاصة صريحة: فارغة ترفض العمل قبل أي رفع، وnone تنسخ القرصين المحليين وحدهما
dumps() { tools 'export PATH=/opt/minio:$PATH; mc ls h/backups/db/ 2>/dev/null | grep -c "dump.gpg$" || true'; }
before="$(dumps)"
log0="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_SOURCE_S3_BUCKET= backup 2>&1)" && rc0=0 || rc0=$?
check "BACKUP_SOURCE_S3_BUCKET فارغ يرفض العمل قبل أي رفع" '[ "$rc0" -ne 0 ] && echo "$log0" | grep -q "BACKUP_SOURCE_S3_BUCKET غير مضبوط" && [ "$(dumps)" = "$before" ]'
none_log="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_SOURCE_S3_BUCKET=none -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc00=0 || rc00=$?
check "BACKUP_SOURCE_S3_BUCKET=none ينسخ القرصين المحليين وحدهما" '[ "$rc00" -eq 0 ] && ! echo "$none_log" | grep -q "حاوية الملفات"'

# ٢. السكربت الجديد: يوم عادي ثم يوم التجديد
run1="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc1=0 || rc1=$?
while IFS= read -r line; do printf '    %s\n' "$line"; done <<< "$run1"
check "النسخ ينجح بلا SSE وبمفتاح بلا حذف" '[ "$rc1" -eq 0 ]'
run2="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_REFRESH_WEEKDAY="$TODAY" backup 2>&1)" && rc2=0 || rc2=$?
check "يوم التجديد (--ignore-times) ينجح بالمفتاح نفسه" '[ "$rc2" -eq 0 ] && echo "$run2" | grep -q "التجديد الأسبوعي"'

# ٣. حذف ملف من المصدر ثم نسخ: لا يفشل، ولا يُحذف من النسخ (copy لا sync)
tools 'export PATH=/opt/minio:$PATH; mc rm -q c/live-files/attachment-secret.pdf >/dev/null'
run3="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc3=0 || rc3=$?
check "النسخ بعد حذف ملف من المصدر ينجح" '[ "$rc3" -eq 0 ]'

# ملف تغيّر بعد نسخه يُرفع في اليوم العادي (والاستعادة أدناه تقارن بمحتواه الجديد)
tools 'sleep 2; printf "PRIVATE-LOCAL-CONTENT changed %s\n" {1..120} > /work/app/storage/app/private/forms/signature-secret.png'
run4="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc4=0 || rc4=$?
check "ملف تغيّر يُنسخ في اليوم العادي" '[ "$rc4" -eq 0 ]'


# ٤. حاوية النسخ: لا اسم ولا محتوى مقروء، ونسخة القاعدة موجودة
listing="$(tools 'export PATH=/opt/minio:$PATH; mc ls -r h/backups')"
echo "$listing" | sed 's/^/    /' | head -20
check "نسخة القاعدة مرفوعة إلى db/" 'echo "$listing" | grep -q "db/ejaz-.*\.dump\.gpg$"'
check "ملفات مرفوعة إلى files/" '[ "$(echo "$listing" | grep -c " files/")" -ge 4 ]'
check "لا اسم ملف ولا مجلد مقروء في files/" '! echo "$listing" | grep -qiE "secret|video|attachment|signature|competitions|forms|private|public|s3-private|cover|livewire"'
plain="$(tools 'export PATH=/opt/minio:$PATH; mkdir -p /work/raw && mc cp -q -r h/backups/files/ /work/raw/ >/dev/null && grep -rlE "SECRET-FORM-CONTENT|PRIVATE-LOCAL-CONTENT|public image" /work/raw || true')"
check "لا محتوى مقروء في نسخ الملفات" '[ -z "$plain" ]'
headers="$(tools 'export PATH=/opt/minio:$PATH; mc stat --json -r h/backups/files/ | grep -c "X-Amz-Server-Side-Encryption" || true')"
check "لا SSE على الكائنات (التشفير عند العميل)" '[ "$headers" = "0" ]'

# يوم عادي لا يعيد رفع ما لم يتغير، ويوم التجديد يرفع كل ملف حي إصداراً جديداً: عادي ثم تجديد ثم عادي ثم عادي بعد
# تغيير ملف واحد = إصداران لكل كائن، وإصدار ثالث للملف المتغير وحده
versions="$(tools 'export PATH=/opt/minio:$PATH; mc ls -r --versions h/backups/files/ | grep -c ""')"
objects="$(echo "$listing" | grep -c " files/")"
check "التجديد والتغيير وحدهما يرفعان ($objects كائنات، $versions إصدارات)" '[ "$versions" -eq $(( objects * 2 + 1 )) ]'

# ٥. المفتاحان: النسخ بلا حذف، والمصدر للقراءة وحدها
first="$(echo "$listing" | awk "/ files\\//{print \$NF; exit}")"
check "مفتاح النسخ لا يحذف" '! tools "export PATH=/opt/minio:\$PATH; mc rm -q hw/backups/$first" >/dev/null 2>&1'
check "مفتاح حاوية الملفات لا يكتب" '! tools "export PATH=/opt/minio:\$PATH; echo x | mc pipe -q cr/live-files/x.txt" >/dev/null 2>&1'
check "مفتاح حاوية الملفات لا يحذف" '! tools "export PATH=/opt/minio:\$PATH; mc rm -q cr/live-files/competitions/video-secret-1.mp4" >/dev/null 2>&1'

# مدة الملفات واحدة للخطوة كلها: بثانية واحدة وملفات جديدة كبيرة في الحاوية تُرفع نسخة القاعدة، ويقطع rclone نسخ الحاوية (رمز 10)، ويُتخطى القرصان، ثم يخرج السكربت بـ 75
tools 'export PATH=/opt/minio:$PATH; for i in $(seq 1 12); do head -c 40000000 /dev/urandom | mc pipe -q c/live-files/bulk/part-$i.bin >/dev/null; done'
before="$(dumps)"
run5="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_MAX_DURATION=1s -e RCLONE_BWLIMIT=1M -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc5=0 || rc5=$?
check "مدة الملفات المنقضية: نسخة القاعدة مرفوعة، والخروج 75 بتنبيه «جزئي»" '[ "$rc5" -eq 75 ] && echo "$run5" | grep -q "نُسخ حاوية الملفات live-files جزئياً" && echo "$run5" | grep -q "لم يُنسخ storage/app/private" && echo "$run5" | grep -q "نسخ الملفات جزئي" && [ "$(dumps)" -eq $(( before + 1 )) ]'
for bad in 20x 25h 0 9999999h; do
  check "مدة غير صالحة ($bad) ترفض العمل قبل أي رفع" '! "${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_FILES_MAX_DURATION="$bad" backup >/dev/null 2>&1 && [ "$(dumps)" -eq $(( before + 1 )) ]'
done

# خطأ نسخ لا يوقف ما بعده: حاوية غائبة تفشل، ويُنسخ القرصان مع ذلك، والخروج 1 بعد نسخة القاعدة
run6="$("${DC[@]}" run --rm -T "${ENVS[@]}" -e BACKUP_SOURCE_S3_BUCKET=missing-bucket -e BACKUP_FILES_REFRESH_WEEKDAY="$NOT_TODAY" backup 2>&1)" && rc6=0 || rc6=$?
check "حاوية غائبة: الخروج 1، والقرصان يُنسخان بعدها" '[ "$rc6" -eq 1 ] && echo "$run6" | grep -q "فشل نسخ حاوية الملفات missing-bucket" && echo "$run6" | grep -q "نسخ storage/app/private مشفّراً" && [ "$(dumps)" -eq $(( before + 2 )) ]'

# ٦. الاستعادة من جهاز المسؤول (مفتاح إدارة حاوية النسخ وكلمتا السر من مدير كلمات المرور)، كما في scripts/restore.md
"${DC[@]}" run --rm -T --entrypoint bash \
  -e RCLONE_CONFIG_BACKUP_TYPE=s3 -e RCLONE_CONFIG_BACKUP_PROVIDER=Other -e RCLONE_CONFIG_BACKUP_ENDPOINT=http://hetzner:9000 \
  -e RCLONE_CONFIG_BACKUP_ACCESS_KEY_ID=rootadmin -e RCLONE_CONFIG_BACKUP_SECRET_ACCESS_KEY=rootadmin-test-only \
  -e RCLONE_CONFIG_VAULT_TYPE=crypt -e RCLONE_CONFIG_VAULT_REMOTE=backup:backups/files \
  -e CRYPT_PASSWORD=crypt-password-test-only -e CRYPT_SALT=crypt-salt-test-only backup -c '
set -e
export RCLONE_CONFIG_VAULT_PASSWORD="$(printf %s "$CRYPT_PASSWORD" | rclone obscure -)"
export RCLONE_CONFIG_VAULT_PASSWORD2="$(printf %s "$CRYPT_SALT" | rclone obscure -)"
rm -rf /work/restore && mkdir -p /work/restore
rclone copy --config /dev/null vault:s3-private /work/restore/s3
rclone copy --config /dev/null vault:private /work/restore/private
rclone copy --config /dev/null vault:public /work/restore/public
chmod -R a+rwX /work/restore' >/dev/null
sums="$(tools '
cd /work
a=$(sha256sum src/s3/competitions/video-secret-1.mp4 | cut -d" " -f1); b=$(sha256sum restore/s3/competitions/video-secret-1.mp4 | cut -d" " -f1); [ "$a" = "$b" ] && echo video
a=$(sha256sum src/s3/attachment-secret.pdf | cut -d" " -f1); b=$(sha256sum restore/s3/attachment-secret.pdf | cut -d" " -f1); [ "$a" = "$b" ] && echo deleted-at-source
a=$(sha256sum app/storage/app/private/forms/signature-secret.png | cut -d" " -f1); b=$(sha256sum restore/private/forms/signature-secret.png | cut -d" " -f1); [ "$a" = "$b" ] && echo private
a=$(sha256sum app/storage/app/public/news/cover.webp | cut -d" " -f1); b=$(sha256sum restore/public/news/cover.webp | cut -d" " -f1); [ "$a" = "$b" ] && echo public
[ ! -e restore/s3/livewire-tmp ] && echo no-tmp
true')"
check "الاستعادة تعيد ملف حاوية الملفات مطابقاً (sha256)" 'echo "$sums" | grep -qx video'
check "وما حُذف من المصدر بعد نسخه يُستعاد (copy لا sync)" 'echo "$sums" | grep -qx deleted-at-source'
check "الاستعادة تعيد القرص الخاص المحلي مطابقاً (sha256)" 'echo "$sums" | grep -qx private'
check "الاستعادة تعيد القرص العام مطابقاً (sha256)" 'echo "$sums" | grep -qx public'
check "مجلد الرفع المؤقت livewire-tmp مستثنى" 'echo "$sums" | grep -qx no-tmp'

# ٧. نسخة القاعدة تُفك بالمفتاح الخاص وحده
dbok="$("${DC[@]}" run --rm -T --entrypoint bash -e AWS_ACCESS_KEY_ID=rootadmin -e AWS_SECRET_ACCESS_KEY=rootadmin-test-only -e AWS_DEFAULT_REGION=us-east-1 backup -c '
export GNUPGHOME=/work/gnupg-admin
key=$(aws s3 --endpoint-url http://hetzner:9000 ls s3://backups/db/ | awk "/dump.gpg\$/{print \$4; exit}")
aws s3 --endpoint-url http://hetzner:9000 cp --only-show-errors s3://backups/db/$key /tmp/d.gpg
gpg --batch --quiet --decrypt /tmp/d.gpg 2>/dev/null | head -c 5' 2>/dev/null || true)"
check "نسخة القاعدة تُفك بالمفتاح الخاص (PGDMP)" '[ "$dbok" = "PGDMP" ]'

echo "نجح $PASS، وفشل $FAILED"
[ "$FAILED" -eq 0 ]
