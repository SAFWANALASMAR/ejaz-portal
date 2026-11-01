<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\Setting;
use App\Support\HomeContent;
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
    preg_match('/<div class="concept-hero-media">\s*<img src="([^"]+)"/', $html, $match);

    return $match[1] ?? '';
}

it('shows the uploaded image in the hero of its own page only', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/governance-hero.jpg', 'x');
    Setting::put(HomeContent::KEY, ['page_heroes' => ['governance' => 'settings/governance-hero.jpg']]);

    expect(heroImageOf($this->get('/governance')->assertOk()->getContent()))->toBe(Storage::disk('public')->url('settings/governance-hero.jpg'))
        ->and(heroImageOf($this->get('/contact')->assertOk()->getContent()))->toBe(asset('images/placeholders/architecture.svg'));
});

it('keeps the current image when no page image is uploaded', function () {
    expect(heroImageOf($this->get('/programs')->assertOk()->getContent()))->toBe(asset('images/placeholders/architecture.svg'))
        ->and(HomeContent::pageHeroUrl('programs.index'))->toBeNull()
        ->and(HomeContent::pageHeroUrl('programs.show'))->toBeNull();
});

it('offers one image slot per inner page in the home content screen and saves it', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $page = Livewire::test(HomeContentPage::class);

    foreach (array_keys(HomeContent::PAGE_HEROES) as $key) {
        $page->assertFormFieldExists('page_heroes.'.$key);
    }

    $page->fillForm(['page_heroes.contact' => UploadedFile::fake()->image('contact.jpg', 1600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = Setting::group(HomeContent::KEY)['page_heroes']['contact'] ?? null;

    expect($path)->toBeString()
        ->and(HomeContent::imagePaths(Setting::group(HomeContent::KEY)))->toContain($path)
        ->and(heroImageOf($this->get('/contact')->assertOk()->getContent()))->toBe(Storage::disk('public')->url($path));
});

it('refuses a page image from a user without the settings permission', function () {
    app(UpdateHomeContent::class)->handle(userWithRole(RoleName::Member), ['page_heroes' => ['contact' => 'settings/x.jpg']]);
})->throws(AuthorizationException::class);
