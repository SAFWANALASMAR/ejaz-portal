<?php

use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\User;

/*
| بعد الدخول إلى «حسابي» دائماً، ولو كان قادماً من صفحة المسابقة أو البرنامج، بلا عودة إلى أي صفحة سابقة (قرار صفوان، 5
| نوفمبر 2026 — decisions/2026-11-05-phone-login.md، ويلغي العودة في قرار 30 أكتوبر). ومن يدخل لوحة الإدارة إلى مدخلها.
| والرابط المقصود في الجلسة يُحذف، فلا يتبعه شيء بعد الدخول (ومنه رابط اللوحة الذي رآه المستفيد 403 — 29 أكتوبر 2026).
*/

// كلمة مرور قوية، فلا يُحوَّل الحساب إلى تغييرها بعد الدخول
const LD_PASSWORD = 'Thursday-Exam-Key-2026';

it('sends a beneficiary to their account, not to an admin link left in the session', function () {
    $member = User::factory()->create(['email' => 'member@example.test', 'password' => LD_PASSWORD]);

    $this->withSession(['url.intended' => url('/admin/competitions/1?relation=8')])
        ->post('/login', ['login' => 'member@example.test', 'password' => LD_PASSWORD])
        ->assertRedirect(route('account.dashboard'));

    $this->get(route('account.dashboard'))->assertOk();
    expect(session('url.intended'))->toBeNull()
        ->and($member->fresh()->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('sends a beneficiary to their account, not to a site page they were heading to', function (string $page) {
    User::factory()->create(['email' => 'member@example.test', 'password' => LD_PASSWORD]);

    $this->withSession(['url.intended' => url($page)])
        ->post('/login', ['login' => 'member@example.test', 'password' => LD_PASSWORD])
        ->assertRedirect(route('account.dashboard'));
    expect(session('url.intended'))->toBeNull();
})->with(['account page' => '/account/programs', 'public page' => '/programs']);

it('sends a staff member with panel access to the panel home, not to the admin link they were heading to', function () {
    // بلا تحقق ثنائي مفعّل، فيصل الدخول إلى PortalLoginResponse مباشرة (وإتمام التحقق الثنائي يمر بالاستجابة نفسها)
    $editor = User::factory()->create(['password' => LD_PASSWORD]);
    $editor->assignRole(RoleName::ContentEditor->value);

    $this->withSession(['url.intended' => url('/admin/pages')])
        ->post('/login', ['login' => $editor->email, 'password' => LD_PASSWORD])
        ->assertRedirect(url('/admin'));
});

it('sends a guest who logs in from the competition page to their account, not back to the competition', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);
    User::factory()->create(['phone' => '+966512345678', 'password' => LD_PASSWORD]);
    $register = route('competitions.student.register', 'brahin');

    // الزر للزائر إلى صفحة التسجيل نفسها، فتطلب الدخول
    $this->get('/competitions/brahin')->assertOk()->assertSee('href="'.$register.'"', false);
    $this->get($register)->assertRedirect(route('login'));

    $this->post('/login', ['login' => '0512345678', 'password' => LD_PASSWORD])->assertRedirect(route('account.dashboard'));
    $this->get(route('account.dashboard'))->assertOk();
    expect(session('url.intended'))->toBeNull();
});
