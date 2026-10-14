<?php

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

it('pins every development image to a version', function () {
    preg_match_all('/^\s*image:\s*(\S+)\s*$/m', devCompose(), $matches);

    foreach ($matches[1] as $image) {
        expect($image)->toContain(':')->not->toEndWith(':latest');
    }
});
