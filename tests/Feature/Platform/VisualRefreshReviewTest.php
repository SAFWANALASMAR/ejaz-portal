<?php

use App\Enums\AssemblyFileCategory;
use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use App\Enums\OpeningKind;
use App\Enums\SiteIcon;
use App\Jobs\FetchHomeVideoThumbnail;
use App\Support\ContentCache;
use App\Support\Icon;
use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| مراجعة المشرف على #95 (30 أكتوبر 2026): المهمة الخمس، والمنخفضة السريعة. والسلوك في المتصفح مختبر في
| tests/browser/visual-review.spec.mjs.
*/

$css = fn (string $file): string => (string) file_get_contents(resource_path('css/'.$file));

it('gives the light focus ring to dark sections and to the accent button', function () use ($css) {
    $app = $css('app.css');

    expect($app)->toMatch('/:where\([^)]*\.section-dark[^)]*\) :focus-visible \{\s*@apply outline-accent-light;/')
        ->and($app)->toContain(".bg-accent:focus-visible {\n        @apply outline-primary-dark;")
        ->and($app)->toMatch('/:where\([^)]*\.section-dark[^)]*\) \.bg-accent:focus-visible \{\s*@apply outline-accent-light;/');
});

it('hides skeleton images only once the script has run', function () use ($css) {
    expect($css('visual.css'))->toContain('html[data-motion-ready] .skeleton:not([data-loaded]) img')
        ->not->toMatch('/^\.skeleton:not\(\[data-loaded\]\) img/m')
        ->and((string) file_get_contents(resource_path('js/motion.js')))->toContain("setAttribute('data-motion-ready', '')");
});

it('takes the hidden back-to-top button out of the focus order', function () use ($css) {
    expect($css('visual.css'))->toMatch('/\[data-back-to-top\]:not\(\[data-visible\]\) \{[^}]*visibility: hidden;/');
});

it('runs endless animations only without a reduced-motion preference, and has no endless background glow', function () use ($css) {
    $visual = $css('visual.css');
    $noPreference = (string) str($visual)->after('@media (prefers-reduced-motion: no-preference) {')->before("\n}\n");
    $outside = str_replace($noPreference, '', $visual);

    expect($noPreference)->toContain('animation: ken-burns')->toContain('animation: skeleton')
        ->and($outside)->not->toContain('animation: ken-burns')->not->toContain('animation: skeleton')
        // الحلقتان المستمرتان وحدهما (ken-burns وskeleton)، وكلتاهما داخل الكتلة
        ->and($visual)->not->toContain('section-glow')
        ->and(substr_count($visual, 'infinite'))->toBe(2)
        ->and(substr_count($noPreference, 'infinite'))->toBe(2);
});

it('takes colours from the identity tokens, not hard-coded values', function () use ($css) {
    expect($css('visual.css'))->not->toMatch('/rgb\(|#[0-9a-fA-F]{3,6}\b/')
        ->and($css('app.css'))->toContain('--color-highlight: var(--color-accent);')
        ->toContain('--color-highlight-on-dark: var(--color-accent-light);');
});

it('has a Phosphor icon for every icon name the site can show', function () {
    $names = collect(SiteIcon::cases())->map(fn (SiteIcon $icon): string => $icon->value)
        ->merge(collect([AssemblyFileCategory::cases(), DocumentCategory::cases(), LibrarySection::cases(), OpeningKind::cases()])
            ->flatten()->map(fn ($case): string => $case->icon()))
        ->unique();

    $missing = $names->reject(function (string $name): bool {
        try {
            app(Factory::class)->svg(Icon::phosphor($name));

            return true;
        } catch (Throwable) {
            return false;
        }
    });

    expect($missing->values()->all())->toBe([]);
});

it('rejects a thumbnail path or job for a value that is not a YouTube id', function () {
    Storage::fake('public');
    Http::fake();

    expect(fn () => FetchHomeVideoThumbnail::path('../../x/abcd'))->toThrow(InvalidArgumentException::class);

    (new FetchHomeVideoThumbnail('../../etc/pwd'))->handle();

    Http::assertNothingSent();
});

it('skips a thumbnail whose declared size is too large', function () {
    Storage::fake('public');
    Http::fake(['i.ytimg.com/*' => Http::response('', 200, ['Content-Length' => (string) (50 * 1024 * 1024)])]);

    (new FetchHomeVideoThumbnail('ccccccccccc'))->handle();

    expect(Storage::disk('public')->exists(FetchHomeVideoThumbnail::path('ccccccccccc')))->toBeFalse();
});

it('clears only the home cache key when the home video image arrives', function () {
    Storage::fake('public');
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagejpeg($image);
    Http::fake(['i.ytimg.com/*' => Http::response((string) ob_get_clean())]);
    $other = ContentCache::remember('other', fn (): string => 'kept');
    ContentCache::remember('home', fn (): string => 'old');

    (new FetchHomeVideoThumbnail('ddddddddddd'))->handle();

    expect(Storage::disk('public')->exists(FetchHomeVideoThumbnail::path('ddddddddddd')))->toBeTrue()
        ->and(ContentCache::remember('other', fn (): string => 'rebuilt'))->toBe($other)
        ->and(ContentCache::remember('home', fn (): string => 'new'))->toBe('new');
});
