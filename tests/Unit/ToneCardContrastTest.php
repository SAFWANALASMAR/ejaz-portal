<?php

/*
| البطاقة الملونة (.tone-card — أمر التصور النهائي، النسخة 63، البند G2): تدرجان يتعاقبان بين البطاقات، داكن بنص أبيض ثم فاتح بنص
| أزرق داكن. والنص العادي على كل طرف وعلى منتصف كل تدرج بتباين 4.5:1 فأكثر (WCAG 2.2 AA)، من القيم نفسها في concept.css.
| وrelativeLuminance وcontrastRatio من tests/Unit/BrandContrastTest.php.
*/

/**
 * @return array{0: string, 1: string, 2: string, 3: string}
 */
function toneCard(string $tone): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    preg_match("/\.tone-card\[data-tone='".$tone."'\]\s*\{\s*--tc-from:\s*(#[0-9a-f]{6});\s*--tc-via:\s*(#[0-9a-f]{6});\s*--tc-to:\s*(#[0-9a-f]{6});\s*--tc-text:\s*(#[0-9a-f]{6});/", $css, $match);

    return isset($match[4]) ? array_slice($match, 1) : throw new RuntimeException("Missing tone card {$tone}");
}

it('keeps the card text readable on every stop of both card gradients', function (string $tone) {
    [$from, $via, $to, $text] = toneCard($tone);

    foreach ([$from, $via, $to] as $stop) {
        expect(contrastRatio($text, $stop))->toBeGreaterThanOrEqual(4.5, "{$tone} {$stop}");
    }
})->with(['dark', 'light']);

it('alternates the same two gradients by card position', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');

    foreach (['odd' => 'dark', 'even' => 'light'] as $position => $tone) {
        preg_match('/:is\(li, article, \.tone-card, \[data-card\]\):not\(:only-child\):nth-child\('.$position.'\)\s*\{\s*--tcv-from:\s*(#[0-9a-f]{6});\s*--tcv-via:\s*(#[0-9a-f]{6});\s*--tcv-to:\s*(#[0-9a-f]{6});\s*--tcv-text:\s*(#[0-9a-f]{6});/', $css, $cycle);

        expect(array_slice($cycle, 1))->toBe(toneCard($tone), $position);
    }
});
