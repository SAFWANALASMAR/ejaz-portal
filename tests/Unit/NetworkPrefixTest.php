<?php

use App\Support\NetworkPrefix;

it('normalizes addresses and unwraps IPv4 written in IPv6 form', function (?string $ip, ?string $expected) {
    expect(NetworkPrefix::normalize($ip))->toBe($expected);
})->with([
    'ipv4' => ['203.0.113.77', '203.0.113.77'],
    'mapped ipv4' => ['::ffff:203.0.113.5', '203.0.113.5'],
    'ipv6' => ['2001:DB8:0:0:1::1', '2001:db8::1:0:0:1'],
    'missing' => [null, null],
    'invalid' => ['not-an-ip', null],
]);

it('computes network prefixes', function (string $ip, int $bits, string $prefix) {
    expect(NetworkPrefix::prefix($ip, $bits))->toBe($prefix);
})->with([
    'ipv4 /24' => ['203.0.113.77', 24, '203.0.113.0/24'],
    'ipv6 /64' => ['2001:db8:abcd:12:1:2:3:4', 64, '2001:db8:abcd:12::/64'],
    'ipv6 /48' => ['2001:db8:abcd:12:1:2:3:4', 48, '2001:db8:abcd::/48'],
    'ipv6 /56' => ['2001:db8:abcd:12ff::1', 56, '2001:db8:abcd:1200::/56'],
]);

it('treats each IPv4 address and each IPv6 /64 as one subscriber', function () {
    expect(NetworkPrefix::subscriber('203.0.113.77'))->toBe('203.0.113.77')
        ->and(NetworkPrefix::subscriber('::ffff:203.0.113.77'))->toBe('203.0.113.77')
        ->and(NetworkPrefix::subscriber('2001:db8:abcd:12:1:2:3:4'))->toBe('2001:db8:abcd:12::/64')
        ->and(NetworkPrefix::subscriber(null))->toBe('unknown');
});

it('tells public addresses from private and reserved ones', function () {
    expect(NetworkPrefix::isPublic('203.0.113.5'))->toBeTrue()
        ->and(NetworkPrefix::isPublic('2001:db8::1'))->toBeTrue()
        ->and(NetworkPrefix::isPublic('10.0.0.1'))->toBeFalse()
        ->and(NetworkPrefix::isPublic('172.18.0.4'))->toBeFalse()
        ->and(NetworkPrefix::isPublic('127.0.0.1'))->toBeFalse()
        ->and(NetworkPrefix::isPublic('::1'))->toBeFalse();
});
