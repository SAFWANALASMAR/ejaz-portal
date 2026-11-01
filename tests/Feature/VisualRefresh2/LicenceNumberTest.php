<?php

use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\Setting;
use App\Support\HomeContent;

/*
| رقم الترخيص في مربع الرأس (صفوان عبر المشرف، 1 نوفمبر 2026): 1000723700 هو نفسه رقم الترخيص المعتمد في التذييل وصفحة التواصل،
| فيُكتب بدل علامة 【ناقص】 من المصدر نفسه لا بنسخة ثانية منه، ويبقى السطر قابلاً للتعديل من «محتوى الرئيسية» ← «الرأس».
*/

it('writes the approved licence number in the header box instead of the missing mark', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $licence = (string) str($html)->after('data-header-licence')->before('</p>');

    expect($licence)->toContain('برقم 1000723700')->not->toContain('【ناقص');
});

it('takes the header number from the footer licence text, so changing it in one place changes both', function () {
    Setting::put(HomeContent::KEY, ['footer' => ['license' => 'رقم الترخيص: 555']]);

    expect(HomeContent::text('header', 'license_number'))->toBe('برقم 555');
});

it('keeps the header line editable from the panel over the footer number', function () {
    Setting::put(HomeContent::KEY, ['header' => ['license_number' => 'برقم 1234']]);

    expect(HomeContent::text('header', 'license_number'))->toBe('برقم 1234')
        ->and(HomeContent::text('footer', 'license'))->toBe('رقم الترخيص: 1000723700');
});

it('shows the number in the panel field and does not store it when saved unchanged', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire\Livewire::test(HomeContentPage::class)
        ->assertFormSet(['header.license_number' => 'برقم 1000723700'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group(HomeContent::KEY)['header']['license_number'] ?? null)->toBeNull();
});
