#!/usr/bin/env bash
# المشغّل المحلي المؤقت (docs/deploy/ci-runner.md):
#   bash deploy/ci-runner/register.sh          # حلقة: رمز تسجيل جديد، ثم حاوية لمهمة واحدة، ثم من جديد
#   bash deploy/ci-runner/register.sh remove   # إلغاء تسجيل دائم قديم (قبل المشغّل المؤقت)، ثم إيقاف وحذف الحاويتين
# الرمز من gh إن وُجدت، وإلا ببيانات git المحفوظة لـ github.com. لا يُطبع ولا يُكتب في ملف، ويمر إلى الحاوية من دخلها.
set -euo pipefail

cd "$(dirname "$0")"
repo=SAFWANALASMAR/ejaz-portal
action="${1:-loop}"
case "$action" in
  loop) endpoint=registration-token ;;
  remove) endpoint=remove-token ;;
  *) echo "الاستعمال: register.sh [loop|remove]" >&2; exit 2 ;;
esac

token() {
  if command -v gh >/dev/null; then
    gh api -X POST "repos/$repo/actions/runners/$endpoint" --jq .token
  else
    # ورأس التوثيق من الدخل القياسي لا سطر الأمر، فلا يظهر لعملية أخرى على الجهاز
    printf 'protocol=https\nhost=github.com\n\n' | git credential fill | sed -n 's/^password=\(.*\)$/Authorization: Bearer \1/p' \
      | curl -fsS -X POST -H @- -H 'Accept: application/vnd.github+json' \
        "https://api.github.com/repos/$repo/actions/runners/$endpoint" | sed -n 's/.*"token": *"\([^"]*\)".*/\1/p'
  fi
}

# ما تتركه مهمة في خادم Docker الخاص (dind، دائم) لا يبقى للتي بعدها: الحاويات، والمجلدات، والشبكات، وذاكرة البناء،
# والصور المبنية محلياً (ejaz-*). وتبقى صور السجلات وحدها، فلا يُعاد تنزيلها، وكلها تُسحب من جديد في الفحص (--pull،
# وخدمات الفحص تُسحب كل مرة)، فلا يبقى وسم عدّلته مهمة
clean() {
  docker compose exec -T dind sh -c '
    docker ps -aq | xargs -r docker rm -f
    docker volume prune -af
    docker network prune -f
    docker builder prune -af
    docker images --format "{{.Repository}}:{{.Tag}}" | grep "^ejaz-" | xargs -r docker rmi -f
    docker image prune -f
  ' >/dev/null
}

docker compose build runner

if [ "$action" = remove ]; then
  # مجلد التسجيل الدائم القديم يُركَّب هنا وحده، ثم يُحذف مع الحاويتين
  token | docker compose run --rm -T -v ejaz-ci-runner_state:/ejaz-ci-state runner remove
  docker compose down
  docker volume rm -f ejaz-ci-runner_state >/dev/null
  exit 0
fi

docker compose up -d --wait dind
# حاوية جديدة لكل مهمة (--rm): تسجّل مؤقتاً، وتنفّذ مهمة واحدة، وتخرج. ورمز جديد قبل كل واحدة (مدته ساعة)
while true; do
  # ولا مهمة بلا تنظيف: إن تعذّر أُعيدت المحاولة
  if ! clean; then
    echo "تعذّر تنظيف خادم Docker الخاص، إعادة المحاولة بعد 30 ثانية" >&2
    sleep 30
    continue
  fi
  if ! token | docker compose run --rm -T runner ephemeral; then
    echo "تعذّر تشغيل المشغّل، إعادة المحاولة بعد 30 ثانية" >&2
    sleep 30
  fi
  # ومهلة قصيرة بين الدورات، فلا يُطلب رمز بلا توقف إن خرجت الحاوية سريعاً بلا مهمة (تحديث ذاتي مثلاً)
  sleep 5
done
