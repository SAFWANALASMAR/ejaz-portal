<?php

use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('opens the settings page only to those allowed', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(SiteSettings::getUrl())->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(SiteSettings::getUrl())->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(SiteSettings::getUrl())->assertForbidden();
});

it('saves contact details and shows them in the footer at once', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'contact.email' => 'office@example.test',
            'contact.address' => 'مكة المكرمة',
            'contact.social.x' => 'https://x.com/example',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('contact'))->toMatchArray(['email' => 'office@example.test', 'address' => 'مكة المكرمة'])
        ->and(Setting::group('contact')['social'])->toBe(['x' => 'https://x.com/example'])
        ->and(Activity::where('subject_type', Setting::class)->exists())->toBeTrue();

    $this->get('/')->assertSee('office@example.test')->assertSee(__('settings.social.x'));
});

it('validates emails and links', function (array $data, string $field) {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm($data)
        ->call('save')
        ->assertHasFormErrors([$field]);
})->with([
    'bad email' => [['contact.email' => 'not-an-email'], 'contact.email'],
    'script link' => [['contact.social.x' => 'javascript:alert(1)'], 'contact.social.x'],
    'internal social link' => [['contact.social.youtube' => '/channel'], 'contact.social.youtube'],
    'unsafe button link' => [['home.hero_button_url' => '//evil.com'], 'home.hero_button_url'],
]);

it('stores approved statistics as numbers', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['home.stats' => [['label' => 'برنامج', 'value' => 'كثير', 'icon' => 'book-open']]])
        ->call('save')
        ->assertHasFormErrors(['home.stats.0.value']);

    expect(Setting::group('home'))->toBe([]);
});

it('stores approved statistics as whole numbers', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['home.stats' => [['label' => 'برنامج', 'value' => '1500', 'icon' => 'book-open']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('home')['stats'][0]['value'])->toBe(1500);
});

it('does not accept a file path that was not actually uploaded', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['home.hero_image' => ['forged' => 'some-other-dir/not-uploaded.jpg']])
        ->call('save');

    expect(Setting::group('home')['hero_image'] ?? null)->not->toBe('some-other-dir/not-uploaded.jpg');
});

it('rejects an image that passes the checks but cannot be read, and keeps the current one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/current.jpg', UploadedFile::fake()->image('current.jpg', 800, 400)->getContent());
    Setting::put('home', ['hero_image' => 'settings/current.jpg']);

    // ترويسة PNG سليمة بأبعاد 800×400، يليها محتوى لا يُقرأ صورةً
    $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 800, 400)."\x08\x02\x00\x00\x00";
    $broken = UploadedFile::fake()->createWithContent('broken.png', $header.pack('N', crc32(substr($header, 12))).random_bytes(2048));

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $component = Livewire::test(SiteSettings::class)
        ->set('data.home.hero_image', null)
        ->fillForm(['home.hero_image' => [$broken]])
        ->call('save');

    expect($component->errors()->first('data.home.hero_image'))->toBe(__('settings.home.hero_image_unreadable'))
        ->and(Setting::group('home')['hero_image'])->toBe('settings/current.jpg')
        ->and(Storage::disk('public')->allFiles('settings'))->toBe(['settings/current.jpg']);

    // الحقل عاد إلى الصورة المحفوظة، فيُحفظ النموذج دون إعادة الرفع
    $component->fillForm(['home.hero_title' => 'عنوان بعد الرفض'])->call('save')->assertHasNoFormErrors();

    expect(Setting::group('home'))->toMatchArray(['hero_title' => 'عنوان بعد الرفض', 'hero_image' => 'settings/current.jpg']);
});

it('refuses an image path outside the settings folder when the action is called directly', function (string $path) {
    Storage::fake('public');
    Storage::disk('public')->put('other/keep.jpg', UploadedFile::fake()->image('keep.jpg', 800, 400)->getContent());

    expect(fn () => app(UpdateSiteSettings::class)->handle(userWithRole(RoleName::ContentEditor), ['home' => ['hero_image' => $path]]))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('public')->exists('other/keep.jpg'))->toBeTrue()
        ->and(Setting::group('home'))->toBe([]);
})->with(['other/keep.jpg', 'settings/../other/keep.jpg']);

it('publishes the uploaded hero image without its metadata', function () {
    Storage::fake('public');
    $jpeg = UploadedFile::fake()->image('hero.jpg', 1200, 600)->getContent();
    $payload = "Exif\0\0GPS-LATITUDE-21.4225";
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['home.hero_image' => [UploadedFile::fake()->createWithContent('hero.jpg', $withExif)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Storage::disk('public')->get(Setting::group('home')['hero_image']))->not->toContain('GPS-LATITUDE');
});

it('refuses to save settings for someone without the permission, wherever it is called from', function () {
    expect(fn () => app(UpdateSiteSettings::class)->handle(userWithRole(RoleName::BoardMember), ['contact' => ['email' => 'x@example.test']]))
        ->toThrow(AuthorizationException::class);

    expect(Setting::group('contact'))->toBe([]);
});

it('replaces the hero image and removes the previous file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/old.jpg', UploadedFile::fake()->image('old.jpg', 800, 400)->getContent());
    Setting::put('home', ['hero_image' => 'settings/old.jpg']);

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    // كما في الواجهة: تُزال الصورة الحالية من الحقل، ثم تُرفع الجديدة
    Livewire::test(SiteSettings::class)
        ->set('data.home.hero_image', null)
        ->fillForm(['home.hero_image' => [UploadedFile::fake()->image('hero.jpg', 1200, 600)]])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = Setting::group('home')['hero_image'];

    expect($path)->not->toBe('settings/old.jpg')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists('settings/old.jpg'))->toBeFalse();
});

it('rejects oversized images and non-image files', function (UploadedFile $file, string $message) {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $component = Livewire::test(SiteSettings::class)
        ->fillForm(['home.hero_image' => [$file]])
        ->call('save');

    // مقارنة النص كاملاً (assertHasErrors يفصل النص عند النقطتين)
    expect($component->errors()->first('data.home.hero_image'))->toBe($message);

    expect(Setting::group('home'))->toBe([]);
})->with([
    'too wide' => fn () => [UploadedFile::fake()->image('wide.jpg', 5000, 100), __('validation.dimensions', ['attribute' => __('settings.home.hero_image')])],
    'svg' => fn () => [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), __('validation.mimetypes', ['attribute' => __('settings.home.hero_image'), 'values' => 'image/jpeg, image/png, image/webp'])],
]);

it('keeps the current hero image when other settings are saved', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/current.jpg', UploadedFile::fake()->image('current.jpg', 800, 400)->getContent());
    Setting::put('home', ['hero_image' => 'settings/current.jpg']);

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['home.hero_title' => 'عنوان جديد'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('home')['hero_image'])->toBe('settings/current.jpg')
        ->and(Storage::disk('public')->exists('settings/current.jpg'))->toBeTrue();
});
