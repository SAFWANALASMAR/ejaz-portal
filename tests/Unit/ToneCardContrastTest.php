<?php

/*
| البطاقة الملونة (.tone-card — أمر التصور النهائي، النسخة 63، البند G2): تدرجان يتعاقبان بين البطاقات، داكن بنص أبيض ثم فاتح بنص
| أزرق داكن. والنص العادي على كل طرف وعلى منتصف كل تدرج بتباين 4.5:1 فأكثر (WCAG 2.2 AA)، من القيم نفسها في concept.css.
| ومنذ ملاحظة المدير التنفيذي ٢٩ (2 نوفمبر 2026) الداكنة من الأزرق المتوسط إلى أخف منه برموز app.css، لا بلون الرأس والتذييل.
| وrelativeLuminance وcontrastRatio وcssColour من tests/Unit/BrandContrastTest.php.
*/

/**
 * @return array{0: string, 1: string, 2: string, 3: string}
 */
function toneCard(string $tone): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    $value = '(#[0-9a-f]{6}|var\(--[a-z0-9-]+\))';
    preg_match("/\.tone-card\[data-tone='".$tone."'\]\s*\{\s*--tc-from:\s*{$value};\s*--tc-via:\s*{$value};\s*--tc-to:\s*{$value};\s*--tc-text:\s*{$value};/", $css, $match);

    return isset($match[4]) ? array_map(cssColour(...), array_slice($match, 1)) : throw new RuntimeException("Missing tone card {$tone}");
}

it('keeps the card text readable on every stop of both card gradients', function (string $tone) {
    [$from, $via, $to, $text] = toneCard($tone);

    foreach ([$from, $via, $to] as $stop) {
        expect(contrastRatio($text, $stop))->toBeGreaterThanOrEqual(4.5, "{$tone} {$stop}");
    }
})->with(['dark', 'light']);

it('alternates the same two gradients by card position', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    $value = '(#[0-9a-f]{6}|var\(--[a-z0-9-]+\))';

    foreach (['odd' => 'dark', 'even' => 'light'] as $position => $tone) {
        preg_match('/:is\(li, article, \.tone-card, \[data-card\]\):not\(:only-child\):nth-child\('.$position.'\)\s*\{\s*--tcv-from:\s*'.$value.';\s*--tcv-via:\s*'.$value.';\s*--tcv-to:\s*'.$value.';\s*--tcv-text:\s*'.$value.';/', $css, $cycle);

        expect(array_map(cssColour(...), array_slice($cycle, 1)))->toBe(toneCard($tone), $position);
    }
});

it('runs the dark card from the mid blue to lighter, never in the header and footer colour (director note 29)', function () {
    [$from, $via, $to] = toneCard('dark');

    expect([$from, $via, $to])->toBe(['#2e6898', '#3171a5', '#3476ac'])
        ->and(relativeLuminance($via))->toBeGreaterThan(relativeLuminance($from))
        ->and(relativeLuminance($to))->toBeGreaterThan(relativeLuminance($via))
        // والفاتحة «أخف منها»: أغمق طرف فيها أفتح من أفتح طرف في الداكنة
        ->and(relativeLuminance(toneCard('light')[2]))->toBeGreaterThan(relativeLuminance($to));
});

it('keeps the focus outline inside a card visible on both gradients (3:1, outline in the card text colour)', function (string $tone) {
    [$from, $via, $to, $text] = toneCard($tone);

    foreach ([$from, $via, $to] as $stop) {
        expect(contrastRatio($text, $stop))->toBeGreaterThanOrEqual(3, "{$tone} {$stop}");
    }
})->with(['dark', 'light']);

/**
 * متغير من قاعدة البطاقة بدرجتها (--tc-deep و--tc-muted): لونه فوق أرضيته، ويُمزج إن كان color-mix(… N%, transparent).
 */
function toneCardColourOn(string $tone, string $name, string $ground): string
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    preg_match("/\.tone-card\[data-tone='".$tone."'\]\s*\{([^}]*)\}/", $css, $block) || throw new RuntimeException("Missing tone card {$tone}");
    preg_match('/--tc-'.$name.':\s*([^;]+);/', $block[1], $value) || throw new RuntimeException("Missing --tc-{$name} in {$tone}");

    if (preg_match('/^color-mix\(in srgb, (var\(--[a-z0-9-]+\)) (\d+)%, transparent\)$/', trim($value[1]), $mix) === 1) {
        return mixColour(cssColour($mix[1]), $ground, ((int) $mix[2]) / 100);
    }

    return cssColour($value[1]);
}

it('keeps the secondary text readable on every stop of both cards (director note 29)', function (string $tone) {
    [$from, $via, $to, $text] = toneCard($tone);

    foreach ([$from, $via, $to] as $stop) {
        expect(contrastRatio(toneCardColourOn($tone, 'muted', $stop), $stop))->toBeGreaterThanOrEqual(4.5, "{$tone} {$stop}");
    }
})->with(['dark', 'light']);

it('keeps the pill, chip and enter label readable on their deep grounds inside both cards', function (string $tone) {
    [$from, , $to, $text] = toneCard($tone);

    // .tone-pill و.tone-chip و.service-tile-enter: أعمق طرف (--tc-deep) بنسبة 55–85% فوق البطاقة
    foreach ([0.55, 0.6, 0.7, 0.85] as $share) {
        foreach ([$from, $to] as $ground) {
            $deep = mixColour(toneCardColourOn($tone, 'deep', $ground), $ground, $share);
            expect(contrastRatio($text, $deep))->toBeGreaterThanOrEqual(4.5, "{$tone} {$ground} {$share}");
        }
    }
})->with(['dark', 'light']);
