<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
| شرح التحقق الثنائي (طلب صفوان، نوفمبر 2026): «كيف أفعّله؟» و«إن تعطّل التطبيق أو ضاع الجوال» في صفحة «الأمان»، وهي صفحة
| التفعيل للوحة الإدارة أيضاً (EnsureTwoFactorIsEnabled يحوّل إليها)، وسطر الوقت تحت حقل الرمز عند الدخول.
*/

function securityPageAs(User $user): TestResponse
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/account/security');
}

function assertTwoFactorGuide(TestResponse $response): void
{
    $response->assertOk()
        ->assertSee('data-two-factor-how', false)
        ->assertSee('data-two-factor-lost', false)
        ->assertSee(__('account.two_factor_guide.how.title'))
        ->assertSee(__('account.two_factor_guide.lost.title'))
        // أسماء الأزرار الحقيقية في الخطوات
        ->assertSee(__('account.two_factor_guide.how.steps.enable', ['enable' => __('account.security.enable')]))
        ->assertSee(__('account.two_factor_guide.how.steps.code', ['confirm' => __('account.security.confirm')]))
        ->assertSee(__('account.two_factor_guide.lost.items.recovery', [
            'use_recovery' => __('account.two_factor_challenge.use_recovery'),
            'regenerate' => __('account.security.regenerate'),
        ]))
        ->assertSee(__('account.two_factor_guide.lost.items.no_reset'))
        ->assertSee(__('account.two_factor_guide.lost.items.sync'));
}

function newPhoneLine(): string
{
    return __('account.two_factor_guide.lost.items.new_phone', [
        'disable' => __('account.security.disable'),
        'enable' => __('account.security.enable'),
    ]);
}

it('explains two-factor on the account security page before and after enabling it', function () {
    foreach ([User::factory()->create(), User::factory()->withTwoFactor()->create()] as $user) {
        assertTwoFactorGuide(securityPageAs($user)
            // والمستفيد ينقله إلى جوال جديد بالإيقاف ثم التفعيل، والزران في صفحته
            ->assertSee(newPhoneLine())
            ->assertDontSee(__('account.two_factor_guide.lost.items.new_phone_required')));
    }

    securityPageAs(User::factory()->withTwoFactor()->create())->assertSee('«'.__('account.security.disable').'»', false);
});

it('explains two-factor on the page the admin panel sends administrators to', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ExecutiveDirector->value);

    $this->actingAs($admin)->get('/admin')->assertRedirect(route('account.security'));

    assertTwoFactorGuide(securityPageAs($admin)
        ->assertSee(__('account.security.required_for_role'))
        // لا يُطلب منه زر «إيقاف التحقق الثنائي» وهو مخفي عنه ومرفوض له (DisableTwoFactorAuthentication)
        ->assertSee(__('account.two_factor_guide.lost.items.new_phone_required'))
        ->assertDontSee(newPhoneLine()));
});

it('keeps the guide from guests, behind the login like the rest of the page', function () {
    $this->get('/account/security')->assertRedirect(route('login'));
});

it('keeps the guide behind the password confirmation like the rest of the page', function () {
    $this->actingAs(User::factory()->create())->get('/account/security')
        ->assertRedirect(route('password.confirm'));
});

it('shows the timing hint under the code field at login, and not for recovery codes', function () {
    User::factory()->withTwoFactor()->create(['email' => 'guide@example.test']);

    $this->post('/login', ['login' => 'guide@example.test', 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');

    $this->get('/two-factor-challenge')->assertOk()
        ->assertSee(__('account.two_factor_challenge_hint'))
        ->assertSee('aria-describedby="field-code-hint"', false);

    $this->get('/two-factor-challenge?recovery=1')->assertOk()
        ->assertDontSee(__('account.two_factor_challenge_hint'));
});
