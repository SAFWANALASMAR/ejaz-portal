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

it('uses the official blue and only shades of blue for the secondary and accent tokens (G1)', function () {
    // أمر التصور النهائي، النسخة 63: الأزرق وحده، فالثانوي والمميز درجتان منه بدل الأخضر والبنفسجي، وخلفية الصفحة #D6E7F6
    expect(brandToken('brand'))->toBe('#3E8ACA')
        ->and(brandToken('secondary'))->toBe('#24527A')
        ->and(brandToken('accent'))->toBe('#1F5F8F')
        ->and(brandToken('page'))->toBe('#D6E7F6')
        ->and(brandToken('section'))->toBe('#CFE3F4');

    foreach (['secondary', 'secondary-soft', 'accent', 'accent-dark', 'accent-light'] as $token) {
        [$r, $g, $b] = array_map(hexdec(...), str_split(substr(brandToken($token), 1), 2));
        expect($b)->toBeGreaterThan($r, $token)->and($b)->toBeGreaterThanOrEqual($g, $token);
    }
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
    // خلفية الصفحة والأقسام الزرقاء الفاتحة (البند R1)
    ['ink', 'page'],
    ['muted', 'page'],
    ['primary', 'page'],
    ['primary-dark', 'page'],
    ['ink', 'section'],
    ['muted', 'section'],
    ['primary-dark', 'section'],
    ['primary-dark', 'pale'],
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

it('never puts white text on the brand blue, even on hover (3.69:1 is under AA)', function () {
    $offenders = [];
    $classes = "[^\"'\n]*";

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            // في سلسلة الأصناف نفسها: خلفية brand (بحالة أو بلا حالة) ونص أبيض
            if (preg_match("/[\"']({$classes}\b([a-z-]+:)?bg-brand\b(?![\/-]){$classes}\btext-white\b|{$classes}\btext-white\b{$classes}\b([a-z-]+:)?bg-brand\b(?![\/-]))/", $line)) {
                $offenders[] = $file->getFilename().':'.($number + 1);
            }
        }
    }

    // وزر الدخول في الرأس: نصه أبيض، فلونه عند المرور ليس brand
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');
    preg_match('/\.login-pill:hover \{([^}]*)\}/', $css, $hover);

    expect($offenders)->toBe([])
        ->and($hover[1] ?? '')->not->toContain('--color-brand)');
});

/**
 * لون رمز من app.css بصيغة #rrggbb صغيرة: قيمة مباشرة، أو var(--name) يُتتبع حتى قيمته (--blue-* و--mid-* و--color-*).
 */
function cssColour(string $value): string
{
    $value = trim($value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }

    preg_match('/^var\(--([a-z0-9-]+)\)$/', $value, $name) || throw new RuntimeException("Not a colour: {$value}");
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    preg_match('/--'.preg_quote($name[1], '/').':\s*([^;]+);/', $css, $match) || throw new RuntimeException("Missing token {$name[1]}");

    return cssColour($match[1]);
}

/**
 * لون الطبقة الشفافة فوق أرضيتها: $share من $top والباقي من $ground (color-mix في sRGB).
 */
function mixColour(string $top, string $ground, float $share): string
{
    $channels = array_map(
        fn (string $a, string $b): int => (int) round(hexdec($a) * $share + hexdec($b) * (1 - $share)),
        str_split(substr($top, 1), 2),
        str_split(substr($ground, 1), 2),
    );

    return vsprintf('#%02x%02x%02x', $channels);
}

/*
| ملاحظة المدير التنفيذي ٢٩ (2 نوفمبر 2026 — docs/decisions/2026-11-02-director-29-lighter-mid.md): الرأس والتذييل بلونهما الداكن،
| وما بينهما من الأزرق المتوسط إلى أخف منه. والأبيض على كل درجة يُكتب عليها نص صغير 4.5:1 فأكثر.
*/
it('keeps the header and footer colours exactly as they were (director note 29)', function () {
    $app = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    $concept = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');

    expect(cssColour('var(--blue-900)'))->toBe('#1f4565')
        ->and(cssColour('var(--blue-950)'))->toBe('#142e45')
        ->and(cssColour('var(--blue-850)'))->toBe('#24527a')
        ->and(brandToken('primary-dark'))->toBe('#1F4565')
        ->and($app)->toContain('--chrome-gradient: linear-gradient(180deg, #17344f 0%, var(--blue-900) 100%);')
        ->and($concept)->toMatch('/\[data-site-header\] \{\s*background: var\(--chrome-gradient\);/')
        ->and($concept)->toMatch('/\.header-nav \{\s*background: linear-gradient\(180deg, var\(--blue-900\), var\(--blue-850\)\);/')
        ->and($concept)->toMatch('/\.site-footer \{\s*background: var\(--blue-900\);/');
});

it('runs the middle of the page from the mid blue to lighter, white text readable on every stop', function (string $token, string $hex) {
    expect(cssColour("var(--{$token})"))->toBe($hex)
        ->and(contrastRatio('#ffffff', $hex))->toBeGreaterThanOrEqual(4.5);
})->with([
    ['mid-from', '#2e6898'],
    ['mid-via', '#3171a5'],
    ['mid-to', '#3476ac'],
    // الشريط الجانبي في الحساب
    ['blue-850', '#24527a'],
]);

it('keeps the tinted labels on the lighter middle readable', function () {
    $white = '#ffffff';

    // سطر البطل الثاني #DCEBF8 نص كبير (24 بكسل فأكثر) على أفتح طرفي البطل: 3:1
    expect(contrastRatio('#dcebf8', cssColour('var(--mid-to)')))->toBeGreaterThanOrEqual(3.0)
        // شارة البطل وكبسولات العودة: الأزرق المتوسط 92% فوق أسوأ ما تحتها (صورة بيضاء في أعلى البطل)
        ->and(contrastRatio($white, mixColour(cssColour('var(--mid-from)'), $white, 0.92)))->toBeGreaterThanOrEqual(4.5)
        // العنصر النشط في الشريط الجانبي: أبيض 15% فوق #24527A
        ->and(contrastRatio($white, mixColour($white, cssColour('var(--blue-850)'), 0.15)))->toBeGreaterThanOrEqual(4.5)
        // الأبيض على الأزرق الرسمي #3E8ACA يفشل للنص الصغير، فلا يُكتب عليه نص في الوسط (طرف البطل عند الإطار الدائري وحده)
        ->and(contrastRatio($white, cssColour('var(--blue-600)')))->toBeLessThan(4.5);
});

it('writes full white, not translucent or pale, on the lighter heroes and dark sections (review of #136)', function () {
    $white = '#ffffff';
    $views = dirname(__DIR__, 2).'/resources/views/';

    // الأبيض 90% فوق أفتح طرفي البطل 4.3:1، والأبيض الكامل 4.85:1
    expect(contrastRatio(mixColour($white, cssColour('var(--mid-to)'), 0.9), cssColour('var(--mid-to)')))->toBeLessThan(4.5)
        ->and(contrastRatio($white, cssColour('var(--mid-to)')))->toBeGreaterThanOrEqual(4.5)
        // .section-dark: المتوسط بدائرتين باهتتين، الرسمي 35% والمميز 35%، والأبيض على أفتحهما
        ->and(contrastRatio($white, mixColour(brandToken('brand'), cssColour('var(--mid-from)'), 0.35)))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastRatio($white, mixColour(brandToken('accent'), cssColour('var(--mid-from)'), 0.35)))->toBeGreaterThanOrEqual(4.5)
        // #BFE0FA على المتوسط 4.3:1 لا يكفي للنص الصغير
        ->and(contrastRatio(cssColour('var(--blue-200)'), cssColour('var(--mid-from)')))->toBeLessThan(4.5);

    expect((string) file_get_contents($views.'components/hero-slider.blade.php'))->not->toContain('text-white/90')
        ->and((string) file_get_contents($views.'components/page-hero.blade.php'))->not->toContain('text-white/90')
        ->and((string) file_get_contents($views.'components/section-heading.blade.php'))->toContain('[.section-dark_&]:text-white"')
        ->and((string) file_get_contents($views.'home/partials/shortcuts.blade.php'))->not->toContain('concept-pale');
});

it('keeps the dark icon shade of the header and footer at #1F4565 while the content one is lighter (review of #136)', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/concept.css');

    expect($css)->toMatch('/:is\(\[data-site-header\], \.site-footer\) :is\(li, article, \.group, \[data-card\]\):not\(:only-child\):nth-child\(4n \+ 4\) \{\s*--gt-to: #1f4565;/')
        ->and($css)->toMatch("/:is\(\[data-site-header\], \.site-footer\) \.icon-tile\[data-shade='dark'\] \{\s*--tile-to: #1f4565;/");
});
