<?php

use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\ContentCache;
use Database\Seeders\MainMenuSevenSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
| الأساس المشترك للنسخة 63 من التصور (أمر التصور النهائي، المرحلة أ — docs/decisions/2026-10-31-visual-refresh-2.md): القائمة بسبعة
| عناصر، والنشط «الخدمات الإلكترونية» في البرامج والمسابقات، والتذييل ثلاثة مربعات، ونصوص الرأس والتذييل من اللوحة، والشريط
| المتحرك، وانتقال الصفحات بالشعار.
*/

const SEVEN = ['الرئيسية', 'عن الجمعية', 'مكتبة إعجاز', 'المركز الإعلامي', 'الحوكمة', 'الخدمات الإلكترونية', 'تواصل معنا'];

/**
 * @return list<string>
 */
function headerLabels(string $html): array
{
    preg_match('/<nav data-main-nav.*?<\/nav>/s', $html, $nav);
    preg_match_all('/<span class="truncate">([^<]+)<\/span>/', $nav[0] ?? '', $labels);

    return $labels[1];
}

it('reshapes a menu built before the seven items without touching what the editor changed', function () {
    // القائمة كما بناها MenuSeeder القديم: تسعة عناصر، و«الدورات والبرامج» بثلاثة فرعية
    $top = fn (string $label, string $url, int $sort, array $extra = []) => MenuItem::create(['label' => $label, 'url' => $url, 'sort' => $sort, ...$extra]);
    $top('الرئيسية', '/', 0);
    $top('عن الجمعية', '/about', 1);
    $programs = $top('الدورات والبرامج', '/programs', 2);
    foreach ([['الدورات والبرامج', '/programs'], ['المسابقات', '/competitions'], ['خدماتنا الإلكترونية', '/services']] as $sort => [$label, $url]) {
        MenuItem::create(['parent_id' => $programs->id, 'label' => $label, 'url' => $url, 'sort' => $sort]);
    }
    $top('المبادرات', '/initiatives', 3);
    $top('المركز الإعلامي', '/media', 4);
    $top('مكتبة إعجاز', '/library', 5);
    $top('الحوكمة', '/governance', 6);
    $top('المتجر', '/store', 7, ['opens_in_new_tab' => true]);
    $top('تواصل معنا', '/contact', 8);
    // عنصر أضافه المحرر يبقى ظاهراً بعد السبعة
    $top('البث المباشر', '/live', 9);

    $this->seed(MainMenuSevenSeeder::class);
    $this->seed(MainMenuSevenSeeder::class);

    // بترتيب القاعدة (و«عن الجمعية» لا تظهر في الرأس هنا لأن صفحتها غير منشورة في الاختبار — SiteChromeComposer)
    expect(MenuItem::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort')->pluck('label')->all())->toBe([...SEVEN, 'البث المباشر'])
        ->and(headerLabels($this->get('/')->getContent()))->toBe(array_values(array_diff([...SEVEN, 'البث المباشر'], ['عن الجمعية'])))
        ->and(MenuItem::query()->where('url', '/store')->value('is_visible'))->toBeFalse()
        ->and(MenuItem::query()->where('url', '/initiatives')->value('is_visible'))->toBeFalse()
        ->and(MenuItem::query()->where('parent_id', $programs->id)->where('is_visible', true)->exists())->toBeFalse()
        ->and($programs->fresh()->only(['label', 'url']))->toBe(['label' => 'الخدمات الإلكترونية', 'url' => '/services']);
});

it('marks e-services as the active item on the programs and competitions pages', function (string $path) {
    $this->seed(MenuSeeder::class);

    preg_match('/<nav data-main-nav.*?<\/nav>/s', $this->get($path)->assertOk()->getContent(), $nav);

    expect($nav[0])->toMatch('/aria-current="page"\s+class="nav-capsule">\s*<span data-icon-tile[^>]*>.*?<\/span>\s*<span class="truncate">الخدمات الإلكترونية<\/span>/s')
        ->and(substr_count($nav[0], 'aria-current="page"'))->toBe(1);
})->with(['/programs', '/competitions', '/services']);

it('builds the footer from three boxes with an icon tile beside each title, and no partners in it', function () {
    $this->seed(MenuSeeder::class);
    Page::factory()->create(['slug' => Page::ABOUT]);
    Partner::factory()->create(['name' => 'شريك الاختبار', 'logo' => 'partners/a.png', 'is_published' => true]);
    Setting::put('contact', ['email' => 'info@ejaz.sa', 'phone' => '0550556758', 'address' => 'مكة المكرمة', 'whatsapp' => '+966550556758', 'social' => ['x' => 'https://x.com/ejazsa2025']]);
    ContentCache::flush();

    $footer = (string) str($this->get('/')->assertOk()->getContent())->after('data-site-footer')->before('</footer>');
    preg_match_all('/data-footer-box="(\w+)"/', $footer, $boxes);
    preg_match('/data-footer-box="links".*?<\/nav>/s', $footer, $links);
    preg_match_all('/<span class="truncate">([^<]+)<\/span>/', $links[0], $labels);

    expect($boxes[1])->toBe(['contact', 'links', 'accounts'])
        ->and(substr_count($footer, 'class="footer-box-title"><span data-icon-tile'))->toBe(3)
        ->and($footer)->toContain('data-footer-hours')->toContain(e(__('home.sections.footer.hours')))
        ->and($footer)->toContain('info@ejaz.sa')->toContain('0550556758')->toContain('مكة المكرمة')
        ->and($footer)->toContain('href="https://x.com/ejazsa2025"')->toContain('href="https://wa.me/966550556758"')
        ->and($footer)->not->toContain('شريك الاختبار')->not->toContain(e(__('home.sections.footer.supervision')))
        // أسماء مختصرة، و«إنجازاتنا» لا تظهر قبل بناء مسارها
        ->and($labels[1])->toBe(array_values(array_map(fn (string $key): string => __('ui.footer.links.'.$key), ['about', 'programs', 'competitions', 'library', 'media', 'governance', 'initiatives', 'services', 'contact'])));
});

it('takes the licence box and the footer hours and accounts title from the home content screen', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(HomeContentPage::class)
        ->assertFormSet(['header.licensed_by' => __('home.sections.header.licensed_by'), 'footer.hours' => __('home.sections.footer.hours')])
        ->fillForm(['header.license_number' => 'برقم 1234', 'footer.hours' => 'من الأحد إلى الخميس', 'footer.accounts' => 'حساباتنا'])
        ->call('save')
        ->assertHasNoFormErrors();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('برقم 1234')->toContain('من الأحد إلى الخميس')->toContain('حساباتنا')
        ->not->toContain(e(__('home.sections.header.license_number')));
});

it('renders one marquee component, horizontal or vertical, with a hidden copy for the loop and a pause button', function () {
    $horizontal = Blade::render('<x-marquee id="m1" label="شريط"><span>عنصر</span></x-marquee>');
    $vertical = Blade::render('<x-marquee id="m2" label="الأخبار" direction="vertical" height="18rem" :duration="30" :controls="false"><span>خبر</span></x-marquee>');

    expect($horizontal)->toContain('data-marquee data-direction="horizontal" data-loop role="region" aria-label="شريط"')
        ->toContain('data-marquee-copy aria-hidden="true" inert')
        ->toContain('data-ticker-toggle="m1"')->toContain('aria-pressed="false"')
        ->and(substr_count($horizontal, 'عنصر'))->toBe(2)
        ->and($vertical)->toContain('data-direction="vertical"')->toContain('--marquee-height: 18rem;')->toContain('--marquee-duration: 30s;')
        ->not->toContain('data-ticker-toggle');
});

it('puts the logo page transition in every page, persisted across fast navigation and announced while busy', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();
    preg_match('/<div data-page-transition[^>]*>/', $html, $overlay);

    expect($overlay[0])->toContain('hidden')->toContain('role="status"')->toContain('aria-busy="false"')
        ->and($html)->toContain('page-transition-fill')->toContain('page-transition-faint')->toContain('page-transition-shine')
        ->toContain(e(__('ui.transition.loading')))->toContain('logo-mark-white.png');
})->with(['/', '/contact']);

it('gives every section heading the icon tile and one heading level', function () {
    $html = Blade::render('<x-section-heading icon="book-open" size="md">مكتبة إعجاز</x-section-heading>');

    expect($html)->toContain('data-section-heading')->toContain('section-heading-title')
        ->toMatch('/<h2[^>]*>\s*<span data-icon-tile data-variant="gradient"\s+data-shade="medium"/')
        // size لا يغيّر المقاس: مستوى واحد في كل الصفحات
        ->not->toContain('text-2xl')->not->toContain('text-[1.875rem]');
});
