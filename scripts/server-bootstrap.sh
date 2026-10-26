#!/usr/bin/env bash
# تجهيز خادم Ubuntu 24.04 جديد لحزمة الإنتاج، مرة واحدة قبل أول نشر — بوابة جمعية إعجاز (docs/deploy.md «Hostinger KVM»)
#
# التشغيل بـ root على الخادم الجديد، بعد إضافة مفتاح SSH العام من لوحة المزود (أو تمريره هنا):
#   curl -fsSLo server-bootstrap.sh <رابط الملف الخام من المستودع بوسم الإصدار>   # أو scp من جهازك
#   less server-bootstrap.sh                                                      # اقرأه قبل تشغيله
#   ADMIN_USER=ejaz bash server-bootstrap.sh
#
# المتغيرات:
#   ADMIN_USER   اسم المستخدم غير root الذي يدير الخادم وينشر (إلزامي، حروف صغيرة وأرقام و- و_)
#   SSH_PUBKEY   مفتاحه العام بنصه (اختياري). وبدونه تُنسخ مفاتيح /root/.ssh/authorized_keys التي أضافتها لوحة المزود
#   ADMIN_CIDR   عنوان الإدارة أو شبكتها لمنفذ 22 في ufw (اختياري، مثل 203.0.113.7/32). وبدونه 22 للعموم مع fail2ban
#
# ما يفعله بالترتيب (docs/deploy.md «أولاً»):
#   ١. تحديث الحزم، والتحديثات الأمنية التلقائية (unattended-upgrades) بلا إعادة إقلاع آلية.
#   ٢. المستخدم غير root بمفتاحه، وsudo بكلمة مرور تُكتب في الطرفية.
#   ٣. SSH بالمفتاح وحده، ولا دخول بـ root. ولا يُطفأ الدخول بكلمة المرور قبل التأكد من وجود مفتاح للمستخدم.
#   ٤. ufw: 22 و80 و443 وحدها. وDocker يتجاوز ufw في المنافذ المنشورة، فالحزمة لا تنشر إلا 80 و443.
#   ٥. fail2ban لـ SSH.
#   ٦. Docker Engine وإضافة compose من مستودع Docker الرسمي، والمستخدم في مجموعة docker.
#   ٧. مجلدات التطبيق: /opt/ejaz و/etc/ejaz/tls و/var/lib/ejaz/acme.
#
# آمن لإعادة التشغيل: كل خطوة تكتب ملفها كاملاً أو تتخطى ما هو قائم. ولا يغلق جلسة SSH الحالية.
# - ADMIN_CIDR يُتحقق أن عنوان الجلسة الحالية داخله قبل أي تغيير، وإلا رُفض (أو طُلب تأكيد في طرفية).
# - وإعادة التشغيل بـ ADMIN_CIDR مختلف تضيف قاعدة 22 جديدة ولا تحذف القديمة: ufw status numbered، ثم ufw delete <رقمها>
#   بعد التأكد من الدخول بالقاعدة الجديدة.
# لا أسرار فيه: كلمة مرور sudo للمستخدم يكتبها المشغّل في الطرفية (passwd) قبل إغلاق دخول root، ولا يُشدَّد SSH
# بدونها، لأن المستخدم بلا كلمة مرور لا يستعمل sudo ولا يضعها لنفسه. وفي تشغيل بلا طرفية: passwd <المستخدم> أولاً.
set -euo pipefail

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "شغّله بـ root: sudo ADMIN_USER=... bash $0"
# shellcheck source=/dev/null
. /etc/os-release
if [ "${ID:-}" != ubuntu ] || [ "${VERSION_ID:-}" != "24.04" ]; then
  fail "مكتوب لـ Ubuntu 24.04 وحده، والنظام ${PRETTY_NAME:-غير معروف}"
fi

ADMIN_USER="${ADMIN_USER:-}"
printf '%s' "$ADMIN_USER" | grep -Ex '[a-z_][a-z0-9_-]{1,31}' >/dev/null || fail "حدّد ADMIN_USER صحيحاً (حروف صغيرة وأرقام و- و_)"
[ "$ADMIN_USER" != root ] || fail "ADMIN_USER لا يكون root"
ADMIN_CIDR="${ADMIN_CIDR:-}"
if [ -n "$ADMIN_CIDR" ]; then
  # النطاق، ثم عنوان هذه الجلسة فيه (IPv4 أو IPv6، والعنوان IPv4 المغلف في IPv6 يُقارن بأصله)، قبل أي تغيير.
  # فإن لم يكن فيه لم يدخل صاحب الجلسة بعد إغلاق root إلا من لوحة المزود
  command -v python3 >/dev/null || fail "python3 غير موجود، ويلزم لفحص ADMIN_CIDR (موجود في صورة Ubuntu 24.04 القياسية)"
  CLIENT_IP="${SSH_CLIENT:-}"
  CLIENT_IP="${CLIENT_IP%% *}"
  inside="$(python3 - "$ADMIN_CIDR" "$CLIENT_IP" <<'PY'
import ipaddress, sys
try:
    net = ipaddress.ip_network(sys.argv[1], strict=False)
except ValueError:
    print("invalid"); sys.exit()
if not sys.argv[2]:
    print("unknown"); sys.exit()
ip = ipaddress.ip_address(sys.argv[2])
if ip.version == 6 and ip.ipv4_mapped:
    ip = ip.ipv4_mapped
print("yes" if ip.version == net.version and ip in net else "no")
PY
)"
  case "$inside" in
    yes) ;;
    invalid) fail "ADMIN_CIDR غير صالح: $ADMIN_CIDR" ;;
    *)
      if [ "$inside" = unknown ]; then
        msg="لا يُعرف عنوان هذه الجلسة (ليست SSH)، فلا يُتحقق أنه داخل ADMIN_CIDR=$ADMIN_CIDR"
      else
        msg="عنوان هذه الجلسة $CLIENT_IP خارج ADMIN_CIDR=$ADMIN_CIDR، فلن تدخل بـ SSH بعدها إلا من لوحة المزود"
      fi
      [ -t 0 ] || fail "$msg. صحّح ADMIN_CIDR، أو شغّله من طرفية لتأكيده"
      printf '\033[1;33m! %s\033[0m\nاكتب «نعم» للمتابعة: ' "$msg"
      read -r answer
      [ "$answer" = "نعم" ] || fail "أُلغي التشغيل، ولم يتغير شيء"
      ;;
  esac
fi

export DEBIAN_FRONTEND=noninteractive
APT=(apt-get -y -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold)

say "١/٧ تحديث الحزم والتحديثات الأمنية التلقائية"
"${APT[@]}" update
"${APT[@]}" upgrade
"${APT[@]}" install ca-certificates curl gnupg ufw fail2ban python3-systemd unattended-upgrades git
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
# إعادة الإقلاع بعد تحديث النواة تُجدول يدوياً في وقت هادئ (docs/deploy.md «Hostinger KVM»)، فلا تتوقف البوابة فجأة
cat > /etc/apt/apt.conf.d/52ejaz-unattended <<'EOF'
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF
systemctl enable --now unattended-upgrades

say "٢/٧ المستخدم $ADMIN_USER"
if ! id "$ADMIN_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "$ADMIN_USER"
fi
usermod -aG sudo "$ADMIN_USER"
HOME_DIR="$(getent passwd "$ADMIN_USER" | cut -d: -f6)"
install -d -m 700 -o "$ADMIN_USER" -g "$ADMIN_USER" "$HOME_DIR/.ssh"
KEYS="$HOME_DIR/.ssh/authorized_keys"
touch "$KEYS"
if [ -n "${SSH_PUBKEY:-}" ]; then
  printf '%s' "$SSH_PUBKEY" | grep -Ex '(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com) [A-Za-z0-9+/=]+( .*)?' >/dev/null \
    || fail "SSH_PUBKEY ليس مفتاحاً عاماً صالحاً"
  grep -qxF "$SSH_PUBKEY" "$KEYS" || printf '%s\n' "$SSH_PUBKEY" >> "$KEYS"
elif [ -s /root/.ssh/authorized_keys ]; then
  # المفتاح وحده بلا خيارات قبله: بعض الصور تضع لـ root ‏command="echo ..." فيُغلق الدخول بالمفتاح المنسوخ
  sed -nE 's/^.*((ssh-(ed25519|rsa)|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com) [A-Za-z0-9+\/=]+( .*)?)$/\1/p' \
    /root/.ssh/authorized_keys | while IFS= read -r line; do
      if ! grep -qxF "$line" "$KEYS"; then
        printf '%s\n' "$line" >> "$KEYS"
      fi
    done
fi
chown "$ADMIN_USER:$ADMIN_USER" "$KEYS"
chmod 600 "$KEYS"
grep -Eq '^(ssh-|ecdsa-|sk-)' "$KEYS" || fail "لا مفتاح SSH للمستخدم $ADMIN_USER. أضفه من لوحة المزود لـ root أو مرّره في SSH_PUBKEY، ثم أعد التشغيل"

# كلمة مرور sudo قبل إغلاق root، وإلا بقي الخادم بلا من يستعمل sudo (P في passwd -S = كلمة صالحة)
password_set() { [ "$(passwd -S "$ADMIN_USER" | awk '{print $2}')" = P ]; }
if ! password_set && [ -t 0 ]; then
  echo "كلمة مرور sudo للمستخدم $ADMIN_USER (طويلة، وتُحفظ في مدير كلمات المرور):"
  until passwd "$ADMIN_USER"; do echo "لم تُضبط، أعد المحاولة."; done
fi
password_set || fail "لا كلمة مرور للمستخدم $ADMIN_USER، فلا يُغلق دخول root. شغّل passwd $ADMIN_USER ثم أعد التشغيل"

say "٣/٧ SSH بالمفتاح وحده"
# الملف أول ما في sshd_config.d، والقيمة الأولى هي النافذة، فيسبق 50-cloud-init.conf الذي يفتح كلمة المرور
cat > /etc/ssh/sshd_config.d/00-ejaz.conf <<'EOF'
# docs/deploy.md «أولاً» الخطوة 3 — scripts/server-bootstrap.sh
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
EOF
# مجلد فصل الامتيازات: sshd -t يرفض الإعداد بدونه في Ubuntu 24.04 إن لم يكن ssh قد بدأ بعد (تفعيل المقبس)
install -d -m 755 /run/sshd
sshd -t || fail "إعداد SSH غير صالح، ولم يُطبَّق"
systemctl restart ssh

say "٤/٧ الجدار الناري (ufw)"
ufw default deny incoming
ufw default allow outgoing
if [ -n "$ADMIN_CIDR" ]; then
  ufw allow from "$ADMIN_CIDR" to any port 22 proto tcp comment 'ssh (admin)'
else
  ufw limit 22/tcp comment 'ssh'
fi
ufw allow 80/tcp comment 'http'
ufw allow 443/tcp comment 'https'
ufw --force enable
ufw status verbose

say "٥/٧ fail2ban"
cat > /etc/fail2ban/jail.d/ejaz.local <<'EOF'
[sshd]
enabled  = true
backend  = systemd
maxretry = 5
findtime = 10m
bantime  = 1h
EOF
systemctl enable fail2ban
systemctl restart fail2ban

say "٦/٧ Docker وcompose من المستودع الرسمي"
# مفتاح المستودع ببصمته المنشورة في وثائق Docker، قبل أن يُوثق به أي حزمة
DOCKER_KEY_FPR=9DC858229FC7DD38854AE2D88D81803C0EBFCD88
install -m 0755 -d /etc/apt/keyrings
key_tmp="$(mktemp)"
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o "$key_tmp"
gnupg_tmp="$(mktemp -d)"
fprs="$(GNUPGHOME="$gnupg_tmp" gpg --batch --show-keys --with-colons "$key_tmp" | awk -F: '/^fpr:/ {print $10}')"
rm -rf "$gnupg_tmp"
if ! printf '%s\n' "$fprs" | grep -qx "$DOCKER_KEY_FPR"; then
  rm -f "$key_tmp"
  fail "بصمة مفتاح Docker لا تطابق $DOCKER_KEY_FPR، فلم يُضف المستودع"
fi
install -m 0644 "$key_tmp" /etc/apt/keyrings/docker.asc
rm -f "$key_tmp"
printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu %s stable\n' \
  "$(dpkg --print-architecture)" "$VERSION_CODENAME" > /etc/apt/sources.list.d/docker.list
"${APT[@]}" update
"${APT[@]}" install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker
# مجموعة docker تعادل root، ولا يدخل الخادم إلا صاحب المفتاح (docs/deploy.md)
usermod -aG docker "$ADMIN_USER"
docker compose version

say "٧/٧ مجلدات التطبيق"
install -d -m 755 -o "$ADMIN_USER" -g "$ADMIN_USER" /opt/ejaz
install -d -m 755 /etc/ejaz
install -d -m 755 /etc/ejaz/tls
install -d -m 755 /var/lib/ejaz/acme

printf '\n\033[1;32m✓ اكتمل التجهيز.\033[0m\n'
cat <<EOF
قبل إغلاق جلسة root هذه، من جهازك في نافذة جديدة:
  ssh $ADMIN_USER@<عنوان الخادم>، ثم sudo -v، ثم docker ps
ثم أكمل من docs/deploy.md «Hostinger KVM» الخطوة ٤.
EOF
