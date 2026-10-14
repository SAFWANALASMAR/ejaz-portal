<?php

/*
| الفحص الآلي لا ينجح دون تدقيق (الفحص الأمني 14 أكتوبر، المجموعة ١ البند ٣): غياب ملف قفل أو إعداد
| التحليل يُفشل الخطوة، ولا تُتخطى الفحوص بشرط يجعل الفحص أخضر بلا تنسيق ولا تحليل ولا اختبارات.
*/

function ciWorkflow(): string
{
    return (string) file_get_contents(base_path('.github/workflows/ci.yml'));
}

it('fails the CI run when a lock file or the phpstan config is missing', function () {
    $workflow = ciWorkflow();

    preg_match('/- name: التحقق من اكتمال التأسيس\n(.*?)(?=\n      - )/s', $workflow, $step);

    expect($step[1] ?? '')->toContain('exit 1');

    foreach (['composer.lock', 'package-lock.json', 'phpstan.neon'] as $file) {
        expect($step[1])->toContain($file);
    }
});

it('never skips the quality steps on a condition that keeps the run green', function () {
    $workflow = ciWorkflow();

    expect($workflow)->not->toMatch('/^\s*if:/m')
        ->and($workflow)->not->toContain('ready=false')
        ->and($workflow)->toContain('vendor/bin/pint --test')
        ->and($workflow)->toContain('vendor/bin/phpstan analyse')
        ->and($workflow)->toContain('php artisan test')
        ->and($workflow)->toContain('composer audit');
});
