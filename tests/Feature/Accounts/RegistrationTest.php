<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeSmsGateway;

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return [
        'name' => 'عبدالله محمد',
        'email' => 'Abdullah@Example.test',
        'phone' => '0512345678',
        'password' => 'long-enough-pass-2026',
        'password_confirmation' => 'long-enough-pass-2026',
        'privacy' => '1',
        ...$overrides,
    ];
}

it('shows the registration form', function () {
    $this->get('/register')->assertOk()->assertSee(__('account.register.title'));
});

it('registers a beneficiary and starts phone verification', function () {
    $this->post('/register', registrationData())->assertRedirect(route('account.phone.verify'));

    $user = User::where('email', 'abdullah@example.test')->firstOrFail();

    expect($user->phone)->toBe('+966512345678')
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([RoleName::Member->value])
        ->and($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('requires accepting the privacy policy', function () {
    $this->post('/register', registrationData(['privacy' => null]))->assertSessionHasErrors('privacy');

    expect(User::count())->toBe(0);
});

it('rejects invalid input', function (array $overrides, string $field) {
    $this->post('/register', registrationData($overrides))->assertSessionHasErrors($field);
})->with([
    'non-Saudi phone' => [['phone' => '+201012345678'], 'phone'],
    'landline' => [['phone' => '0112345678'], 'phone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatch' => [['password_confirmation' => 'something-else-2026'], 'password'],
    'short name' => [['name' => 'ع'], 'name'],
]);

it('rejects an email or phone that is already registered', function () {
    User::factory()->create(['email' => 'abdullah@example.test']);
    $this->post('/register', registrationData())->assertSessionHasErrors('email');

    User::factory()->create(['phone' => '+966512345678']);
    $this->post('/register', registrationData(['email' => 'other@example.test', 'phone' => '051 234 5678']))
        ->assertSessionHasErrors('phone');
});

it('limits registrations from one address', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/register', registrationData([
            'email' => "user{$i}@example.test",
            'phone' => '05'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
        ]));
        auth()->logout();
    }

    $this->post('/register', registrationData(['email' => 'sixth@example.test', 'phone' => '0599999999']))
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'sixth@example.test')->exists())->toBeFalse();
});
