# مشغّل الفحص الآلي المحلي

منذ 26 أكتوبر 2026 ترفض GitHub تشغيل مهام Actions على خوادمها لتعثّر الدفع في الحساب. فصار الفحص يعمل على جهاز صفوان بمشغّل مستضاف ذاتياً، ولا يستهلك دقائق مدفوعة (قرار 27 أكتوبر في `decisions.md`).

## كيف يعمل

- الملفات في `deploy/ci-runner/`:
  - `compose.yaml`: الحاويتان.
  - `Dockerfile`: صورة المشغّل.
  - `entrypoint.sh`: التشغيل والتسجيل والإلغاء.
  - `register.sh`: يجلب الرمز ويسجّل.
- **حاوية `runner`:** صورة GitHub الرسمية `ghcr.io/actions/actions-runner` بإصدار مثبّت، ومعها shellcheck. وفيها Docker CLI وbuildx، وsudo بلا كلمة مرور يحتاجه `setup-php`.
- **حاوية `dind`:** خادم Docker خاص بالمشغّل (`docker:<إصدار>-dind`). والمشغّل يشاركه شبكته، ويكلّمه على `127.0.0.1:2375`. فيلزم:
  - خدمات الفحص (PostgreSQL وRedis) ومنافذ `docker run -p 127.0.0.1::` تُفتح على `127.0.0.1` الذي يراه المشغّل، كما على `ubuntu-latest`. ولا تتعارض مع منافذ بيئات التطوير (5432 و6379 وغيرها).
  - مجلد العمل والمؤقت (`TMPDIR`) على المسار نفسه `/ejaz-ci` في الحاويتين. فيصح `docker run -v "$PWD/...:..."` و`mktemp -d` في الخطوات.
  - حاويات الجهاز (التطوير والعرض) خارج متناول سير العمل.
- **التسجيل** في مجلد `state` وحده خارج المستودع. وصور dind ومجلد العمل في مجلدين مستقلين، فتبقى طبقات الصور بين التشغيلات.
- **التوجيه:** `runs-on: ${{ vars.CI_RUNNER || 'ubuntu-latest' }}` في `.github/workflows/ci.yml`.
  - متغير المستودع `CI_RUNNER=ejaz` يرسل المهام إلى هذا المشغّل.
  - وحذفه يعيدها إلى خوادم GitHub بلا تعديل في الشيفرة.
- **الاسم والتسميات:** `ejaz-local`، بالتسميات `self-hosted` و`Linux` و`X64` و`ejaz`.

## التشغيل والإيقاف

من مجلد `deploy/ci-runner/`:

```bash
docker compose up -d        # تشغيل (ويعود وحده بعد إعادة تشغيل Docker Desktop: restart: unless-stopped)
docker compose stop         # إيقاف مؤقت: المهام تنتظر في GitHub حتى يعود
docker compose logs -f runner
docker compose ps
```

- **المشغّل واحد:** المهام تُنفَّذ واحدة بعد واحدة.
  - كل دفع إلى فرع له طلب دمج يشغّل الفحص مرتين (`push` و`pull_request`).
  - مدة «الجودة والاختبارات» نحو مدة `composer check` على الجهاز.
- **والجهاز مطفأ أو Docker Desktop متوقف:** لا فحص. والمهام تبقى في الطابور حتى 24 ساعة، ثم تُلغى فيُعاد تشغيلها.

## التسجيل وإعادة التسجيل

```bash
bash deploy/ci-runner/register.sh          # تسجيل (أو إعادة تسجيل بالاسم نفسه)، ثم تشغيل
bash deploy/ci-runner/register.sh remove   # إلغاء التسجيل في GitHub، ثم إيقاف وحذف الحاويتين
```

- **الرمز:**
  - يُطلب من `gh api -X POST repos/SAFWANALASMAR/ejaz-portal/actions/runners/registration-token` إن وُجدت `gh`، وإلا ببيانات git المحفوظة لـ github.com (صلاحية `repo`).
  - مدته ساعة، ولا يُطبع ولا يُكتب في ملف.
  - ويمر إلى حاوية التسجيل المؤقتة متغيراً فقط، وتُحذف بعد التسجيل.
- **إعادة التسجيل لازمة** في ثلاث حالات:
  - حُذف المشغّل من إعدادات المستودع.
  - حُذف مجلد `state` (`docker compose down -v`).
  - ظهر في السجل أن التسجيل غير صالح.
- **بإعادة التسجيل** يحل التسجيل الجديد محل القديم بالاسم نفسه (`--replace`).
- **لاسم آخر:** `RUNNER_NAME=<الاسم> bash deploy/ci-runner/register.sh`.

## التحديث

- **تحديث المشغّل:** يحدّث نفسه داخل الحاوية حين تصدر GitHub إصداراً، ويعيد ذلك بعد كل إنشاء للحاوية.
- **لتثبيت إصدار أحدث:** يُغيَّر الوسم في `Dockerfile` و`image:` في `compose.yaml` معاً، ثم `docker compose up -d --build`.
- **إصدار dind:** يُغيَّر في `compose.yaml`، ويُبقى قريباً من إصدار Docker CLI في صورة المشغّل.

## العودة إلى خوادم GitHub

1. حذف متغير المستودع `CI_RUNNER`.
   - بـ `gh variable delete CI_RUNNER`.
   - أو من الإعدادات: Settings ← Secrets and variables ← Actions ← Variables.
2. ثم `bash deploy/ci-runner/register.sh remove`.

## الأمن

- **المستودع خاص**، وطلبات الدمج من النسخ (forks) لا تشغّل سير العمل أصلاً. والإعداد في Settings ← Actions ← General ← Fork pull request workflows، وفيه `run_workflows_from_fork_pull_requests: false`. فلا تصل شيفرة من خارج المستودع إلى المشغّل. **ولا يُفعَّل هذا الخيار ما دام المشغّل محلياً.**
- **أي شيفرة في سير العمل** على فرع في المستودع تعمل على جهاز صفوان.
  - وحاوية dind ممتازة (`privileged`)، فما يعمل فيها يقدر على آلة Docker Desktop الافتراضية.
  - ولا يُستعمل هذا المشغّل لمستودع عام، ولا يُضاف إلى مستودع آخر (`decisions.md`).
- **لا أسرار للمستودع على المشغّل:** سير العمل لا يستعمل أسراراً. ورمز `GITHUB_TOKEN` للقراءة وحدها (`permissions: contents: read`).
