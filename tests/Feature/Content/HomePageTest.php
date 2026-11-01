<?php

use App\Actions\Content\BuildHomePage;
use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
| مقاطع الرئيسية السابقة (البطل والإحصائيات والاختصارات) مخفية افتراضياً منذ ملاحظات المدير التنفيذي (25 أكتوبر 2026)،
| فتُظهر هنا لتُختبر كما كانت. ومقاطع المدير في tests/Feature/Director.
*/
const HOME_LEGACY_SHOWN = ['show_hero' => true, 'show_stats' => true, 'show_shortcuts' => true];

beforeEach(function () {
    Setting::put('home', HOME_LEGACY_SHOWN);
});

it('shows the hero from the site settings', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN,
        'hero_title' => 'عنوان الواجهة',
        'hero_text' => 'نص التعريف بالجمعية',
        'hero_button_label' => 'تعرّف على الجمعية',
        'hero_button_url' => '/about',
    ]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['<h1', 'عنوان الواجهة'], false)
        ->assertSee('نص التعريف بالجمعية')
        ->assertSee('تعرّف على الجمعية')
        ->assertSee('href="/about"', false);
});

it('falls back to the association name, its specialty and the neutral image, with no button', function () {
    $this->get('/')->assertOk()
        ->assertSeeInOrder(['<h1', __('site.name')], false)
        ->assertSee(__('site.tagline'))
        ->assertSee('images/placeholders/pages/default.svg', false)
        ->assertSee('data-hero-slider', false);
});

it('shows the uploaded hero image', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'hero_image' => 'settings/hero.jpg']);

    // الشريحة الأولى (تعريف الجمعية) بالصورة المرفوعة لا بالرسم المؤقت. وما بلا صورة من الشرائح الأخرى بالرسم المؤقت (HomeShowcase::slides)
    $html = $this->get('/')->assertSee('/storage/settings/hero.jpg', false)->getContent();
    preg_match('/data-hero-slide="about".*?<img src="([^"]+)"/s', $html, $about);

    expect($about[1])->toEndWith('/storage/settings/hero.jpg');
});

it('never renders an unsafe hero link even if one reaches the database', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'hero_button_label' => 'زر ضار', 'hero_button_url' => 'javascript:alert(1)']);

    $this->get('/')->assertDontSee('javascript:alert(1)', false)->assertDontSee('زر ضار');
});

it('shows approved statistics with thousands separators, and no strip without them', function () {
    $this->get('/')->assertDontSee('data-portal-stats', false);

    Setting::put('home', [...HOME_LEGACY_SHOWN, 'stats' => [approvedStat()]]);

    // «البوابة بالأرقام» آخر الرئيسية قبل «شركاؤنا» (النسخة 63 — البند H9)
    $this->get('/')->assertSee('data-portal-stats', false)->assertSee(__('home.sections_final.headings.stats'))->assertSee('25,000+')->assertSee('مستفيد');
});

it('hides the statistics when switched off, and skips incomplete entries', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'show_stats' => false, 'stats' => [approvedStat()]]);
    $this->get('/')->assertDontSee('25,000');

    Setting::put('home', [...HOME_LEGACY_SHOWN, 'stats' => [['label' => '', 'value' => 10, 'icon' => 'users'], ['label' => 'بلا رقم', 'value' => 'x']]]);
    $this->get('/')->assertDontSee('data-portal-stats', false);
});

it('draws only the allowed statistic icons', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'stats' => [approvedStat(['icon' => '../../evil'])]]);

    $this->get('/')->assertOk()->assertSee('25,000+');
});

it('shows a section shortcut only once its section exists', function () {
    $built = collect(BuildHomePage::SHORTCUTS)->filter(fn (array $shortcut) => Route::has($shortcut['route']));
    $missing = collect(BuildHomePage::SHORTCUTS)->reject(fn (array $shortcut) => Route::has($shortcut['route']));

    // الأقسام المبنية لها بطاقاتها، والباقية بلا بطاقة حتى يُبنى مسارها
    foreach ($built->keys() as $key) {
        $this->get('/')->assertSee(__(Lang::has('home.shortcuts.'.$key.'.title') ? 'home.shortcuts.'.$key.'.title' : 'home.shortcuts_final.'.$key.'.title'));
    }

    if ($missing->isNotEmpty()) {
        $key = $missing->keys()->first();
        $this->get('/')->assertDontSee('href="/'.$key.'-section"', false);

        registerSectionRoute('/'.$key.'-section', $missing[$key]['route']);

        $this->get('/')->assertSee('href="'.route($missing[$key]['route']).'"', false);
    }
});

/**
 * تعديل الإعداد في القاعدة دون أحداث النموذج، ومسح ذاكرة الإعداد وحدها: فما تعرضه الرئيسية
 * بعدها يأتي من ذاكرة المقاطع.
 */
function changeHomeSettingsSilently(array $home): void
{
    DB::table('settings')->where('key', 'home')->update(['value' => json_encode($home)]);
    Cache::forget(Setting::cacheKey('home'));
}

it('serves the home sections from the cache until content changes', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'hero_title' => 'العنوان الأول']);
    $this->get('/')->assertSee('العنوان الأول');

    changeHomeSettingsSilently([...HOME_LEGACY_SHOWN, 'hero_title' => 'عنوان لم يُعتمد']);
    $this->get('/')->assertSee('العنوان الأول')->assertDontSee('عنوان لم يُعتمد');

    DB::enableQueryLog();
    $this->get('/');
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'settings')))->toBeEmpty();

    ContentCache::flush();
    $this->get('/')->assertSee('عنوان لم يُعتمد');
});

it('shows a change saved from the settings page at once', function () {
    $this->get('/')->assertSee(__('site.name'));

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    Livewire::test(SiteSettings::class)
        ->fillForm(['home.hero_title' => 'عنوان من اللوحة'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/')->assertSee('عنوان من اللوحة');
});

it('builds every cached link from the site address, never from the Host header of a request', function () {
    $this->get('http://evil.example/')->assertOk();

    $this->get('/')->assertOk()->assertDontSee('evil.example', false);
});

it('refreshes the content cache after a deployment without touching the protection limits', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'hero_title' => 'قبل النشر']);
    $this->get('/')->assertSee('قبل النشر');
    changeHomeSettingsSilently([...HOME_LEGACY_SHOWN, 'hero_title' => 'بعد النشر']);
    RateLimiter::hit('login-account:someone', 900);

    $this->artisan('ejaz:refresh-content')->assertSuccessful();

    $this->get('/')->assertSee('بعد النشر');
    expect(RateLimiter::attempts('login-account:someone'))->toBe(1);
});

it('escapes the texts that come from the settings', function () {
    Setting::put('home', [...HOME_LEGACY_SHOWN, 'hero_title' => '<script>alert(1)</script>']);

    $this->get('/')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

it('shows no button and no missing-content marker in production', function () {
    $this->app['env'] = 'production';

    $this->withoutVite()->get('/')->assertOk()
        ->assertDontSee(__('home.hero.button_missing'))
        ->assertSee(__('site.name'));
});

// زر البحث في الرأس يفتح البحث السريع (Ctrl+K) في صفحات القائمة والأقسام التي لها بحث (القرار ٩)
it('opens the quick search from the header button', function () {
    $this->get('/')->assertSee('data-search-open', false)->assertSee('data-search-dialog', false)->assertSee(__('ui.search.open'));
});
