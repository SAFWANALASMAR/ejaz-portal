#!/usr/bin/env bash
# المشغّل المحلي المؤقت (docs/deploy/ci-runner.md):
#   bash deploy/ci-runner/register.sh          # حلقة: رمز تسجيل جديد، ثم حاوية لمهمة واحدة، ثم من جديد
#   bash deploy/ci-runner/register.sh remove   # إلغاء تسجيل دائم قديم (قبل المشغّل المؤقت)، ثم إيقاف وحذف الحاويتين
# الرمز من gh إن وُجدت، وإلا ببيانات git المحفوظة لـ github.com. لا يُطبع ولا يُكتب في ملف، ويمر إلى الحاوية من دخلها.
set -euo pipefail

cd "$(dirname "$0")"
repo=SAFWANALASMAR/ejaz-portal
# اسم مشروع compose ومجلداته: الافتراضي للمشغّل الأول، ومشغّل ثانٍ باسم آخر (COMPOSE_PROJECT_NAME=ejaz-ci-runner-2 وRUNNER_NAME)
# فلا يمس خادم الأول ولا مجلداته (docs/deploy/ci-runner.md «مشغّل ثانٍ»)
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-ejaz-ci-runner}"
project=$COMPOSE_PROJECT_NAME
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

# خادم Docker الخاص (dind) ومجلداه يُعاد إنشاؤها قبل كل مهمة، فلا يبقى مما فعلته مهمة شيء للتي بعدها: لا حاوية ولا
# مجلد ولا شبكة ولا swarm ولا إضافة، ولا ملف في قرص dind الممتاز، ولا طبقة صورة عُدّلت في مخزنه، ولا ملف في مجلد العمل
# المشترك. وثمنه تنزيل الصور في كل مهمة (المراجعة الأمنية لـ 0.14.1). ويفشل بأي خطوة فاشلة، فلا تبدأ المهمة
clean() {
  docker compose rm -sf dind >/dev/null \
    && docker volume rm -f "${project}_docker-data" "${project}_work" >/dev/null \
    && docker compose up -d --wait dind >/dev/null
}

docker compose build runner

if [ "$action" = remove ]; then
  # مجلد التسجيل الدائم القديم يُركَّب هنا وحده. ثم تُوقف حاوية المشغّل الدائم القديمة وتُحذف (خدمة runner في ملف
  # ephemeral، فلا يراها down بلا الملف، وتبقى تمسك المجلد)، ثم dind والشبكة، ثم مجلد التسجيل state وحده: docker-data
  # وwork تعيد الحلقة إنشاءهما قبل كل مهمة
  token | docker compose run --rm -T -v "${project}_state:/ejaz-ci-state" runner remove
  docker compose --profile ephemeral rm -sf runner
  docker compose down
  docker volume rm -f "${project}_state" >/dev/null
  exit 0
fi

# حاوية جديدة لكل مهمة (--rm): تسجّل مؤقتاً، وتنفّذ مهمة واحدة، وتخرج. ورمز جديد قبل كل واحدة (مدته ساعة)
while true; do
  # ولا مهمة بلا خادم Docker جديد: إن تعذّر أُعيدت المحاولة
  if ! clean; then
    echo "تعذّر إعادة إنشاء خادم Docker الخاص، إعادة المحاولة بعد 30 ثانية" >&2
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
