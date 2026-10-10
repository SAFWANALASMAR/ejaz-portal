<?php

use App\Support\FileSize;

it('shows a file size in kilobytes or megabytes, or nothing when unknown', function (?int $bytes, ?string $expected) {
    $translator = fn (string $key, array $replace): string => $key.':'.$replace['size'];

    expect(FileSize::display($bytes, $translator))->toBe($expected);
})->with([
    'unknown' => [null, null],
    'empty' => [0, null],
    'tiny rounds up to 1 KB' => [100, 'site.file.size_kb:1'],
    'kilobytes' => [3072, 'site.file.size_kb:3'],
    'megabytes with one decimal' => [2 * 1048576 + 104858, 'site.file.size_mb:2.1'],
]);
