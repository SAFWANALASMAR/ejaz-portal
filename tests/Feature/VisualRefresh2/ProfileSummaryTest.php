<?php

use App\Models\User;
use App\Support\Masked;

/*
| ملخص «بياناتي» (تدقيق المشرف): البريد والجوال مخفيان أولاً بزر «إظهار البيانات» بحالته في aria-expanded، والقيمة الكاملة
| لصاحب الحساب وحده في صفحته، وبلا زر حين لا شيء مخفي. والصفحة للمسجل دخوله وحده.
*/

it('masks the email and the phone', function () {
    expect(Masked::email('sara@example.test'))->toBe('s••••@example.test')
        ->and(Masked::email(null))->toBeNull()
        ->and(Masked::phone('+966512345678'))->toEndWith('**78')
        ->and(Masked::phone('+966512345678'))->not->toContain('345');
});

it('shows the owner a masked summary with the reveal button', function () {
    $user = User::factory()->create(['name' => 'سارة أحمد', 'email' => 'sara@example.test', 'phone' => '+966512345678']);

    $this->actingAs($user)->get('/account/profile')->assertOk()
        ->assertSee('data-profile-summary', false)
        ->assertSeeInOrder([__('account.summary.title'), 'aria-expanded="false"', 'aria-controls="profile-summary-list"', __('account.summary.show')], false)
        ->assertSeeInOrder(['data-masked', 's••••@example.test', 'data-full dir="ltr" hidden', 'sara@example.test'], false)
        ->assertSee('data-icon-tile', false);
});

it('drops the reveal button when nothing is masked', function () {
    $user = User::factory()->create(['email' => null, 'phone' => null, 'username' => 'sara_1']);

    $this->actingAs($user)->get('/account/profile')->assertOk()
        ->assertSee('data-profile-summary', false)
        ->assertDontSee('data-reveal-toggle', false);
});

it('keeps the summary behind sign-in', function () {
    $this->get('/account/profile')->assertRedirect(route('login'))->assertDontSee('data-profile-summary', false);
});
