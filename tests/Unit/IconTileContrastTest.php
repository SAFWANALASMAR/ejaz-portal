<?php

use App\Support\IconTones;

/*
| مربع الأيقونة الموحد (x-icon-tile — أمر التصور النهائي، النسخة 63، البند R5): أربع درجات زرقاء، الرسم على طرفي كل تدرج بتباين
| 3:1 فأكثر (WCAG 2.2 للمكونات غير النصية): #1F4565 على الفاتحتين، والأبيض على المتوسطة والغامقة. والقيم نفسها في
| resources/css/concept.css، للدرجة الصريحة (data-shade) وللتعاقب بموضع البطاقة (nth-child). وrelativeLuminance وcontrastRatio
| من tests/Unit/BrandContrastTest.php.
*/

function conceptCss(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
}

/**
 * @return array{0: string, 1: string, 2: string}
 */
function tileShade(string $shade): array
{
    preg_match("/\.icon-tile\[data-shade='".preg_quote($shade, '/')."'\]\s*\{\s*--tile-from:\s*(#[0-9a-fA-F]{6});\s*--tile-to:\s*(#[0-9a-fA-F]{6});\s*--tile-glyph:\s*(#[0-9a-fA-F]{6});/", conceptCss(), $match);

    return isset($match[3]) ? [$match[1], $match[2], $match[3]] : throw new RuntimeException("Missing shade {$shade}");
}

it('keeps the glyph readable on both ends of every shade', function (string $shade) {
    [$from, $to, $glyph] = tileShade($shade);

    foreach ([$from, $to] as $stop) {
        expect(contrastRatio($glyph, $stop))->toBeGreaterThanOrEqual(3.0, "{$shade} {$stop}");
    }

    // الفاتحتان برسم أزرق داكن، والمتوسطة والغامقة برسم أبيض
    expect(strtolower($glyph))->toBe(in_array($shade, IconTones::WHITE_GLYPH, true) ? '#ffffff' : '#1f4565');
})->with(IconTones::ORDER);

it('cycles the four shades by card position with the same colours as the explicit shades', function () {
    expect(IconTones::ORDER)->toBe(['pale', 'light', 'medium', 'dark'])
        ->and(array_map(IconTones::at(...), range(0, 5)))->toBe(['pale', 'light', 'medium', 'dark', 'pale', 'light']);

    foreach (IconTones::ORDER as $position => $shade) {
        preg_match('/:not\(:only-child\):nth-child\(4n \+ '.($position + 1).'\)\s*\{\s*--gt-from:\s*(#[0-9a-f]{6});\s*--gt-to:\s*(#[0-9a-f]{6});\s*--gt-glyph:\s*(#[0-9a-f]{6});/', conceptCss(), $cycle);

        expect(array_slice($cycle, 1))->toBe(tileShade($shade), $shade);
    }
});

it('leaves no violet or green tile, card or service tone in the concept styles', function () {
    expect(conceptCss())->not->toMatch("/data-tone='(violet|green|lilac)'/")
        ->and(conceptCss())->not->toMatch('/#(5d2a92|287a37|7a4bb0|2f8a3f|9068c4)\b/i');
});
