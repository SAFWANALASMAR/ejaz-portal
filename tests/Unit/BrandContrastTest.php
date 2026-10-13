<?php

/**
 * تباين ألوان الهوية (WCAG 2.1 AA) من الرموز نفسها في resources/css/app.css، فلا يُغيَّر لون
 * دون أن يبقى كل زوج مستعمل مقروءاً. docs/design-system.md
 */
function brandToken(string $name): string
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    preg_match('/--color-'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})\s*;/', $css, $match);

    return $match[1] ?? throw new RuntimeException("Missing colour token {$name}");
}

function relativeLuminance(string $hex): float
{
    $channels = array_map(function (string $pair): float {
        $c = hexdec($pair) / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(substr($hex, 1), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrastRatio(string $a, string $b): float
{
    $l = [relativeLuminance($a), relativeLuminance($b)];

    return (max($l) + 0.05) / (min($l) + 0.05);
}

it('uses the official colours of the brand guidelines', function () {
    expect(brandToken('brand'))->toBe('#3E8ACA')
        ->and(brandToken('secondary'))->toBe('#287A37')
        ->and(brandToken('accent'))->toBe('#5D2A92');
});

it('keeps normal text readable on every background it is used on', function (string $text, string $background) {
    expect(contrastRatio(brandToken($text), brandToken($background)))->toBeGreaterThanOrEqual(4.5);
})->with([
    ['ink', 'white'],
    ['ink', 'surface'],
    ['ink', 'primary-soft'],
    ['muted', 'white'],
    ['muted', 'surface'],
    ['primary', 'white'],
    ['primary', 'surface'],
    ['primary', 'primary-soft'],
    ['white', 'primary'],
    ['white', 'primary-dark'],
    ['white', 'secondary'],
    ['white', 'accent'],
    ['accent', 'surface'],
    ['secondary', 'white'],
    ['accent-light', 'primary-dark'],
]);

it('keeps large text, icons and borders visible where only those are used', function (string $foreground, string $background) {
    // 3:1 للنص الكبير والأيقونات والحدود: الأزرق الرسمي على الأبيض، وأيقونات القائمة الجانبية على الأزرق
    expect(contrastRatio(brandToken($foreground), brandToken($background)))->toBeGreaterThanOrEqual(3.0);
})->with([
    ['brand', 'white'],
    ['accent-light', 'primary'],
]);

it('never puts dark text on the purple accent or the purple accent on the dark blues in the templates', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            // في سلسلة الأصناف نفسها: خلفية بنفسجية بنص داكن، أو نص بنفسجي على خلفية زرقاء داكنة
            $classes = "[^\"'\\n]*";
            if (preg_match("/[\"']({$classes}\bbg-accent\b(?![\/-]){$classes}\btext-(ink|muted)\b|{$classes}\btext-(ink|muted)\b{$classes}\bbg-accent\b(?![\/-]))/", $line)
                || preg_match("/[\"']{$classes}\bbg-primary(-dark)?\b{$classes}\btext-accent\b(?!-)/", $line)) {
                $offenders[] = $file->getFilename().':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
