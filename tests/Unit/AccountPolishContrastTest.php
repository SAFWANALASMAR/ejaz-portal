<?php

/*
| ألوان حساب المستفيد (resources/css/account-polish.css — الدفعة ٤ من طلب لوحة الإدارة): النص على الترحيب الفاتح وعلى البطاقات الفاتحة
| بتباين 4.5:1 فأكثر على كل طرف من تدرجهما (WCAG 2.2 AA)، والأبيض على دائرة السهم ومربع التاريخ. والقيم تُقرأ من الملف نفسه.
| وcontrastRatio من tests/Unit/BrandContrastTest.php.
*/

function accountPolishCss(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/account-polish.css');
}

/**
 * @return list<string>
 */
function accountPolishStops(string $selector): array
{
    preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*?background:\s*linear-gradient\(([^;]+)\);/s', accountPolishCss(), $match);
    preg_match_all('/#[0-9a-f]{6}/i', $match[1] ?? '', $stops);

    return $stops[0] !== [] ? $stops[0] : throw new RuntimeException("Missing gradient for {$selector}");
}

function accountPolishValue(string $selector, string $property): string
{
    preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*?'.preg_quote($property, '/').':\s*(#[0-9a-f]{6});/is', accountPolishCss(), $match);

    return $match[1] ?? throw new RuntimeException("Missing {$property} in {$selector}");
}

it('keeps the welcome title and intro readable on every stop of the light welcome', function () {
    $stops = accountPolishStops('.acct-welcome');

    expect($stops)->toHaveCount(3);

    foreach ([accountPolishValue('.acct-welcome-title', 'color'), accountPolishValue('.acct-welcome-intro', 'color')] as $text) {
        foreach ($stops as $stop) {
            expect(contrastRatio($text, $stop))->toBeGreaterThanOrEqual(4.5, "{$text} on {$stop}");
        }
    }
});

it('keeps the card text and its muted line readable on every stop of the light card', function () {
    $card = '[data-account] .tone-card';
    $stops = [accountPolishValue($card, '--tc-from'), accountPolishValue($card, '--tc-via'), accountPolishValue($card, '--tc-to')];

    foreach ([accountPolishValue($card, '--tc-text'), accountPolishValue($card, '--tc-muted')] as $text) {
        foreach ($stops as $stop) {
            expect(contrastRatio($text, $stop))->toBeGreaterThanOrEqual(4.5, "{$text} on {$stop}");
        }
    }
});

it('keeps white readable on the arrow and date gradient', function () {
    foreach (accountPolishStops('.acct-date') as $stop) {
        expect(contrastRatio('#ffffff', $stop))->toBeGreaterThanOrEqual(4.5, $stop);
    }
});

it('uses blue only: every colour in the file is from the blue scale or white', function () {
    preg_match_all('/#([0-9a-f]{6})\b/i', accountPolishCss(), $hexes);

    foreach (array_unique($hexes[1]) as $hex) {
        [$r, $g, $b] = array_map(hexdec(...), str_split($hex, 2));
        // أزرق أو أبيض: الأزرق أعلى القنوات أو مساوٍ لها، والأحمر أدناها
        expect($b)->toBeGreaterThanOrEqual(max($r, $g), "#{$hex}")
            ->and($r)->toBeLessThanOrEqual($g, "#{$hex}");
    }
});
