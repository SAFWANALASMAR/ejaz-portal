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
    local auth
    auth="$(printf 'protocol=https\nhost=github.com\n\n' | git credential fill | sed -n 's/^password=//p')"
    curl -fsS -X POST -H "Authorization: Bearer $auth" -H 'Accept: application/vnd.github+json' \
      "https://api.github.com/repos/$repo/actions/runners/$endpoint" | sed -n 's/.*"token": *"\([^"]*\)".*/\1/p'
  fi
}

docker compose build runner

if [ "$action" = remove ]; then
  token | docker compose run --rm -T runner remove
  docker compose down
  exit 0
fi

docker compose up -d dind
# حاوية جديدة لكل مهمة (--rm): تسجّل مؤقتاً، وتنفّذ مهمة واحدة، وتخرج. ورمز جديد قبل كل واحدة (مدته ساعة)
while true; do
  if ! token | docker compose run --rm -T runner ephemeral; then
    echo "تعذّر تشغيل المشغّل، إعادة المحاولة بعد 30 ثانية" >&2
    sleep 30
  fi
done
