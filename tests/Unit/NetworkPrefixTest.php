<?php

use App\Support\NetworkPrefix;

it('groups addresses by their network', function (?string $ip, string $prefix) {
    expect(NetworkPrefix::of($ip))->toBe($prefix);
})->with([
    'ipv4' => ['203.0.113.77', '203.0.113.0/24'],
    'ipv4 same network' => ['203.0.113.5', '203.0.113.0/24'],
    'ipv6' => ['2001:db8:abcd:12:1:2:3:4', '2001:db8:abcd:12::/64'],
    'missing' => [null, 'unknown'],
    'invalid' => ['not-an-ip', 'unknown'],
]);
