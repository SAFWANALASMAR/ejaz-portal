<?php

use App\Enums\RoleName;
use Illuminate\Support\Facades\Vite;

/**
 * الهوية الرسمية من دليل الهوية: الشعار في الرأس والتذييل، وخط Zain بأوزانه كلها.
 * docs/requirements/12-executive-director-2026-10-10.md (القسم 1)
 */
it('shows the official logo in the header and its white version in the footer', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('/images/brand/logo-horizontal.svg')
        ->toContain('/images/brand/logo-horizontal-white.svg')
        ->toContain('alt="'.e(__('site.name')).'"');
});

it('ships every brand logo file as a vector without embedded images', function (string $file) {
    $svg = (string) file_get_contents(public_path('images/brand/'.$file));

    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<image')
        ->and($svg)->not->toContain('<script')
        ->and($svg)->not->toContain('foreignObject')
        ->and($svg)->not->toContain('javascript:')
        ->and($svg)->not->toMatch('/\son[a-z]+\s*=/i')
        // لا مرجع إلا إلى تعريفات الملف نفسه (#clip-1 وأمثاله)
        ->and($svg)->not->toMatch('/href="(?!#)/');
})->with(['logo-horizontal.svg', 'logo-horizontal-white.svg', 'emblem.svg']);

it('loads the Zain typeface locally in all of its weights and nothing of the temporary font', function () {
    $vite = (string) file_get_contents(base_path('vite.config.js'));
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $panel = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($vite)->toContain("bunny('Zain'")
        ->toContain('weights: [200, 300, 400, 700, 800, 900]')
        ->and($css)->toContain("--font-sans: 'Zain'")
        ->and($panel)->toContain("->font('Zain'");

    foreach ([$vite, $css, $panel] as $source) {
        expect($source)->not->toContain('IBM Plex');
    }
});

// ملاحظات التجربة الشاملة (الخلل 7): كانت كل صفحة تحمّل مسبقاً 12 ملف خط (ستة أوزان)، فيحذّر المتصفح من غير المستعمل
it('preloads only the two weights used at the top of the page, in woff2', function (string $path) {
    // أصول البناء لا خادم Vite (public/hot حين تعمل حاويته)، فبيانات الخطوط من ناتج npm run build كالإنتاج
    Vite::useHotFile(storage_path('framework/testing/no-vite-hot'));
    $file = public_path('build/fonts-manifest.json');

    expect(is_file($file))->toBeTrue('ابنِ الواجهة أولاً (npm run build): '.$file.' غائب');

    $manifest = json_decode((string) file_get_contents($file), true);
    $preloads = collect($manifest['preloads']);

    expect($preloads->pluck('weight')->unique()->sort()->values()->all())->toBe([400, 700])
        ->and($preloads->every(fn (array $preload): bool => str_ends_with($preload['file'], '.woff2')))->toBeTrue();

    $user = userWithRole(RoleName::SuperAdmin);
    $html = (string) $this->actingAs($user)->get($path)->assertOk()->getContent();
    preg_match_all('#<link\b[^>]*\bas="font"[^>]*>#', $html, $links);

    expect($links[0])->not->toBeEmpty()->toHaveCount($preloads->count())
        ->each->toMatch('#href="[^"]*/zain-(400|700)-normal-[^"]+\.woff2"#');
})->with(['/', '/account', '/admin']);
