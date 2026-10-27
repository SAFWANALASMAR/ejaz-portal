#!/usr/bin/env bash
# تسجيل المشغّل المحلي أو إعادة تسجيله أو إلغاؤه (docs/deploy/ci-runner.md):
#   bash deploy/ci-runner/register.sh          # تسجيل، ثم تشغيل
#   bash deploy/ci-runner/register.sh remove   # إلغاء التسجيل في GitHub، ثم إيقاف
# الرمز من gh إن وُجدت، وإلا ببيانات git المحفوظة لـ github.com. لا يُطبع ولا يُكتب في ملف.
set -euo pipefail

cd "$(dirname "$0")"
repo=SAFWANALASMAR/ejaz-portal
action="${1:-register}"
case "$action" in
  register) endpoint=registration-token ;;
  remove) endpoint=remove-token ;;
  *) echo "الاستعمال: register.sh [register|remove]" >&2; exit 2 ;;
esac

if command -v gh >/dev/null; then
  RUNNER_TOKEN="$(gh api -X POST "repos/$repo/actions/runners/$endpoint" --jq .token)"
else
  auth="$(printf 'protocol=https\nhost=github.com\n\n' | git credential fill | sed -n 's/^password=//p')"
  RUNNER_TOKEN="$(curl -fsS -X POST -H "Authorization: Bearer $auth" -H 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/$repo/actions/runners/$endpoint" | sed -n 's/.*"token": *"\([^"]*\)".*/\1/p')"
  unset auth
fi
[ -n "$RUNNER_TOKEN" ] || { echo "تعذّر الحصول على رمز $endpoint" >&2; exit 1; }
export RUNNER_TOKEN

docker compose build runner
docker compose stop runner
docker compose run --rm -e RUNNER_TOKEN runner "$action"
unset RUNNER_TOKEN

if [ "$action" = register ]; then
  docker compose up -d
else
  docker compose down
fi
