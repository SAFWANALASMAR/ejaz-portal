<?php

use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use Livewire\Livewire;

/*
| تلميح «صور شرائح البطل» في «محتوى الرئيسية» يصف الشرائح الأربع بترتيبها (إضافة توجيه المدير، 3 نوفمبر 2026، البندان ٢٢ و٢٣):
| المصحف الشريف، ثم الحرم المكي، ثم خادم الحرمين الشريفين، ثم رئيس مجلس الإدارة. ومسابقة البراهين خرجت من البطل إلى الإعلانات.
*/

it('describes the four hero slides in order, without the competition slide', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $hint = (string) __('settings.home_content.hero_slides_hint');

    expect($hint)->toContain('الحرم المكي')
        ->and(mb_strpos($hint, 'المصحف الشريف'))->toBeLessThan(mb_strpos($hint, 'الحرم المكي'))
        ->and(mb_strpos($hint, 'الحرم المكي'))->toBeLessThan(mb_strpos($hint, 'خادم الحرمين الشريفين'))
        ->and(mb_strpos($hint, 'خادم الحرمين الشريفين'))->toBeLessThan(mb_strpos($hint, 'رئيس مجلس الإدارة'))
        ->and($hint)->not->toContain('المسابقة');

    Livewire::test(HomeContentPage::class)->assertSee($hint);
});
