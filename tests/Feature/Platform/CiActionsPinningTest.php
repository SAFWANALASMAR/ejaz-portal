<?php

/*
| إجراءات GitHub مثبّتة ببصمة الالتزام الكاملة لا بالوسم (الفحص الأمني 14 أكتوبر، البند 8): الوسم يُنقل
| فيشغّل الفحص شيفرة لم تُراجع، والبصمة لا تتغير. والتعليق بعدها يحمل الوسم ليقرأه Dependabot ويحدّثه.
*/

/** @return array<string, list<string>> */
function workflowUsesLines(): array
{
    $lines = [];

    foreach (glob(base_path('.github/workflows/*.{yml,yaml}'), GLOB_BRACE) ?: [] as $file) {
        preg_match_all('/^\s*(?:-\s+)?uses:\s*(.+?)\s*$/m', (string) file_get_contents($file), $matches);
        $lines[basename($file)] = $matches[1];
    }

    return $lines;
}

it('pins every action in the workflows to a full commit sha with its tag in a comment', function () {
    $lines = workflowUsesLines();

    expect($lines)->not->toBeEmpty()
        ->and(array_merge(...array_values($lines)))->not->toBeEmpty();

    foreach ($lines as $file => $uses) {
        foreach ($uses as $use) {
            if (str_starts_with($use, './') || str_starts_with($use, 'docker://')) {
                continue;
            }

            expect($use)->toMatch('/^[\w.-]+\/[\w.\/-]+@[0-9a-f]{40}\s+#\s*v\d[\w.-]*$/', $file.': '.$use);
        }
    }
});

it('keeps dependabot watching the pinned github actions', function () {
    $config = (string) file_get_contents(base_path('.github/dependabot.yml'));

    expect($config)->toMatch('/package-ecosystem:\s*["\']?github-actions["\']?/');
});
