<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/*
| لا طباعة خام في القوالب إلا الاستثناءات المكتوبة في docs/security.md (المراجعة الأمنية بعد v0.14.2).
*/

it('escapes a section heading title holding a script, with or without a highlight, exactly once', function (?string $highlight) {
    $title = 'عنوان <script>alert(1)</script> & "اقتباس"';

    $html = Blade::render('<x-section-heading :highlight="$highlight">{{ $title }}</x-section-heading>', ['title' => $title, 'highlight' => $highlight]);

    expect($html)->not->toContain('<script>')
        ->not->toContain('&amp;lt;')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('&amp; &quot;اقتباس&quot;');

    if ($highlight !== null) {
        expect($html)->toContain('عنوان <span class="text-gradient">'.e($highlight).'</span> &amp; &quot;اقتباس&quot;');
    }
})->with([
    'no highlight' => [null],
    'highlighted script' => ['<script>alert(1)</script>'],
]);

it('keeps the plain heading markup unchanged', function () {
    $html = Blade::render('<x-section-heading highlight="إعجاز">{{ $title }}</x-section-heading>', ['title' => 'مكتبة إعجاز']);

    expect($html)->toContain('مكتبة <span class="text-gradient">إعجاز</span>');
});

it('prints raw output only in the templates documented as exceptions', function () {
    $raw = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn (SplFileInfo $file): bool => rawOutputIn($file->getContents()))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->sort()->values()->all();

    expect($raw)->toBe(['account/security.blade.php', 'components/social-icon.blade.php', 'partials/search-palette.blade.php']);

    $policy = (string) file_get_contents(base_path('docs/security.md'));
    foreach ($raw as $file) {
        expect($policy)->toContain($file);
    }
});

/**
 * في القالب طباعة خام بعد حذف تعليقات Blade: {!! !!}، أو echo أو print في @php، أو وسم PHP مباشر (<?php و<?=).
 * وما لا يلتقطه مكتوب حدّه في docs/security.md.
 */
function rawOutputIn(string $template): bool
{
    $code = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $template);

    return str_contains($code, '{!!')
        // echo وprint عبارتين، لا جزءاً من اسم (heroicon-o-finger-print) ولا متغيراً ولا خاصية
        || preg_match('/@php\b(?:(?!@endphp).)*(?<![\w$>-])(?:echo|print)(?=[\s(])/s', $code) === 1
        || preg_match('/<\?(?:php\b|=)/', $code) === 1;
}

it('catches raw output written as echo in a php block or as a php tag, not only as {!! !!}', function (string $template) {
    expect(rawOutputIn($template))->toBeTrue();
})->with([
    'braces' => ['<p>{!! $html !!}</p>'],
    'echo in @php' => ["@php\n    echo \$html;\n@endphp"],
    'print in inline @php' => ['@php(print $html)'],
    'php tag' => ['<?php echo $html; ?>'],
    'short echo tag' => ['<?= $html ?>'],
]);

it('does not flag escaped output, comments, or a php block without echo', function (string $template) {
    expect(rawOutputIn($template))->toBeFalse();
})->with([
    'escaped' => ['<p>{{ $title }}</p>'],
    'blade comment' => ['{{-- لا {!! !!} هنا --}}'],
    'php block' => ["@php\n    \$echoed = e(\$title);\n@endphp"],
    'icon name with print' => ["@php\n    \$icons = ['heroicon-o-finger-print', \$this->print];\n@endphp"],
]);
