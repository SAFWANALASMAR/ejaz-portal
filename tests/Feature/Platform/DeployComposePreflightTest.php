<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| فحوص ما قبل النشر في scripts/deploy-compose.sh (decisions/2026-11-06-deploy-readiness.md): لا نشر بلا APP_KEY،
| ولا بقيمة مثال بين <...> باقية في .env. وفحص /up إلى الخادم نفسه لا إلى ما في DNS قبل نقل النطاق.
*/

/** يشغّل سطري فحص APP_KEY وقيم المثال من سكربت النشر على ملف .env المعطى، ويرد النتيجة. */
function deployPreflight(string $env): ProcessResult
{
    $lines = collect(file(base_path('scripts/deploy-compose.sh'), FILE_IGNORE_NEW_LINES))
        ->filter(fn (string $line) => str_contains($line, '^APP_KEY=') || str_contains($line, "'^[^#]*=.*<[^>]*>'"))
        ->implode("\n");

    $dir = storage_path('framework/testing/deploy-preflight-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', $env);
    File::put($dir.'/check.sh', "set -euo pipefail\nfail() { echo \"$1\" >&2; exit 1; }\n{$lines}\necho passed\n");

    try {
        return Process::path($dir)->run(['bash', 'check.sh']);
    } finally {
        File::deleteDirectory($dir);
    }
}

it('passes a filled .env, ignoring placeholders in comments', function () {
    $result = deployPreflight("APP_KEY=base64:abc=\n# MAIL_HOST=<مزود البريد>\nMAIL_HOST=smtp.office365.com\n");

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and($result->output())->toContain('passed');
});

it('refuses a missing APP_KEY', function () {
    $result = deployPreflight("APP_KEY=\nMAIL_HOST=smtp.office365.com\n");

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput())->toContain('APP_KEY');
});

it('refuses a placeholder left in .env and names it', function () {
    $result = deployPreflight("APP_KEY=base64:abc=\nMAIL_HOST=<مزود البريد>\n");

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput())->toContain('MAIL_HOST');
});

it('checks /up on this server, and builds the backup image with the release', function () {
    $script = File::get(base_path('scripts/deploy-compose.sh'));

    expect($script)->toContain('--resolve "${HOST}:443:127.0.0.1"')
        ->toContain('build --pull app web backup');
});
