<?php

use App\Rules\SafeUrl;

it('accepts internal paths and full web links', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeTrue();
})->with([
    '/',
    '/about',
    '/library/videos?page=2',
    '/مبادرة-قيم',
    'https://ejaz.sa/contact',
    'http://example.com',
]);

it('rejects links that could run script or leave the site unexpectedly', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeFalse();
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'data:text/html,hello',
    '//evil.com',
    '/\evil.com',
    '/%5Cevil.com',
    '/%2F%2Fevil.com',
    '/%2Fevil.com',
    '/path with space',
    "/path\twith-tab",
    'https://',
    'ftp://example.com',
    'about',
    '',
]);

it('rejects internal paths when only full links are allowed', function () {
    expect(SafeUrl::isSafe('/about', allowInternal: false))->toBeFalse()
        ->and(SafeUrl::isSafe('https://x.com/ejaz', allowInternal: false))->toBeTrue();
});
