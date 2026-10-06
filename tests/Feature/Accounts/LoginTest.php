<?php

use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'member@example.test',
        'phone' => '+966512345678',
    ]);
});

it('shows the login form', function () {
    $this->get('/login')->assertOk()->assertSee(__('account.login.identifier'));
});

it('logs in with the email address', function () {
    $this->post('/login', ['login' => 'Member@Example.test', 'password' => 'password'])
        ->assertRedirect('/account');

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::where('log_name', 'security')->where('event', 'login')->exists())->toBeTrue();
});

it('logs in with the mobile number in any common format', function (string $phone) {
    $this->post('/login', ['login' => $phone, 'password' => 'password'])->assertRedirect('/account');

    $this->assertAuthenticatedAs($this->user);
})->with(['0512345678', '+966512345678', '٠٥١٢٣٤٥٦٧٨']);

it('gives the same message for a wrong password and an unknown account', function () {
    $this->post('/login', ['login' => 'member@example.test', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => __('auth.failed')]);

    $this->post('/login', ['login' => 'nobody@example.test', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => __('auth.failed')]);

    $this->assertGuest();
    expect(Activity::where('event', 'login_failed')->count())->toBe(2);
});

it('never stores the attempted password in the security log', function () {
    $this->post('/login', ['login' => 'member@example.test', 'password' => 'secret-attempt-123']);

    $log = Activity::where('event', 'login_failed')->firstOrFail();

    expect(json_encode($log->properties))->not->toContain('secret-attempt-123')
        ->not->toContain('member@example.test');
});

it('refuses deactivated accounts', function () {
    $this->user->forceFill(['is_active' => false])->save();

    $this->post('/login', ['login' => 'member@example.test', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('asks for the two-factor code when it is enabled', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'secure@example.test']);

    $this->post('/login', ['login' => 'secure@example.test', 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
    $this->get('/two-factor-challenge')->assertOk()->assertSee(__('account.two_factor_challenge.title'));
});

it('throttles repeated attempts', function () {
    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['login' => 'member@example.test', 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => 'member@example.test', 'password' => 'password'])->assertStatus(429);
    $this->assertGuest();
});

it('locks the account across different spellings of the same number', function () {
    $spellings = [
        '0512345678', '512345678', '+966512345678', '٠٥١٢٣٤٥٦٧٨', '966512345678',
        '051 234 5678', '00966512345678', '051-234-5678', '05 1234 5678', '+966 51 234 5678',
    ];

    // كل محاولة من عنوان مختلف، فلا يمنعها إلا قفل الحساب نفسه
    foreach ($spellings as $i => $spelling) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])
            ->post('/login', ['login' => $spelling, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
        ->post('/login', ['login' => '0512345678', 'password' => 'password'])
        ->assertStatus(429)
        ->assertSee(Str::before(__('auth.throttle'), ':seconds'), false);

    $this->assertGuest();
    expect(Activity::where('event', 'login_lockout')->exists())->toBeTrue();
});

it('fingerprints every spelling of an identifier the same way', function () {
    $this->post('/login', ['login' => '0512345678', 'password' => 'wrong-password']);
    $this->post('/login', ['login' => '+966 51 234 5678', 'password' => 'wrong-password']);
    $this->post('/login', ['login' => 'Member@Example.test', 'password' => 'wrong-password']);
    $this->post('/login', ['login' => 'member@example.test', 'password' => 'wrong-password']);

    $hashes = Activity::where('event', 'login_failed')->get()
        ->map(fn (Activity $log) => $log->properties['identifier_hash'])
        ->unique();

    expect($hashes)->toHaveCount(2);
});

it('logs out and records it', function () {
    $this->actingAs($this->user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
