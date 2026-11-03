<?php

/*
| المشغّل المحلي للفحص الآلي (المراجعة المستقلة على v0.14.0، عالية — docs/deploy/ci-runner.md «الأمن»): لا شيفرة من
| نسخة ولا من Dependabot على جهاز صفوان، ولا شيفرة مكتبة عند التثبيت، ومشغّل مؤقت لمهمة واحدة برمز لا يُكتب في ملف.
*/

function ciJob(string $job): string
{
    $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

    preg_match('/^  '.$job.':\n(.*?)(?=^  \S|\z)/ms', $workflow, $match);

    return $match[1] ?? '';
}

function ciRunnerFile(string $file): string
{
    return (string) file_get_contents(base_path('deploy/ci-runner/'.$file));
}

it('runs no job for a fork pull request or for Dependabot', function (string $job) {
    expect(ciJob($job))->toContain('    if: '.ciRunnerGuard()."\n");
})->with(['php-image', 'quality']);

it('installs the dependencies without their plugins or install scripts, then runs the project script', function () {
    $job = ciJob('quality');

    expect($job)->toContain('composer install --no-interaction --prefer-dist --no-progress --no-plugins --no-scripts')
        ->toContain('composer run-script --no-plugins post-autoload-dump')
        ->toContain('npm ci --ignore-scripts')
        ->not->toMatch('/npm ci\s*$/m')
        ->not->toMatch('/composer install(?!.*--no-scripts)/');
});

it('caches Composer from its Linux cache folder', function () {
    expect(ciJob('quality'))->toContain('path: ~/.cache/composer')
        ->not->toContain('~/.composer/cache');
});

it('registers the runner for one job at a time, in a container made for that job', function () {
    $entrypoint = ciRunnerFile('entrypoint.sh');
    $compose = ciRunnerFile('compose.yaml');

    expect($entrypoint)->toContain('--ephemeral')
        // لا تسجيل دائم يُحفظ ويُعاد تشغيله
        ->not->toContain('cp "${state[@]}" /ejaz-ci-state/')
        ->and(ciRunnerFile('Dockerfile'))->toContain('CMD ["ephemeral"]')
        ->and(ciRunnerFile('register.sh'))->toContain('docker compose run --rm -T runner ephemeral');

    // المشغّل لا يعيده Docker وحده ولا يشغّله up: حاوية لكل مهمة من الحلقة
    preg_match('/^  runner:\n(.*?)(?=^  \S|^\S|\z)/ms', $compose, $runner);

    expect($runner[1] ?? '')->not->toContain('restart:')
        ->toContain('profiles: [ephemeral]');
});

it('passes the registration token on standard input, never in the environment or a file', function () {
    $register = ciRunnerFile('register.sh');
    $entrypoint = ciRunnerFile('entrypoint.sh');

    expect($register)->not->toContain('-e RUNNER_TOKEN')
        ->not->toContain('export RUNNER_TOKEN')
        ->toContain('token | docker compose run --rm -T runner ephemeral')
        ->and($entrypoint)->not->toContain('RUNNER_TOKEN')
        ->toContain('IFS= read -r value');

    // ولا تحويل لمخرج الرمز إلى ملف
    expect($register)->not->toMatch('/token[^|\n]*>\s*[^&\s]/');
});

it('recreates the private Docker server and its volumes before every job, and keeps the old registration out of job containers', function () {
    $register = ciRunnerFile('register.sh');
    $compose = ciRunnerFile('compose.yaml');

    preg_match('/^while true; do\n(.*?)^done$/ms', $register, $loop);
    preg_match('/^clean\(\) \{\n(.*?)^\}$/ms', $register, $clean);

    // خادم جديد ومجلدان جديدان قبل المهمة، بخطوات متسلسلة (&&)، ولا مهمة إن تعذّر
    expect($loop[1] ?? '')->toMatch('/if ! clean; then.*?continue.*?docker compose run --rm -T runner ephemeral/s')
        ->and($clean[1] ?? '')->toMatch('/docker compose rm -sf dind[^\n]*\\\\\n\s*&& docker volume rm -f "\$\{project\}_docker-data" "\$\{project\}_work"[^\n]*\\\\\n\s*&& docker compose up -d --wait dind/');

    preg_match('/^  runner:\n(.*?)(?=^  \S|^\S|\z)/ms', $compose, $runner);

    expect($runner[1] ?? '')->not->toContain('/ejaz-ci-state')
        ->and($register)->toContain('-v "${project}_state:/ejaz-ci-state" runner remove');
});

it('names its compose project and volumes from COMPOSE_PROJECT_NAME, so a second runner never touches the first', function () {
    $register = ciRunnerFile('register.sh');

    // الافتراضي اسم المشغّل الأول، ولا اسم مجلد مكتوب بلا المتغير
    expect($register)->toContain('export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-ejaz-ci-runner}"')
        ->toContain('project=$COMPOSE_PROJECT_NAME')
        ->not->toMatch('/ejaz-ci-runner_(docker-data|work|state)/')
        // ومشغّل ثانٍ بلا RUNNER_NAME آخر لا يبدأ، فلا يحل محل الأول بـ --replace
        ->toContain('[ "$project" = ejaz-ci-runner ] || [ "${RUNNER_NAME:-ejaz-local}" != ejaz-local ] || { echo "مشغّل ثانٍ يلزمه RUNNER_NAME آخر" >&2; exit 2; }');
});

// على جهاز صفوان (28 أكتوبر 2026): بقيت حاوية المشغّل الدائم القديمة تعمل بعد remove ومعها مجلد state، لأن خدمة runner في
// ملف ephemeral فلا يراها down. فالإلغاء يوقفها ويحذفها بعد إلغاء التسجيل، ثم يحذف مجلد state وحده
it('stops the old persistent runner container on remove, then deletes only its registration volume', function () {
    preg_match('/^if \[ "\$action" = remove \]; then\n(.*?)^fi$/ms', ciRunnerFile('register.sh'), $remove);
    $steps = array_values(array_filter(array_map('trim', explode("\n", $remove[1] ?? '')), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));

    expect($steps)->toBe([
        'token | docker compose run --rm -T -v "${project}_state:/ejaz-ci-state" runner remove',
        'docker compose --profile ephemeral rm -sf runner',
        'docker compose down',
        'docker volume rm -f "${project}_state" >/dev/null',
        'exit 0',
    ]);
});

it('audits the Composer and production npm packages on every run, as a blocking step', function () {
    $job = ciJob('quality');

    expect(preg_match('/- name: تدقيق المكتبات أمنياً\n(.*?)\n\s*\n/su', $job, $step))->toBe(1)
        ->and($step[1])->toMatch('/^\s+composer audit\s*$/m')
        ->toContain('npm audit --omit=dev --audit-level=high')
        ->not->toContain('continue-on-error')
        ->not->toContain('|| true')
        // ولا يُجعل الفحص كله غير مانع
        ->and($job)->not->toMatch('/^    continue-on-error:/m');
});
