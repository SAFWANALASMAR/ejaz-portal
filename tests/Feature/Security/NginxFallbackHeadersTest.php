<?php

/**
 * رؤوس الحماية من nginx لما لا يمر بـ SecurityHeaders (405 وصفحة الصيانة 503 و400 قبل وسائط المسار، و413 من nginx):
 * يُضاف الرأس متى غاب عن رد التطبيق وحده، فلا يتكرر رأس يضعه التطبيق، ولا CSP بالرمز من nginx.
 * والتجربة الفعلية على nginx في scripts/verify-server.sh (رد 405 برؤوس الحماية، ورأس التطبيق مرة واحدة).
 */
dataset('nginx configs', ['deploy/nginx/ejaz.conf', 'deploy/docker/nginx.conf']);

it('adds every security header only when the application response lacks it', function (string $path, string $header) {
    $config = (string) file_get_contents(base_path($path));
    $upstream = '\$upstream_http_'.str_replace('-', '_', strtolower($header));

    expect(preg_match('/^map '.$upstream.' (\$ejaz_\w+) \{ "" "[^"]+"; default ""; \}$/m', $config, $map))->toBe(1)
        ->and($config)->toContain('add_header '.$header.' '.$map[1].' always;');
})->with('nginx configs')->with([
    'X-Frame-Options',
    'X-Content-Type-Options',
    'Referrer-Policy',
    'Permissions-Policy',
    'Cross-Origin-Opener-Policy',
    'Cross-Origin-Resource-Policy',
    'Strict-Transport-Security',
    'Content-Security-Policy',
]);

it('never sends a nonce policy or a fixed application header from nginx on the PHP location', function (string $path) {
    $config = (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents(base_path($path)));

    // موقع PHP موجود أولاً، فلا ينجح فحص خلوّه بلا فحص
    expect(preg_match('/location = \/index\.php \{[^}]*\}/s', $config, $php))->toBe(1)
        ->and($php[0])->toContain('fastcgi_pass')
        ->and($php[0])->not->toContain('add_header')
        ->and($config)->not->toContain('nonce');
})->with('nginx configs');

it('falls back to the very HSTS value the application sends', function (string $path) {
    $config = (string) file_get_contents(base_path($path));
    preg_match('/^map \$upstream_http_strict_transport_security \$ejaz_\w+ \{ "" "([^"]+)"; default ""; \}$/m', $config, $map);

    expect($map[1] ?? null)->toBe('max-age='.config('security.hsts_max_age').'; includeSubDomains')
        ->and($this->get(str_replace('http://', 'https://', (string) config('app.url')).'/')->headers->get('Strict-Transport-Security'))->toBe($map[1] ?? null);
})->with('nginx configs');
