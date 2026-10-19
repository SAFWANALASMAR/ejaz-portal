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

    expect($config)->not->toContain('nonce')
        ->and(preg_match('/location = \/index\.php \{[^}]*add_header/s', $config))->toBe(0);
})->with('nginx configs');
