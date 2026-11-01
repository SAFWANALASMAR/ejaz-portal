<?php

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;

/*
| لوحة المستفيد بالتصور النهائي (Final-Dashboard — البند D1، المرحلة د من #111): شريط المكتبة تحت الترحيب، و«مواعيدي القادمة»
| مربعات، والبطاقات ملونة، ولا شيء من «قراءاتي» قبل فرعها.
*/

it('runs the latest library videos and book covers under the welcome, published ones only', function () {
    LibraryItem::factory()->video()->create(['title' => 'مقطع لوحة المستفيد', 'thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->create(['title' => 'كتاب لوحة المستفيد', 'section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);
    LibraryItem::factory()->unpublished()->create(['title' => 'كتاب غير منشور', 'section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/c.jpg']);

    $html = $this->actingAs(User::factory()->create())->get('/account')->assertOk()
        ->assertSee('data-account-library-strip', false)
        ->assertSee(__('account.dashboard_final.library'))
        ->assertSee('data-ticker-toggle="account-library-track"', false)
        ->assertSee('مقطع لوحة المستفيد')->assertSee('كتاب لوحة المستفيد')
        ->assertDontSee('كتاب غير منشور')
        ->getContent();

    // تحت الترحيب وقبل الشريط الجانبي
    expect(strpos($html, 'data-account-library-strip'))->toBeLessThan(strpos($html, 'aria-label="'.__('site.my_account').'"'));
});

it('hides the library strip when the library has nothing to show', function () {
    $this->actingAs(User::factory()->create())->get('/account')->assertOk()
        ->assertDontSee('data-account-library-strip', false);
});

it('shows my upcoming programmes as coloured tiles and the cards as coloured cards', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create(['title' => 'برنامج المربع', 'starts_at' => now()->addDays(5)]);
    ProgramRegistration::factory()->for($user)->for($program)->create();

    $this->actingAs($user)->get('/account')->assertOk()
        ->assertSee('data-account-upcoming', false)
        ->assertSee('data-appointment', false)
        ->assertSee('برنامج المربع')
        ->assertSee('data-account-cards', false)
        ->assertSee('class="tone-card group', false);
});
