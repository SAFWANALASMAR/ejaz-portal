<?php

use App\Support\IconTones;

/*
| مربعات الأيقونات المتدرجة (x-icon-tile — صفوان 19:18): الأيقونة البيضاء على طرفي كل تدرج بتباين 3:1 فأكثر (WCAG 2.2 للمكونات
| غير النصية)، من القيم نفسها في resources/css/concept.css. والترتيب ثابت بموضع البطاقة. وrelativeLuminance وcontrastRatio من
| tests/Unit/BrandContrastTest.php.
*/

function tileGradient(string $tone): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    preg_match("/\\.icon-tile\\[data-tone='".preg_quote($tone, '/')."'\\]\\s*\\{\\s*--tile-from:\\s*(#[0-9a-fA-F]{6});\\s*--tile-to:\\s*(#[0-9a-fA-F]{6});/", $css, $match);

    return isset($match[2]) ? [$match[1], $match[2]] : throw new RuntimeException("Missing gradient for {$tone}");
}

it('keeps the white icon readable on both ends of every tile gradient', function (string $tone) {
    foreach (tileGradient($tone) as $stop) {
        expect(contrastRatio('#FFFFFF', $stop))->toBeGreaterThanOrEqual(3.0, "{$tone} {$stop}");
    }
})->with(IconTones::ORDER);

it('cycles the tile colours in the approved order by card position', function () {
    expect(IconTones::ORDER)->toBe(['blue', 'violet', 'green', 'navy', 'sky', 'lilac'])
        ->and(array_map(IconTones::at(...), range(0, 7)))->toBe(['blue', 'violet', 'green', 'navy', 'sky', 'lilac', 'blue', 'violet']);
});
