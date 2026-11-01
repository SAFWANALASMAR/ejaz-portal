<?php

/*
| الأزرق وحده (أمر التصور النهائي، النسخة 63 — البند G1): لا بنفسجي ولا أخضر في الأنماط ولا القوالب ولا سمة لوحة الإدارة ولا
| الرسم المؤقت. يُفحص كل لون مكتوب (#hex وrgb) بدرجته، وكل صنف Tailwind من عائلتيهما. والاستثناءات ثلاثة لا غير: أحمر يوتيوب،
| وحالة «مرفوض»، وأسود بطاقة X، وليست من العائلتين.
*/

/**
 * @return iterable<string, string> المسار ← المحتوى
 */
function paletteSources(): iterable
{
    $root = dirname(__DIR__, 2);

    foreach (['resources/css', 'resources/views', 'resources/js', 'app/Filament', 'app/Providers', 'public/images/placeholders'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (preg_match('/\.(css|php|js|svg)$/', $file->getFilename()) === 1) {
                yield $directory.'/'.$file->getFilename() => (string) file_get_contents($file->getPathname());
            }
        }
    }
}

/**
 * درجة اللون (0–360) وتشبّعه وإضاءته.
 *
 * @return array{0: float, 1: float, 2: float}
 */
function hsl(int $r, int $g, int $b): array
{
    [$r, $g, $b] = [$r / 255, $g / 255, $b / 255];
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $light = ($max + $min) / 2;

    if ($max === $min) {
        return [0.0, 0.0, $light];
    }

    $delta = $max - $min;
    $saturation = $light > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
    $hue = match ($max) {
        $r => fmod(($g - $b) / $delta + 6, 6),
        $g => ($b - $r) / $delta + 2,
        default => ($r - $g) / $delta + 4,
    };

    return [$hue * 60, $saturation, $light];
}

it('writes no violet or green colour anywhere in the styles, templates, admin theme or placeholder art', function () {
    $offenders = [];

    foreach (paletteSources() as $path => $content) {
        preg_match_all('/#([0-9a-f]{6}|[0-9a-f]{3})\b|rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/i', $content, $colours, PREG_SET_ORDER);

        foreach ($colours as $colour) {
            if (($colour[1] ?? '') !== '') {
                $hex = strlen($colour[1]) === 3 ? implode('', array_map(fn (string $c): string => $c.$c, str_split($colour[1]))) : $colour[1];
                [$r, $g, $b] = array_map(hexdec(...), str_split($hex, 2));
            } else {
                [$r, $g, $b] = [(int) $colour[2], (int) $colour[3], (int) $colour[4]];
            }

            [$hue, $saturation, $light] = hsl($r, $g, $b);

            if ($saturation > 0.2 && $light > 0.08 && $light < 0.95 && (($hue > 70 && $hue < 175) || ($hue > 255 && $hue < 330))) {
                $offenders[] = $path.': '.$colour[0];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('uses no violet, purple, green, emerald, lime, teal or fuchsia Tailwind class in the templates', function () {
    $offenders = [];

    foreach (paletteSources() as $path => $content) {
        if (preg_match_all('/\b(?:[a-z-]+:)*(?:bg|text|border|ring|from|to|via|fill|stroke|outline|decoration|shadow|divide|accent)-(?:green|emerald|violet|purple|lime|teal|fuchsia)-\d{2,3}\b/', $content, $classes) > 0) {
            array_push($offenders, ...array_map(fn (string $class): string => $path.': '.$class, $classes[0]));
        }
    }

    expect($offenders)->toBe([]);
});
