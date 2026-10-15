<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| بيئة التطوير (compose.yaml) لا تُفتح على الشبكة المحلية (مهمة الإصلاحات الأمنية ١، البند ٦): كل منفذ
| على 127.0.0.1، وRedis بكلمة مرور، ولا صورة بوسم latest.
*/

function devCompose(): string
{
    return (string) file_get_contents(base_path('compose.yaml'));
}

it('publishes every development port on this machine only', function () {
    preg_match_all('/^\s*-\s*"([^"]+:\d+)"\s*$/m', devCompose(), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $mapping) {
        expect($mapping)->toStartWith('127.0.0.1:');
    }
});

it('requires a password for redis in development', function () {
    $compose = devCompose();

    expect($compose)->toContain('--requirepass')
        ->and($compose)->toContain('REDIS_PASSWORD: ${REDIS_PASSWORD:?')
        ->and((string) file_get_contents(base_path('scripts/setup.sh')))->toContain('REDIS_PASSWORD=$redis_password');
});

it('generates a redis password only when it is missing or empty, and only warns about a short one', function (?string $given, bool $generated, bool $warned) {
    // مقطع كلمة مرور Redis من سكربت التجهيز، يُشغَّل على .env مؤقت
    preg_match('/^# كلمة مرور Redis عشوائية.*?^fi$/msu', (string) file_get_contents(base_path('scripts/setup.sh')), $snippet);
    $directory = sys_get_temp_dir().'/setup-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/.env', "APP_NAME=x\n".($given === null ? '' : "REDIS_PASSWORD={$given}\n"));

    $result = Process::path($directory)->input($snippet[0])->run('bash');
    preg_match('/^REDIS_PASSWORD=(.*)$/m', (string) file_get_contents($directory.'/.env'), $now);
    File::deleteDirectory($directory);

    expect($result->successful())->toBeTrue()
        ->and($now[1] !== $given)->toBe($generated)
        ->and(str_contains($result->output(), 'تنبيه'))->toBe($warned);

    if ($generated) {
        expect($now[1])->toMatch('/^[A-Za-z0-9]{32}$/');
    }
})->with([
    'missing' => [null, true, false],
    'empty' => ['', true, false],
    'null from the old example' => ['null', true, false],
    'short' => ['Ab1!x', false, true],
    'strong with symbols' => ['Strong-Pass_With#Symbols2026', false, false],
]);

it('pins every development image to a version', function () {
    preg_match_all('/^\s*image:\s*(\S+)\s*$/m', devCompose(), $matches);

    foreach ($matches[1] as $image) {
        expect($image)->toContain(':')->not->toEndWith(':latest');
    }
});
