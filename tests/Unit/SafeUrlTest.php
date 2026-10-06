<?php

use App\Rules\SafeUrl;

it('accepts internal paths and secure web links', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeTrue();
})->with([
    '/',
    '/about',
    '/library/videos?page=2',
    '/مبادرة-قيم',
    '/%D9%85%D8%A8%D8%A7%D8%AF%D8%B1%D8%A9',
    'https://ejaz.sa/contact',
    'HTTPS://x.com/ejaz',
]);

it('rejects links that could run script, mislead or leave the site unexpectedly', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeFalse();
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'data:text/html,hello',
    'http://example.com',
    '//evil.com',
    '/\evil.com',
    '/%5Cevil.com',
    '/%2F%2Fevil.com',
    '/%2Fevil.com',
    '/%5C%FFevil.com',
    '/%FF%5Cevil.com',
    'https://ejaz.sa@evil.example',
    'https://user:pass@evil.example',
    '/path with space',
    "/path\twith-tab",
    "/about\u{202E}",
    "/about\u{00A0}",
    "https://ejaz.sa/\u{200B}",
    'https://',
    'ftp://example.com',
    'about',
    '',
]);

it('rejects internal paths when only full links are allowed', function () {
    expect(SafeUrl::isSafe('/about', allowInternal: false))->toBeFalse()
        ->and(SafeUrl::isSafe('https://x.com/ejaz', allowInternal: false))->toBeTrue();
});
