<?php

use App\Support\SaudiPhone;

it('normalizes common Saudi mobile formats', function (string $input) {
    expect(SaudiPhone::normalize($input))->toBe('+966512345678');
})->with([
    '0512345678',
    '512345678',
    '966512345678',
    '+966512345678',
    '00966512345678',
    '051 234 5678',
    '051-234-5678',
    '٠٥١٢٣٤٥٦٧٨',
]);

it('rejects numbers that are not Saudi mobiles', function (?string $input) {
    expect(SaudiPhone::normalize($input))->toBeNull();
})->with([
    null,
    '',
    '0112345678',
    '05123456',
    '+201012345678',
    'abc0512345678',
    '05123456789',
]);

it('displays and masks numbers for people', function () {
    expect(SaudiPhone::display('+966512345678'))->toBe('051 234 5678')
        ->and(SaudiPhone::masked('+966512345678'))->toBe('051 234 **78');
});
