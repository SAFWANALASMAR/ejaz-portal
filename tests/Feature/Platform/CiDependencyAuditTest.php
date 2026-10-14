<?php

/*
| تدقيق مكتبات الواجهة في الفحص الآلي (المجموعة ١، البند ٧): الفحص المانع يبقى لمكتبات الإنتاج
| (`--omit=dev --audit-level=high`)، وتُضاف خطوة تقريرية غير مانعة تشمل أدوات البناء والتطوير
| (`--audit-level=moderate` بلا `--omit=dev`)، فلا تغيب ثغرات أدوات البناء عن السجل.
*/

/**
 * خطوات مهمة الجودة في ci.yml، كل خطوة نصاً واحداً.
 *
 * @return list<string>
 */
function npmAuditCiSteps(): array
{
    $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
    $steps = substr($workflow, (int) strpos($workflow, "\n    steps:\n"));

    return array_values(array_filter(
        preg_split('/^(?=      - )/m', $steps) ?: [],
        fn (string $step): bool => str_starts_with($step, '      - '),
    ));
}

/**
 * @return list<string>
 */
function npmAuditCiStepsRunning(string $pattern): array
{
    return array_values(array_filter(npmAuditCiSteps(), fn (string $step): bool => preg_match($pattern, $step) === 1));
}

it('keeps the blocking production npm audit at high severity', function () {
    $steps = npmAuditCiStepsRunning('/npm audit --omit=dev --audit-level=high/');

    expect($steps)->toHaveCount(1)
        ->and($steps[0])->toContain('composer audit')
        ->and($steps[0])->not->toContain('continue-on-error');
});

it('reports every npm package including build tools at moderate severity without blocking', function () {
    $steps = npmAuditCiStepsRunning('/npm audit[^\n]*--audit-level=moderate/');

    expect($steps)->toHaveCount(1);

    $step = $steps[0];
    preg_match_all('/npm audit[^\n]*/', $step, $commands);

    expect($commands[0])->not->toBeEmpty();
    foreach ($commands[0] as $command) {
        expect($command)->not->toContain('--omit');
    }

    expect($step)->toMatch('/^\s+continue-on-error: true\s*$/m')
        ->and($step)->toMatch("/^\\s+if: steps\\.boot\\.outputs\\.ready == 'true'\\s*$/m");
});
