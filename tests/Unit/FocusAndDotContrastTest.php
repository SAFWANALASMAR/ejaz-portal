<?php

/*
| فحص الوصول 0.15 (docs/reviews/2026-11-02-v0.15-a11y.md): ألوان مؤشرات التركيز ونقاط الدوّار غير النشطة بتباين 3:1 على خلفياتها
| (WCAG 1.4.11)، من القيم نفسها في concept.css وapp.css. والدوال هنا مستقلة بأسماء مسبوقة، فيعمل الملف وحده وبالتوازي.
| وظهور الإطار فعلاً (غير مقصوص ولا ذائب) يقيسه tests/browser/v015-a11y.spec.mjs.
*/

function a11yLuminance(string $hex): float
{
    $channels = array_map(function (string $pair): float {
        $c = hexdec($pair) / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(substr($hex, 1), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function a11yContrast(string $a, string $b): float
{
    $l = [a11yLuminance($a), a11yLuminance($b)];

    return (max($l) + 0.05) / (min($l) + 0.05);
}

function a11yCss(string $file): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/'.$file);
}

function a11yToken(string $name): string
{
    preg_match('/--color-'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})\s*;/', a11yCss('app.css'), $match);

    return $match[1] ?? throw new RuntimeException("Missing colour token {$name}");
}

/**
 * طرفا التدرج ومنتصفه ولون النص في البطاقة الملونة بدرجتها.
 *
 * @return list<string>
 */
function a11yToneCard(string $tone): array
{
    preg_match("/\.tone-card\[data-tone='".$tone."'\]\s*\{\s*--tc-from:\s*(#[0-9a-f]{6});\s*--tc-via:\s*(#[0-9a-f]{6});\s*--tc-to:\s*(#[0-9a-f]{6});\s*--tc-text:\s*(#[0-9a-f]{6});/", a11yCss('concept.css'), $match);

    return isset($match[4]) ? array_slice($match, 1) : throw new RuntimeException("Missing tone card {$tone}");
}

/**
 * اللون $hex بشفافية $alpha فوق $background، كما يرسمه المتصفح لـ color-mix مع transparent.
 */
function a11yOver(string $hex, float $alpha, string $background): string
{
    return '#'.implode('', array_map(
        fn (string $a, string $b): string => sprintf('%02x', (int) round($alpha * hexdec($a) + (1 - $alpha) * hexdec($b))),
        str_split(substr($hex, 1), 2),
        str_split(substr($background, 1), 2),
    ));
}

function a11yDotAlpha(string $selector, string $color): float
{
    $block = (string) strstr(a11yCss('concept.css'), 'فحص الوصول 0.15');
    preg_match('/'.preg_quote($selector, '/').'[^{]*\{\s*background: color-mix\(in srgb, '.preg_quote($color, '/').' (\d+)%, transparent\);/', $block, $match);

    return isset($match[1]) ? $match[1] / 100 : throw new RuntimeException("Missing dot colour for {$selector}");
}

it('draws the focus ring in a colour card in the card text colour, at 3:1 on every gradient stop', function (string $tone) {
    [$from, $via, $to, $text] = a11yToneCard($tone);

    foreach ([$from, $via, $to] as $stop) {
        expect(a11yContrast($text, $stop))->toBeGreaterThanOrEqual(3, "{$tone} {$stop}");
    }
})->with(['dark', 'light']);

it('keeps inactive carousel dots at 3:1 on the page, on white, and on both card gradients', function () {
    $light = a11yDotAlpha('.carousel-dot::before', 'var(--color-primary)');

    foreach ([a11yToken('page'), a11yToken('white')] as $background) {
        expect(a11yContrast(a11yOver(a11yToken('primary'), $light, $background), $background))->toBeGreaterThanOrEqual(3, $background);
    }

    $onCard = a11yDotAlpha('.tone-card .carousel-controls-compact .carousel-dot::before', 'var(--tc-text, var(--color-white))');

    foreach (['dark', 'light'] as $tone) {
        [$from, $via, $to, $text] = a11yToneCard($tone);

        foreach ([$from, $via, $to] as $stop) {
            expect(a11yContrast(a11yOver($text, $onCard, $stop), $stop))->toBeGreaterThanOrEqual(3, "{$tone} {$stop}");
        }
    }
});

it('keeps the default, dark-background and pause-button focus rings at 3:1', function () {
    // الإطار العام على الصفحة والأبيض، والفاتح على الشريط الجانبي الداكن، وإطار زر الإيقاف ملاصق لخلفيته (blue-50)
    expect(a11yContrast(a11yToken('accent'), a11yToken('page')))->toBeGreaterThanOrEqual(3)
        ->and(a11yContrast(a11yToken('accent'), a11yToken('white')))->toBeGreaterThanOrEqual(3)
        ->and(a11yContrast(a11yToken('accent'), '#f2f8fd'))->toBeGreaterThanOrEqual(3)
        ->and(a11yContrast(a11yToken('accent-light'), a11yToken('primary-dark')))->toBeGreaterThanOrEqual(3);
});
