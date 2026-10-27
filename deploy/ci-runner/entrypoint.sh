#!/usr/bin/env bash
# نقطة دخول المشغّل المحلي (docs/deploy/ci-runner.md):
#   run       يشغّل المشغّل بتسجيله المحفوظ في /ejaz-ci-state (الافتراضي)
#   register  يسجّله بـ RUNNER_TOKEN (رمز تسجيل مدته ساعة) ويحفظ التسجيل
#   remove    يلغي تسجيله في GitHub بـ RUNNER_TOKEN (رمز إزالة) ويحذف المحفوظ
set -euo pipefail

cd /home/runner
# العمل والمؤقت على مسار واحد هنا وفي خادم Docker (dind)، فيصح تمرير "$PWD" و"$(mktemp -d)" إلى docker run -v
sudo install -d -o runner -g runner /ejaz-ci/work /ejaz-ci/tmp /ejaz-ci-state
state=(.runner .credentials .credentials_rsaparams)

restore() {
  for file in "${state[@]}"; do
    [ -f "/ejaz-ci-state/$file" ] || { echo "المشغّل غير مسجّل: bash deploy/ci-runner/register.sh" >&2; return 1; }
    cp "/ejaz-ci-state/$file" .
  done
}

case "${1:-run}" in
  run)
    restore
    exec ./run.sh
    ;;
  register)
    : "${RUNNER_TOKEN:?RUNNER_TOKEN غائب}"
    ./config.sh --unattended --replace \
      --url "https://github.com/$RUNNER_REPO" --token "$RUNNER_TOKEN" \
      --name "$RUNNER_NAME" --labels "$RUNNER_LABELS" --work /ejaz-ci/work
    cp "${state[@]}" /ejaz-ci-state/
    chmod 600 /ejaz-ci-state/.credentials*
    ;;
  remove)
    : "${RUNNER_TOKEN:?RUNNER_TOKEN غائب}"
    restore
    ./config.sh remove --token "$RUNNER_TOKEN"
    rm -f /ejaz-ci-state/.runner /ejaz-ci-state/.credentials*
    ;;
  *)
    exec "$@"
    ;;
esac
