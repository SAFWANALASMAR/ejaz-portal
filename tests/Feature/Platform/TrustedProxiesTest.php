<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| TRUSTED_PROXIES=* يصدّق رأس X-Forwarded-For من أي مصدر، فتصير حدود الشبكات قابلة للتزوير
| إن لم يكن الخادم معزولاً خلف موزع يستبدل الرأس (docs/security.md). فسكربتا النشر ينبّهان إليها.
*/

/** يشغّل أسطر فحص TRUSTED_PROXIES من سكربت النشر على ملف .env بالقيمة المعطاة، ويرد مخرجاتها. */
function trustedProxiesCheckOutput(string $script, string $envLine): string
{
    $lines = collect(file(base_path($script), FILE_IGNORE_NEW_LINES))
        ->filter(fn (string $line) => str_starts_with(trim($line), 'grep') && str_contains($line, 'TRUSTED_PROXIES'))
        ->implode("\n");

    $dir = storage_path('framework/testing/trusted-proxies-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', "APP_ENV=production\n{$envLine}\n");
    File::put($dir.'/check.sh', "set -euo pipefail\n{$lines}\ntrue\n");

    try {
        $result = Process::path($dir)->run(['bash', 'check.sh']);
        expect($result->successful())->toBeTrue($result->errorOutput());

        return $result->output();
    } finally {
        File::deleteDirectory($dir);
    }
}

dataset('deploy scripts', ['scripts/deploy-compose.sh', 'scripts/deploy.sh']);

it('warns when TRUSTED_PROXIES is the wildcard', function (string $script, string $value) {
    expect(trustedProxiesCheckOutput($script, "TRUSTED_PROXIES={$value}"))
        ->toContain('TRUSTED_PROXIES=*');
})->with('deploy scripts')->with(['*', '"*"', "'*'", '10.0.0.1,*']);

it('does not warn for explicit proxy addresses', function (string $script) {
    expect(trustedProxiesCheckOutput($script, 'TRUSTED_PROXIES=10.0.0.10,10.0.0.11'))
        ->not->toContain('تنبيه');
})->with('deploy scripts');

it('still warns when TRUSTED_PROXIES is empty', function (string $script) {
    expect(trustedProxiesCheckOutput($script, 'TRUSTED_PROXIES='))
        ->toContain('TRUSTED_PROXIES فارغ')
        ->not->toContain('TRUSTED_PROXIES=*');
})->with('deploy scripts');

it('documents the wildcard risk and ships no wildcard in the production example', function () {
    $example = File::get(base_path('.env.production.example'));

    expect($example)->toMatch('/^TRUSTED_PROXIES=$/m')
        ->and($example)->toContain('TRUSTED_PROXIES=*')
        ->and(File::get(config_path('security.php')))->toContain('X-Forwarded-For');
});
