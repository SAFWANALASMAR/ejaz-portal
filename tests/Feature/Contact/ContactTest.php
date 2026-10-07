<?php

use App\Actions\Accounts\ExportUserData;
use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InquiryConfirmation;
use App\Notifications\InquiryReceived;
use App\Support\FormTimer;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    Setting::put('contact', ['email' => 'contact@example.test']);
});

/**
 * نموذج صحيح بختم وقت صادر الآن. ويُرسل بعد ثوانٍ بـ travel، كما يرسله الإنسان.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactForm(array $overrides = []): array
{
    return [
        'name' => 'زائر الموقع',
        'email' => 'visitor@example.com',
        'phone' => '0501234567',
        'type' => InquiryType::Inquiry->value,
        'subject' => 'سؤال عن البرامج',
        'message' => "السطر الأول\nالسطر الثاني",
        'form_token' => FormTimer::token(),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function sendContactForm(array $overrides = []): TestResponse
{
    $form = contactForm($overrides);
    test()->travel(5)->seconds();

    return test()->post('/contact', $form);
}

it('shows the contact details from the settings, and the form with its trap and timer', function () {
    Setting::put('contact', [
        'email' => 'contact@example.test',
        'phone' => '012 555 0000',
        'address' => 'العنوان التجريبي',
        'map_url' => 'https://maps.example.test/ejaz',
    ]);

    $this->get('/contact')->assertOk()
        ->assertSeeInOrder(['<h1', __('contact.title')], false)
        ->assertSee('href="mailto:contact@example.test"', false)
        ->assertSee('href="tel:0125550000"', false)
        ->assertSee('العنوان التجريبي')
        ->assertSee('href="https://maps.example.test/ejaz" target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('name="form_token"', false)
        ->assertSee('name="website" type="text" tabindex="-1"', false)
        ->assertSee(__('contact.form.title'));
});

it('never shows an unsafe map link, even if one reaches the database', function () {
    Setting::put('contact', ['map_url' => 'javascript:alert(1)']);

    expect($this->get('/contact')->assertOk()->getContent())->not->toContain('javascript:');
});

it('saves a message as a received request with a public reference, and notifies the association and the sender', function () {
    sendContactForm()->assertRedirect(route('contact'))->assertSessionHas('status');

    $inquiry = Inquiry::sole();

    expect($inquiry->reference)->toMatch('/^INQ-[A-HJ-NP-Z2-9]{8}$/')
        ->and($inquiry->status)->toBe(InquiryStatus::Received)
        ->and($inquiry->message)->toBe("السطر الأول\nالسطر الثاني")
        ->and($inquiry->user_id)->toBeNull()
        ->and(session('status'))->toContain($inquiry->reference);

    Notification::assertSentOnDemand(InquiryReceived::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'contact@example.test');
    Notification::assertSentOnDemand(InquiryConfirmation::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'visitor@example.com');
});

it('confirms to the sender with the reference only, never with what they wrote', function () {
    sendContactForm(['name' => 'اسم دعائي', 'subject' => 'عرض خاص', 'message' => 'زوروا https://spam.example.test']);

    Notification::assertSentOnDemand(InquiryConfirmation::class, function (InquiryConfirmation $notification) {
        $mail = (string) $notification->toMail(new AnonymousNotifiable)->render();

        return str_contains($mail, Inquiry::sole()->reference)
            && ! str_contains($mail, 'اسم دعائي')
            && ! str_contains($mail, 'عرض خاص')
            && ! str_contains($mail, 'spam.example.test');
    });
});

it('shows the association the message escaped, its lines kept and its links not turned into links', function () {
    $inquiry = Inquiry::factory()->create(['message' => "[اضغط هنا](https://evil.example.test)\n<script>alert(1)</script>"]);

    $mail = (string) (new InquiryReceived($inquiry))->toMail(new AnonymousNotifiable)->render();

    expect($mail)->toContain($inquiry->reference)
        ->toContain('[اضغط هنا](https://evil.example.test)')
        ->toContain('&lt;script&gt;')
        ->not->toContain('href="https://evil.example.test"')
        ->not->toContain('<script>alert(1)</script>');
});

it('keeps no message when the hidden trap is filled, yet answers like a success', function () {
    sendContactForm(['website' => 'https://bot.example.test'])->assertRedirect(route('contact'))->assertSessionHas('status');

    expect(Inquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses a message sent too fast, or with a broken or expired timer', function () {
    $this->post('/contact', contactForm())->assertSessionHasErrors(['form_token' => __('contact.form.too_fast')]);

    $this->travel(5)->seconds();
    $this->post('/contact', contactForm(['form_token' => 'broken']))->assertSessionHasErrors(['form_token' => __('contact.form.expired')]);

    $old = contactForm();
    $this->travel(3)->hours();
    $this->post('/contact', $old)->assertSessionHasErrors(['form_token' => __('contact.form.expired')]);

    expect(Inquiry::count())->toBe(0);
});

it('refuses wrong input under its field', function (array $data, string $field) {
    sendContactForm($data)->assertSessionHasErrors($field);

    expect(Inquiry::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'unknown type' => [['type' => 'complaint'], 'type'],
    'letters in the phone' => [['phone' => 'call me'], 'phone'],
    'control character in the subject' => [['subject' => "عنوان\x07"], 'subject'],
    'broken encoding in the message' => [['message' => "رسالة\xFF"], 'message'],
    'too long a message' => [['message' => str_repeat('ر', 5001)], 'message'],
]);

it('limits one email to five messages a day', function () {
    foreach (range(1, 5) as $number) {
        sendContactForm(['subject' => 'رسالة '.$number])->assertSessionHasNoErrors();
    }

    sendContactForm()->assertSessionHasErrors(['email' => __('contact.form.daily_limit')]);

    expect(Inquiry::count())->toBe(5);
});

it('limits each address to ten messages an hour', function () {
    expect(Route::getRoutes()->getByName('contact.store')?->gatherMiddleware())->toContain('throttle:10,60');
});

it('fills in a signed-in sender and links the message to their account, and shows it in their data copy', function () {
    $user = User::factory()->create(['name' => 'صاحب حساب', 'email' => 'member@example.com']);
    $this->actingAs($user);

    $this->get('/contact')->assertSee('value="member@example.com"', false)->assertSee('value="صاحب حساب"', false);

    sendContactForm(['name' => 'صاحب حساب', 'email' => 'member@example.com']);

    $inquiry = Inquiry::sole();

    expect($inquiry->user_id)->toBe($user->id)
        ->and(app(ExportUserData::class)->handle($user)['inquiries'][0]['reference'])->toBe($inquiry->reference);
});

it('still saves the message without an association email in the settings', function () {
    Setting::put('contact', []);

    sendContactForm()->assertSessionHasNoErrors();

    expect(Inquiry::count())->toBe(1);
    Notification::assertSentOnDemandTimes(InquiryReceived::class, 0);
    Notification::assertSentOnDemand(InquiryConfirmation::class);
});
