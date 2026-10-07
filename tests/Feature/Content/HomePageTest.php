<?php

use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * مسار مؤقت لقسم لم يُبنَ بعد، ليُختبر ظهور بطاقته في الرئيسية.
 */
function registerSectionRoute(string $uri, string $name): void
{
    Route::get($uri, fn () => 'ok')->name($name);
    Route::getRoutes()->refreshNameLookups();
    ContentCache::flush();
}

/**
 * @return array<string, mixed>
 */
function approvedStat(array $overrides = []): array
{
    return ['label' => 'مستفيد', 'value' => 25000, 'suffix' => '+', 'icon' => 'users', ...$overrides];
}

it('shows the hero from the site settings', function () {
    Setting::put('home', [
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
        ->assertSee('images/placeholders/architecture.svg', false)
        ->assertSee(__('home.hero.button_missing'));
});

it('shows the uploaded hero image', function () {
    Setting::put('home', ['hero_image' => 'settings/hero.jpg']);

    $this->get('/')->assertSee('/storage/settings/hero.jpg', false)
        ->assertDontSee('images/placeholders/architecture.svg', false);
});

it('never renders an unsafe hero link even if one reaches the database', function () {
    Setting::put('home', ['hero_button_label' => 'زر ضار', 'hero_button_url' => 'javascript:alert(1)']);

    $this->get('/')->assertDontSee('javascript:alert(1)', false)->assertDontSee('زر ضار');
});

it('shows approved statistics with thousands separators, and no strip without them', function () {
    $this->get('/')->assertDontSee(__('home.stats.title'));

    Setting::put('home', ['stats' => [approvedStat()]]);

    $this->get('/')->assertSee(__('home.stats.title'))->assertSee('25,000+')->assertSee('مستفيد');
});

it('hides the statistics when switched off, and skips incomplete entries', function () {
    Setting::put('home', ['show_stats' => false, 'stats' => [approvedStat()]]);
    $this->get('/')->assertDontSee('25,000');

    Setting::put('home', ['stats' => [['label' => '', 'value' => 10, 'icon' => 'users'], ['label' => 'بلا رقم', 'value' => 'x']]]);
    $this->get('/')->assertDontSee(__('home.stats.title'));
});

it('draws only the allowed statistic icons', function () {
    Setting::put('home', ['stats' => [approvedStat(['icon' => '../../evil'])]]);

    $this->get('/')->assertOk()->assertSee('25,000+');
});

it('shows a section shortcut only once its section exists, and none when switched off', function () {
    $this->get('/')->assertDontSee(__('home.shortcuts.governance.title'));

    registerSectionRoute('/governance', 'governance.index');

    $this->get('/')
        ->assertSee(__('home.shortcuts.governance.title'))
        ->assertSee('href="'.route('governance.index').'"', false)
        ->assertDontSee(__('home.shortcuts.media.title'));

    Setting::put('home', ['show_shortcuts' => false]);

    $this->get('/')->assertDontSee(__('home.shortcuts.governance.title'));
});

it('caches the home sections as text and shows a settings change at once', function () {
    $this->get('/')->assertOk();

    expect(Cache::tags(ContentCache::TAG)->get('home'))->toBeString()->toContain(__('site.name'));

    DB::enableQueryLog();
    $this->get('/')->assertOk();
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'settings')))->toBeEmpty();

    Setting::put('home', ['hero_title' => 'عنوان معدّل']);

    $this->get('/')->assertSee('عنوان معدّل');
});

it('shows the search icon as coming soon until search is built', function () {
    $this->get('/')->assertSee(__('site.search_soon'));
});
