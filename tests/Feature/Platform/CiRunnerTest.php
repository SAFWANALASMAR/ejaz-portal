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

it('cleans the private Docker server before every job, and keeps the old registration out of job containers', function () {
    $register = ciRunnerFile('register.sh');
    $compose = ciRunnerFile('compose.yaml');

    preg_match('/^while true; do\n(.*?)^done$/ms', $register, $loop);

    // التنظيف قبل المهمة، ولا مهمة إن تعذّر
    expect($loop[1] ?? '')->toMatch('/if ! clean; then.*?continue.*?docker compose run --rm -T runner ephemeral/s')
        ->and($register)->toContain('docker ps -aq | xargs -r docker rm -f')
        ->toContain('docker volume prune -af')
        ->toContain('docker network prune -f')
        ->toContain('docker builder prune -af');

    preg_match('/^  runner:\n(.*?)(?=^  \S|^\S|\z)/ms', $compose, $runner);

    expect($runner[1] ?? '')->not->toContain('/ejaz-ci-state')
        ->and($register)->toContain('-v ejaz-ci-runner_state:/ejaz-ci-state runner remove');
});
