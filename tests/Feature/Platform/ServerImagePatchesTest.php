<?php

/*
| رقع صور الخادم (أمر المشرف، 26 أكتوبر 2026 — docs/security.md «تحديث PHP وnginx»): صورتا PHP على رقعة مثبّتة 8.4.27 فأحدث
| لا وسم متحرك، ورفع تقدم الجلسة مطفأ في إعدادي الإنتاج والتطوير، وnginx على فرع مستقر مدعوم. والفحص الآلي يبني
| الصور ويتحقق منها فعلاً.
*/

const MINIMUM_PHP_PATCH = '8.4.27';

dataset('php images', [
    'production' => ['deploy/docker/Dockerfile'],
    'development' => ['docker/php/Dockerfile'],
]);

it('pins the PHP base image to the security patch or later', function (string $dockerfile) {
    $content = (string) file_get_contents(base_path($dockerfile));

    preg_match_all('/^FROM php:(\S+)/m', $content, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $tag) {
        // رقم كامل بثلاثة أجزاء، لا 8.4 ولا latest
        expect($tag)->toMatch('/^\d+\.\d+\.\d+-/');

        $version = strstr($tag, '-', true);

        expect($version)->not->toBeFalse()
            ->and(version_compare((string) $version, MINIMUM_PHP_PATCH, '>='))->toBeTrue();
    }
})->with('php images');

it('turns session upload progress off in the production and development settings', function (string $ini) {
    expect((string) file_get_contents(base_path($ini)))->toMatch('/^session\.upload_progress\.enabled\s*=\s*0\s*$/m');
})->with(['deploy/php/production.ini', 'docker/php/php.ini']);

it('checks the built images in CI, pulling fresh layers', function () {
    $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

    expect($workflow)->toContain('docker build --pull -t ejaz-php-dev docker/php')
        ->toContain('docker build --pull --target base')
        ->toContain('docker build --pull --target app -t ejaz-app')
        ->toContain('docker run --rm --entrypoint php ejaz-app -r "$check"')
        ->toContain('version_compare(PHP_VERSION, "'.MINIMUM_PHP_PATCH.'", "<")')
        ->toContain('session.upload_progress.enabled')
        ->toContain('deploy/php/production.ini:/usr/local/etc/php/conf.d/99-ejaz.ini');
});

it('serves the site from a supported nginx branch, checked in CI with the production config', function () {
    preg_match('/^FROM nginx:(\d+)\.(\d+)(\S*) AS web$/m', (string) file_get_contents(base_path('deploy/docker/Dockerfile')), $match);

    // 1.27 فرع منتهٍ بلا رقع، والمستقر 1.30 (أمر المشرف، 26 أكتوبر 2026). والفرع الأحدث يُقبل
    expect($match)->not->toBeEmpty()
        ->and(version_compare($match[1].'.'.$match[2], '1.30', '>='))->toBeTrue()
        ->and($match[3])->toBe('-alpine');

    expect((string) file_get_contents(base_path('.github/workflows/ci.yml')))
        ->toContain('bash tests/nginx/run.sh "$image"');

    expect((string) file_get_contents(base_path('tests/nginx/run.sh')))
        ->toContain('nginx -t')
        ->toContain('content-security-policy')
        ->toContain('strict-transport-security');
});
