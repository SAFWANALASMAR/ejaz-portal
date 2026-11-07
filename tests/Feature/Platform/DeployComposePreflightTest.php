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
        ->filter(fn (string $line) => str_contains($line, '^APP_KEY=') || str_contains($line, "'^[A-Z0-9_]+=\"?<[^>]*>\"?$'"))
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

// قيمة حقيقية فيها < و> (كلمة مرور) لا تُعد مثالاً، والمفتاح المقتبس مقبول (مراجعة #210)
it('accepts a quoted APP_KEY and a real value that contains angle brackets', function () {
    $result = deployPreflight("APP_KEY=\"base64:abc=\"\nDB_PASSWORD=a<b>c\nMAIL_FROM_NAME=\"جمعية إعجاز\"\n");

    expect($result->successful())->toBeTrue($result->errorOutput());
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

/** يشغّل سطري فحص Turnstile من سكربت النشر على ملف .env المعطى (decisions/2026-11-07-v0.15.4-ledger.md). */
function deployTurnstilePreflight(string $env): ProcessResult
{
    $lines = collect(file(base_path('scripts/deploy-compose.sh'), FILE_IGNORE_NEW_LINES))
        ->filter(fn (string $line) => str_contains($line, '^TURNSTILE_SITE_KEY=') || str_contains($line, 'Turnstile غير مضبوط'))
        ->implode("\n");

    $dir = storage_path('framework/testing/deploy-turnstile-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', $env);
    File::put($dir.'/check.sh', "set -euo pipefail\nfail() { echo \"$1\" >&2; exit 1; }\n{$lines}\necho passed\n");

    try {
        return Process::path($dir)->run(['bash', 'check.sh']);
    } finally {
        File::deleteDirectory($dir);
    }
}

// في الإنتاج ترد صفحات الدخول والتسجيل والاستعادة 500 بلا Turnstile بمفتاحيه، فالنشر يُرفض لا يُنبَّه وحده
it('refuses to deploy without Turnstile and both keys', function (string $env) {
    $result = deployTurnstilePreflight($env);

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput())->toContain('Turnstile');
})->with([
    'fake driver' => "BOT_CHALLENGE_DRIVER=fake\nTURNSTILE_SITE_KEY=site\nTURNSTILE_SECRET_KEY=secret\n",
    'empty keys' => "BOT_CHALLENGE_DRIVER=turnstile\nTURNSTILE_SITE_KEY=\nTURNSTILE_SECRET_KEY=\n",
    'missing secret' => "BOT_CHALLENGE_DRIVER=turnstile\nTURNSTILE_SITE_KEY=site\nTURNSTILE_SECRET_KEY=\n",
    'quoted empty keys' => "BOT_CHALLENGE_DRIVER=turnstile\nTURNSTILE_SITE_KEY=\"\"\nTURNSTILE_SECRET_KEY=\"\"\n",
]);

it('deploys with Turnstile and both keys, quoted or not', function (string $env) {
    $result = deployTurnstilePreflight($env);

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and($result->output())->toContain('passed');
})->with([
    'plain' => "BOT_CHALLENGE_DRIVER=turnstile\nTURNSTILE_SITE_KEY=site\nTURNSTILE_SECRET_KEY=secret\n",
    'quoted' => "BOT_CHALLENGE_DRIVER=turnstile\nTURNSTILE_SITE_KEY=\"site\"\nTURNSTILE_SECRET_KEY=\"secret\"\n",
]);

// بروفة الرفع (8 نوفمبر): صورة php-fpm تضبط STOPSIGNAL على SIGQUIT، فلا ينهي Horizon مهامه ويُقتل بعد المهلة والموقع في الصيانة
it('stops horizon and the scheduler with SIGTERM, not the php-fpm image default', function () {
    $compose = File::get(base_path('deploy/compose.production.yaml'));

    foreach (['horizon', 'scheduler'] as $service) {
        preg_match('/^  '.$service.':\n((?:    .*\n|\n)+?)(?=^  \S|\z)/m', $compose, $block);

        expect($block[1] ?? '')->toContain('stop_signal: SIGTERM');
    }
});
