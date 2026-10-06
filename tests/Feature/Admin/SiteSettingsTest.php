<?php

use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
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
