<?php

use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\User;

/*
| ملاحظة المدير التنفيذي «حساب المستفيد لا يفتح» (29 أكتوبر 2026): خرج من حساب إدارة في المتصفح نفسه فبقي رابط اللوحة في
| الجلسة (url.intended)، ثم دخل بحساب مستفيد فحُوِّل إليه فرأى 403. الرابط المقصود في اللوحة لا يُتبع لمن لا يدخلها
| (App\Support\IntendedUrl)، ويُتبع لمن يدخلها.
*/

// كلمة مرور قوية، فلا يُحوَّل الحساب إلى تغييرها بعد الدخول
const IA_PASSWORD = 'Thursday-Exam-Key-2026';

it('sends a beneficiary to their account, not to an admin link left in the session', function () {
    $member = User::factory()->create(['email' => 'member@example.test', 'password' => IA_PASSWORD]);

    $this->withSession(['url.intended' => url('/admin/competitions/1?relation=8')])
        ->post('/login', ['login' => 'member@example.test', 'password' => IA_PASSWORD])
        ->assertRedirect(route('account.dashboard'));

    $this->get(route('account.dashboard'))->assertOk();
    expect(session('url.intended'))->toBeNull()
        ->and($member->fresh()->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('still sends a beneficiary to a site page they were heading to', function () {
    User::factory()->create(['email' => 'member@example.test', 'password' => IA_PASSWORD]);

    $this->withSession(['url.intended' => url('/account/programs')])
        ->post('/login', ['login' => 'member@example.test', 'password' => IA_PASSWORD])
        ->assertRedirect(url('/account/programs'));
});

it('sends a staff member with panel access to the admin link they were heading to', function () {
    // بلا تحقق ثنائي مفعّل، فيصل الدخول إلى PortalLoginResponse مباشرة (وإتمام التحقق الثنائي يمر بالاستجابة نفسها)
    $editor = User::factory()->create(['password' => IA_PASSWORD]);
    $editor->assignRole(RoleName::ContentEditor->value);

    $this->withSession(['url.intended' => url('/admin/pages')])
        ->post('/login', ['login' => $editor->email, 'password' => IA_PASSWORD])
        ->assertRedirect(url('/admin/pages'));
});

it('brings a guest back to the competition registration after logging in from the competition page', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);
    User::factory()->create(['email' => 'student@example.test', 'password' => IA_PASSWORD]);
    $register = route('competitions.student.register', 'brahin');

    // الزر للزائر إلى صفحة التسجيل نفسها، فتطلب الدخول وتحفظ وجهتها
    $this->get('/competitions/brahin')->assertOk()->assertSee('href="'.$register.'"', false);
    $this->get($register)->assertRedirect(route('login'));

    $this->post('/login', ['login' => 'student@example.test', 'password' => IA_PASSWORD])->assertRedirect($register);
    $this->get($register)->assertOk();
});
