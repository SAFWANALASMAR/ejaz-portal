<?php

use App\Actions\Messaging\SendTestMessage;
use App\Actions\Messaging\UpdateMessagingChannels;
use App\Enums\ActivityEvent;
use App\Enums\MessageStatus;
use App\Enums\RoleName;
use App\Filament\Pages\MessagingChannels;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const CHANNELS_TEST_KEY = 'taq-secret-key-9f8e7d';

it('opens the channels page to the super admin only', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get(MessagingChannels::getUrl())->assertForbidden();
})->with([RoleName::ContentEditor, RoleName::BoardMember, RoleName::Secretary, RoleName::ExecutiveDirector, RoleName::ProgramManager]);

it('shows the page to the super admin', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(MessagingChannels::getUrl())
        ->assertOk()
        ->assertSee(__('messaging.title'))
        ->assertSee(route('messaging.whatsapp.webhook'));
});

it('refuses saving and test messages for anyone without the permission', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(UpdateMessagingChannels::class)->handle($editor, ['sms' => ['provider' => 'taqnyat']]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SendTestMessage::class)->handle($editor, 'sms', ['phone' => '+966500000001']))->toThrow(AuthorizationException::class)
        ->and(MessagingChannel::count())->toBe(0);
});

it('saves the provider with an encrypted key that never shows or logs', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $this->actingAs($admin);

    Livewire::test(MessagingChannels::class)
        ->fillForm(['sms.provider' => 'taqnyat', 'sms.sender' => 'EJAZ', 'sms.api_key' => CHANNELS_TEST_KEY])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.sms.api_key', null);

    $channel = MessagingChannel::for(MessagingChannel::SMS);
    expect($channel->provider)->toBe('taqnyat')
        ->and($channel->secret('api_key'))->toBe(CHANNELS_TEST_KEY)
        ->and(DB::table('messaging_channels')->value('secrets'))->not->toContain(CHANNELS_TEST_KEY)
        ->and(json_encode($channel->toArray()))->not->toContain(CHANNELS_TEST_KEY)
        ->and(Activity::all()->toJson())->not->toContain(CHANNELS_TEST_KEY)
        ->and(Activity::where('event', ActivityEvent::MessagingSecretsChanged->value)->sole()->properties['secrets'])->toBe(['api_key']);

    $this->get(MessagingChannels::getUrl())->assertOk()->assertDontSee(CHANNELS_TEST_KEY)->assertSee(__('messaging.fields.secret_stored'));
});

it('keeps a stored key when the field is left empty, and clears it on request', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $action = app(UpdateMessagingChannels::class);

    $action->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ', 'api_key' => CHANNELS_TEST_KEY]]);
    $action->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ2', 'api_key' => '']]);

    expect(MessagingChannel::for('sms')->secret('api_key'))->toBe(CHANNELS_TEST_KEY)
        ->and(MessagingChannel::for('sms')->option('sender'))->toBe('EJAZ2')
        ->and(Activity::where('event', ActivityEvent::MessagingSecretsChanged->value)->count())->toBe(1);

    $action->handle($admin, ['sms' => ['provider' => 'taqnyat', 'clear_api_key' => true]]);

    expect(MessagingChannel::for('sms')->secret('api_key'))->toBeNull();
});

it('validates channel settings', function (array $data, string $field) {
    try {
        app(UpdateMessagingChannels::class)->handle(userWithRole(RoleName::SuperAdmin), $data);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    $this->fail('Expected a validation error.');
})->with([
    'unknown provider' => [['sms' => ['provider' => 'twilio']], 'sms.provider'],
    'key with spaces' => [['sms' => ['api_key' => 'a b']], 'sms.api_key'],
    'html sender' => [['sms' => ['sender' => '<b>x</b>']], 'sms.sender'],
    'unknown whatsapp provider' => [['whatsapp' => ['provider' => 'twilio']], 'whatsapp.provider'],
    'non-numeric phone id' => [['whatsapp' => ['phone_number_id' => '12ab']], 'whatsapp.phone_number_id'],
    'bad template name' => [['whatsapp' => ['templates' => ['exam_receipt' => 'Exam Receipt']]], 'whatsapp.templates.exam_receipt'],
    'bad graph version' => [['whatsapp' => ['graph_version' => '23']], 'whatsapp.graph_version'],
]);

it('sends a test SMS to the number the admin enters', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    app(UpdateMessagingChannels::class)->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ', 'api_key' => CHANNELS_TEST_KEY]]);
    Http::fake(['api.taqnyat.sa/*' => Http::response(['messageId' => 42], 201)]);

    $this->actingAs($admin);
    Livewire::test(MessagingChannels::class)
        ->callAction('test_sms', ['phone' => '+966500000555'])
        ->assertNotified(__('messaging.test.result', ['status' => MessageStatus::Sent->getLabel()]));

    Http::assertSent(fn (Request $request) => $request['recipients'] === [966500000555] && $request['body'] === __('messaging.test.sms_body'));
    expect(MessageLog::sole())->usage->toBe('test')->status->toBe(MessageStatus::Sent)
        ->and(Activity::where('event', ActivityEvent::MessagingTestSent->value)->sole()->properties->toArray())->not->toHaveKey('phone');
});

it('shows a provider failure from the test message without the key', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    app(UpdateMessagingChannels::class)->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ', 'api_key' => CHANNELS_TEST_KEY]]);
    Http::fake(['api.taqnyat.sa/*' => Http::response(['message' => 'invalid credentials information'], 401)]);

    $log = app(SendTestMessage::class)->handle($admin, 'sms', ['phone' => '+966500000555']);

    expect($log->status)->toBe(MessageStatus::Failed)->and($log->error)->toBe('http_401');
});

it('sends a WhatsApp test with the named template', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    config(['services.whatsapp.driver' => 'log']);
    app(UpdateMessagingChannels::class)->handle($admin, ['whatsapp' => ['provider' => 'cloud', 'phone_number_id' => '1234567890', 'access_token' => 'wa-token']]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);

    $log = app(SendTestMessage::class)->handle($admin, 'whatsapp', ['phone' => '+966500000555', 'template' => 'ejaz_test']);

    expect($log->status)->toBe(MessageStatus::Sent);
    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'ejaz_test');
});

it('validates the test number and limits test messages per admin', function () {
    config(['messaging.tests_per_hour' => 2]);
    $admin = userWithRole(RoleName::SuperAdmin);
    $send = app(SendTestMessage::class);

    expect(fn () => $send->handle($admin, 'sms', ['phone' => '0500000555']))->toThrow(ValidationException::class)
        ->and(fn () => $send->handle($admin, 'whatsapp', ['phone' => '+966500000555']))->toThrow(ValidationException::class);

    $send->handle($admin, 'sms', ['phone' => '+966500000555']);
    $send->handle($admin, 'sms', ['phone' => '+966500000555']);

    expect(fn () => $send->handle($admin, 'sms', ['phone' => '+966500000555']))->toThrow(ValidationException::class, __('messaging.test.too_many', ['minutes' => 60]));
});

it('warns the admin in production when SMS has no provider', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    Livewire::test(MessagingChannels::class)->assertSee(__('messaging.not_ready.heading'));

    MessagingChannel::query()->create(['channel' => 'sms', 'provider' => 'unifonic', 'options' => [], 'secrets' => ['api_key' => 'sid']]);

    Livewire::test(MessagingChannels::class)->assertDontSee(__('messaging.not_ready.heading'));
});

it('lists recent messages without their text', function () {
    MessageLog::query()->create(['channel' => 'sms', 'provider' => 'taqnyat', 'usage' => 'otp', 'phone_hint' => '***4321', 'status' => MessageStatus::Failed, 'error' => 'http_401']);
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    Livewire::test(MessagingChannels::class)->assertSee('***4321')->assertSee('http_401')->assertSee(__('messaging.usages.labels.otp'));
});

it('sends test messages to Saudi mobile numbers only, within a daily cap for everyone', function () {
    config(['messaging.tests_per_day' => 1]);
    $send = app(SendTestMessage::class);

    expect(fn () => $send->handle(userWithRole(RoleName::SuperAdmin), 'sms', ['phone' => '+447700900123']))->toThrow(ValidationException::class)
        ->and(fn () => $send->handle(userWithRole(RoleName::SuperAdmin), 'sms', ['phone' => "+966500000555\n"]))->toThrow(ValidationException::class);

    $send->handle(userWithRole(RoleName::SuperAdmin), 'sms', ['phone' => '+966500000555']);

    expect(fn () => $send->handle(userWithRole(RoleName::SuperAdmin), 'sms', ['phone' => '+966500000555']))->toThrow(ValidationException::class);
});

it('does not keep a typed key in the page state when saving is refused', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    Livewire::test(MessagingChannels::class)
        ->fillForm(['sms.provider' => 'taqnyat', 'sms.sender' => '<b>', 'sms.api_key' => CHANNELS_TEST_KEY])
        ->call('save')
        ->assertHasErrors()
        ->assertSet('data.sms.api_key', null);

    expect(MessagingChannel::count())->toBe(0);
});

it('treats keys that can no longer be decrypted as not configured, and lets the admin replace them', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    app(UpdateMessagingChannels::class)->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ', 'api_key' => CHANNELS_TEST_KEY]]);
    DB::table('messaging_channels')->update(['secrets' => 'not-decryptable']);

    expect(MessagingChannel::for('sms')->secret('api_key'))->toBeNull()
        ->and(MessagingSettings::ready('sms'))->toBeFalse();

    $this->actingAs($admin)->get(MessagingChannels::getUrl())->assertOk();

    app(UpdateMessagingChannels::class)->handle($admin, ['sms' => ['provider' => 'taqnyat', 'sender' => 'EJAZ', 'api_key' => 'new-key']]);
    expect(MessagingChannel::for('sms')->secret('api_key'))->toBe('new-key');
});
