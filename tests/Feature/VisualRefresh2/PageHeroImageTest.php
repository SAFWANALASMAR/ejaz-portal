<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\Setting;
use App\Support\EditablePages;
use App\Support\HomeContent;
use App\Support\NeutralImage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| صورة ثابتة لرأس كل صفحة داخلية تُرفع من اللوحة (صفوان عبر المشرف، 1 نوفمبر 2026): خانة لكل صفحة في «محتوى الرئيسية» ← «صور
| رؤوس الصفحات»، محفوظة في الإعدادات نفسها (home_sections.page_heroes). وبلا صورة يبقى البديل الحالي كما هو.
*/

function heroImageOf(string $html): string
{
    // رأس «فاتح بصورة واحدة» (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٧): الصورة في إطارها المائل
    preg_match('/<span class="page-hero-frame">\s*<img src="([^"]+)"/', $html, $match);

    return $match[1] ?? '';
}

it('shows the uploaded image in the hero of its own page only', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/governance-hero.jpg', 'x');
    Setting::put(HomeContent::KEY, ['page_heroes' => ['governance' => 'settings/governance-hero.jpg']]);

    expect(heroImageOf($this->get('/governance')->assertOk()->getContent()))->toBe(Storage::disk('public')->url('settings/governance-hero.jpg'))
        ->and(heroImageOf($this->get('/contact')->assertOk()->getContent()))->toBe(asset('images/placeholders/pages/contact.svg'));
});

it('gives each page without an uploaded image its own neutral image, not one repeated placeholder', function () {
    $images = collect(['/programs', '/governance', '/contact', '/media', '/services', '/initiatives', '/media/news', '/competitions'])
        ->map(fn (string $url): string => heroImageOf($this->get($url)->assertOk()->getContent()));

    expect($images->unique())->toHaveCount($images->count())
        ->and($images->every(fn (string $src): bool => str_contains($src, 'images/placeholders/pages/')))->toBeTrue()
        ->and(NeutralImage::forPage('media.news.show'))->toBe(asset('images/placeholders/pages/news.svg'))
        ->and(NeutralImage::forPage('governance.show'))->toBe(asset('images/placeholders/pages/governance.svg'))
        ->and(NeutralImage::forPage('privacy'))->toBe(asset('images/placeholders/pages/default.svg'));
});

it('keeps the current image when no page image is uploaded', function () {
    expect(heroImageOf($this->get('/programs')->assertOk()->getContent()))->toBe(asset('images/placeholders/pages/programs.svg'))
        ->and(HomeContent::pageHeroUrl('programs.index'))->toBeNull()
        ->and(HomeContent::pageHeroUrl('programs.show'))->toBeNull();
});

it('keeps in the home content screen only the image slots that are not in the page editor, and saves them', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $page = Livewire::test(HomeContentPage::class);

    // صور رؤوس صفحات «محرر الصفحات» فيه وحده بنسخها (docs/decisions/2026-11-02-page-editor.md)، والإعلانات هنا
    foreach (array_keys(HomeContent::PAGE_HEROES) as $key) {
        isset(EditablePages::PAGES[$key]) ? $page->assertFormFieldDoesNotExist('page_heroes.'.$key) : $page->assertFormFieldExists('page_heroes.'.$key);
    }

    $page->fillForm(['page_heroes.announcements' => UploadedFile::fake()->image('announcements.jpg', 1600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = Setting::group(HomeContent::KEY)['page_heroes']['announcements'] ?? null;

    expect($path)->toBeString()
        ->and(HomeContent::imagePaths(Setting::group(HomeContent::KEY)))->toContain($path)
        ->and(heroImageOf($this->get('/media/announcements')->assertOk()->getContent()))->toBe(Storage::disk('public')->url($path));
});

it('refuses a page image from a user without the settings permission', function () {
    app(UpdateHomeContent::class)->handle(userWithRole(RoleName::Member), ['page_heroes' => ['contact' => 'settings/x.jpg']]);
})->throws(AuthorizationException::class);

it('ships neutral images without people for every inner page', function () {
    foreach ([...array_keys(HomeContent::PAGE_HEROES), 'default'] as $key) {
        $svg = (string) file_get_contents(public_path('images/placeholders/pages/'.$key.'.svg'));

        expect($svg)->toStartWith('<svg')->not->toContain('<image')->not->toContain('<script')->not->toContain('href=');
    }
});

it('lets the home content screen set the header image of the achievements page', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(HomeContentPage::class)
        ->assertFormFieldExists('page_heroes.achievements')
        ->fillForm(['page_heroes.achievements' => UploadedFile::fake()->image('achievements.jpg', 1600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = Setting::group(HomeContent::KEY)['page_heroes']['achievements'] ?? null;

    expect($path)->toBeString()
        ->and(HomeContent::pageHeroUrl('achievements.index'))->toBe(Storage::disk('public')->url($path))
        ->and(heroImageOf($this->get('/achievements')->assertOk()->getContent()))->toBe(Storage::disk('public')->url($path));
});

it('keeps the card images rotation of the fourteen patterns, without the achievements copy of the default', function () {
    $images = collect(range(0, 13))->map(fn (int $seed): string => NeutralImage::forSeed($seed));

    expect($images->unique())->toHaveCount(14)
        ->and(NeutralImage::forSeed(0))->toBe(asset('images/placeholders/pages/about.svg'))
        ->and(NeutralImage::forSeed(14))->toBe(asset('images/placeholders/pages/about.svg'))
        ->and($images)->not->toContain(asset('images/placeholders/pages/achievements.svg'));
});
