<?php

use App\Enums\RoleName;
use App\Filament\Pages\AssistantSettingsPage;
use App\Models\Announcement;

/*
| عيبان من التجربة الشاملة على 9b9ce5f0 مع #170 و#165 (4 نوفمبر 2026، «مدخلات-المدير/تجربة-شاملة-9b9ce5f0-مع-170-165.md»):
| نقاط شريط الإعلانات في صف لا يلتف فتزيح الرئيسية على 390، وزر «إضافة إلى suggested questions» في إعدادات المساعد.
| واختبار المتصفح: tests/browser/v0151-qa.spec.mjs.
*/

function homeAnnouncements(): string
{
    return (string) str(test()->get('/')->assertOk()->getContent())->after('home-announcements feed-carousel')->before('</section>');
}

it('shows a counter instead of dots once the announcements carousel has more than six slides', function () {
    Announcement::factory()->count(10)->create();

    $carousel = homeAnnouncements();

    expect($carousel)->not->toContain('data-carousel-dots')
        ->and($carousel)->not->toContain('data-carousel-dot ')
        ->and($carousel)->toContain('data-carousel-status')
        ->and($carousel)->toMatch('#data-carousel-status[^>]*>01 / 1\d</span>#');
});

it('keeps the dots for six announcements or fewer', function () {
    Announcement::factory()->count(3)->create();

    $carousel = homeAnnouncements();

    expect($carousel)->toContain('data-carousel-dots')
        ->and(substr_count($carousel, 'data-carousel-dot '))->toBe(3)
        ->and($carousel)->not->toContain('data-carousel-status');
});

it('labels the add button of the suggested questions in Arabic', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    $this->get(AssistantSettingsPage::getUrl())->assertOk()
        ->assertSeeText(__('assistant.admin.settings.add_suggested_question'))
        ->assertDontSeeText('suggested')
        ->assertDontSee('Suggested', false);
});
