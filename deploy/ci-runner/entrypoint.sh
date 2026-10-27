#!/usr/bin/env bash
# نقطة دخول المشغّل المحلي (docs/deploy/ci-runner.md):
#   ephemeral  يسجّله مؤقتاً (--ephemeral) برمز تسجيل يُقرأ من الدخل القياسي، فينفّذ مهمة واحدة ويُلغى تسجيله وتخرج
#              الحاوية (الافتراضي). والحاوية تُنشأ لكل مهمة (register.sh)، فلا يبقى من مهمة شيء لما بعدها.
#   remove     يلغي تسجيلاً دائماً قديماً محفوظاً في /ejaz-ci-state (قبل المشغّل المؤقت) برمز إزالة من الدخل القياسي
# والرمز من الدخل لا من متغير بيئة، فلا يظهر في docker inspect ولا يُكتب في ملف.
set -euo pipefail

cd /home/runner
# العمل والمؤقت على مسار واحد هنا وفي خادم Docker (dind)، فيصح تمرير "$PWD" و"$(mktemp -d)" إلى docker run -v.
# ويُفرَّغان قبل كل مهمة: مجلد العمل مشترك مع dind، فلا ترى مهمةٌ ملفاتِ ما قبلها
sudo install -d -o runner -g runner /ejaz-ci/work /ejaz-ci/tmp /ejaz-ci-state
state=(.runner .credentials .credentials_rsaparams)

token() {
  local value=''
  IFS= read -r value || true
  [ -n "$value" ] || { echo "الرمز غائب من الدخل القياسي" >&2; exit 1; }
  printf '%s' "$value"
}

case "${1:-ephemeral}" in
  ephemeral)
    # الرمز أولاً في متغير، فيوقف غيابه السكربت (set -e) برسالته
    registration="$(token)"
    sudo find /ejaz-ci/work /ejaz-ci/tmp -mindepth 1 -delete
    # التسجيل في ملفات الحاوية وحدها، ويذهب معها
    ./config.sh --unattended --ephemeral --replace \
      --url "https://github.com/$RUNNER_REPO" --token "$registration" \
      --name "$RUNNER_NAME" --labels "$RUNNER_LABELS" --work /ejaz-ci/work
    unset registration
    exec ./run.sh </dev/null
    ;;
  remove)
    removal="$(token)"
    for file in "${state[@]}"; do
      [ -f "/ejaz-ci-state/$file" ] || { echo "لا تسجيل دائماً محفوظاً" >&2; exit 0; }
      cp "/ejaz-ci-state/$file" .
    done
    ./config.sh remove --token "$removal"
    rm -f /ejaz-ci-state/.runner /ejaz-ci-state/.credentials*
    ;;
  *)
    exec "$@"
    ;;
esac
