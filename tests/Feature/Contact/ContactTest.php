<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Users\AnonymizeUser;
use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Enums\RoleName;
use App\Http\Requests\ContactRequest;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InquiryConfirmation;
use App\Notifications\InquiryReceived;
use App\Support\FormTimer;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    Setting::put('contact', ['email' => 'contact@example.test']);
});

/**
 * نموذج صحيح بختم وقت صادر الآن لجلسة الزائر.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactForm(string $session, array $overrides = []): array
{
    return [
        'name' => 'زائر الموقع',
        'email' => 'visitor@example.com',
        'phone' => '0501234567',
        'type' => InquiryType::Inquiry->value,
        'subject' => 'سؤال عن البرامج',
        'message' => "السطر الأول\nالسطر الثاني",
        'form_token' => FormTimer::token($session),
        ...$overrides,
    ];
}

/**
 * يرسل النموذج بعد ثوانٍ من فتحه، كما يرسله الإنسان، من الجلسة التي صدر لها الختم.
 *
 * @param  array<string, mixed>  $overrides
 */
function sendContactForm(array $overrides = [], int $seconds = 5, ?string $session = null): TestResponse
{
    $session ??= Str::random(40);
    $form = contactForm($session, $overrides);
    test()->travel($seconds)->seconds();

    return test()->withCookie(config('session.cookie'), $session)->post('/contact', $form);
}

it('shows the contact details from the settings, the form with its trap and timer, and why the data is collected', function () {
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
        ->assertSee('name="'.ContactRequest::TRAP.'" type="text" tabindex="-1"', false)
        ->assertSee(__('contact.form.title'))
        // غرض الجمع عند نقطة الجمع، ولو لم تُنشر سياسة الخصوصية بعد
        ->assertSee(__('contact.form.privacy'));
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

it('tells the association the reference and type only, keeping everything the sender wrote in the panel', function () {
    $inquiry = Inquiry::factory()->create([
        'name' => 'اسم المرسل',
        'email' => 'sender@example.com',
        'phone' => '0509998887',
        'subject' => '[اضغط هنا](https://evil.example.test)',
        'message' => 'نص الرسالة الخاص',
        'internal_note' => 'ملاحظة الموظفين',
    ]);

    $mail = (string) (new InquiryReceived($inquiry))->toMail(new AnonymousNotifiable)->render();

    expect($mail)->toContain($inquiry->reference)
        ->toContain($inquiry->type->getLabel())
        ->not->toContain('evil.example.test')
        ->not->toContain('اسم المرسل')
        ->not->toContain('sender@example.com')
        ->not->toContain('0509998887')
        ->not->toContain('نص الرسالة الخاص')
        ->not->toContain('ملاحظة الموظفين');
});

it('answers a filled trap like a success and keeps nothing, before checking anything else', function (array $form) {
    DB::enableQueryLog();

    sendContactForm($form)->assertRedirect(route('contact'))->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn (string $status) => preg_match('/INQ-[A-Z2-9]{8}/', $status) === 1);

    // الرقم الوهمي بلا استعلام، فلا يكلّف الروبوت القاعدة شيئاً
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'inquiries')))->toBeEmpty()
        ->and(Inquiry::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'trap' => [[ContactRequest::TRAP => 'https://bot.example.test']],
    'trap with a broken timer' => [[ContactRequest::TRAP => 'x', 'form_token' => 'broken']],
    'trap with long text and bad fields' => [[ContactRequest::TRAP => str_repeat('x', 500), 'email' => 'bad']],
]);

it('answers a message sent within seconds like a success and keeps nothing', function () {
    sendContactForm(seconds: 1)->assertRedirect(route('contact'))->assertSessionHas('status');

    expect(Inquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

it('asks for a refresh when the timer is broken, expired or from another session', function () {
    sendContactForm(['form_token' => 'broken'])->assertSessionHasErrors(['form_token' => __('contact.form.expired')]);
    sendContactForm(seconds: 3 * 3600)->assertSessionHasErrors(['form_token' => __('contact.form.expired')]);

    // ختم صدر لجلسة أخرى، فلا يُنقل بين الزوار
    $form = contactForm(Str::random(40));
    $this->travel(5)->seconds();
    $this->withCookie(config('session.cookie'), Str::random(40))->post('/contact', $form)
        ->assertSessionHasErrors(['form_token' => __('contact.form.expired')]);

    expect(Inquiry::count())->toBe(0);
});

it('keeps the first timer after a validation error, so a quick correction is saved, not taken for a bot', function () {
    $session = Str::random(40);
    $form = contactForm($session);
    $this->travel(5)->seconds();

    $this->withCookie(config('session.cookie'), $session)->post('/contact', [...$form, 'email' => 'bad'])->assertSessionHasErrors('email');

    // الصفحة بعد الخطأ تعرض الختم نفسه، والتصحيح بعد ثانية يُحفظ
    $this->withCookie(config('session.cookie'), $session)->get('/contact')->assertSee('value="'.$form['form_token'].'"', false);
    $this->travel(1)->seconds();
    $this->withCookie(config('session.cookie'), $session)->post('/contact', $form)->assertSessionHasNoErrors();

    expect(Inquiry::count())->toBe(1);
});

it('uses a timer once, so a double click or a replay never makes two messages', function () {
    $session = Str::random(40);
    $form = contactForm($session);
    $this->travel(5)->seconds();

    $this->withCookie(config('session.cookie'), $session)->post('/contact', $form)->assertSessionHasNoErrors();
    $this->withCookie(config('session.cookie'), $session)->post('/contact', $form)->assertSessionHasErrors(['form_token' => __('contact.form.already_sent')]);

    // والختم المستهلك لا يعود إلى الصفحة
    expect($this->withCookie(config('session.cookie'), $session)->get('/contact')->getContent())->not->toContain($form['form_token'])
        ->and(Inquiry::count())->toBe(1);
});

it('refuses wrong input under its field', function (array $data, string $field) {
    sendContactForm($data)->assertSessionHasErrors($field);

    expect(Inquiry::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'unknown type' => [['type' => 'complaint'], 'type'],
    'letters in the phone' => [['phone' => 'call me'], 'phone'],
    'too long a phone' => [['phone' => '+'.str_repeat('1', 20)], 'phone'],
    'folding white space in the email' => [['email' => "visitor\r\n @example.com"], 'email'],
    'right-to-left override in the subject' => [['subject' => "عنوان\u{202E}مقلوب"], 'subject'],
    'control character in the subject' => [['subject' => "عنوان\x07"], 'subject'],
    'too long a message' => [['message' => str_repeat('ر', 5001)], 'message'],
]);

// والترميز المكسور يُرفض بـ 400 قبل الجلسة (RejectInvalidUtf8)، لا تحت حقله: إعادته في الجلسة كانت تُسقط حفظها
it('refuses a broken encoding in the message before the session', function () {
    sendContactForm(['message' => "رسالة\xFF"])->assertStatus(400);

    expect(Inquiry::count())->toBe(0);
});

it('limits one mailbox to five messages a day, whatever way its address is written', function () {
    foreach (['visitor@gmail.com', 'Visitor@Gmail.com', 'v.isitor@gmail.com', 'visitor+news@gmail.com', 'vis.itor+x@googlemail.com'] as $email) {
        sendContactForm(['email' => $email])->assertSessionHasNoErrors();
    }

    sendContactForm(['email' => 'visitor@gmail.com'])->assertSessionHasErrors(['email' => __('contact.form.daily_limit')]);

    expect(Inquiry::count())->toBe(5);
});

it('limits the messages of each subscriber, each IPv6 site and the whole portal', function () {
    expect(Route::getRoutes()->getByName('contact.store')?->gatherMiddleware())->toContain('throttle:contact');

    $keys = fn (string $ip): array => collect(RateLimiter::limiter('contact')(Request::create('/contact', 'POST', server: ['REMOTE_ADDR' => $ip])))
        ->map(fn ($limit): string => $limit->key)
        ->all();

    // IPv4: العنوان نفسه. IPv6: المشترك /64 والموقع /48، فلا يُلتف بتدوير العناوين
    expect($keys('203.0.113.7'))->toBe(['contact:203.0.113.7', 'contact:all'])
        ->and($keys('2001:db8:aa:bb:1:2:3:4'))->toBe(['contact:2001:db8:aa:bb::/64', 'contact:all', 'contact:2001:db8:aa::/48'])
        ->and($keys('2001:db8:aa:bb:9:9:9:9'))->toBe($keys('2001:db8:aa:bb:1:2:3:4'));
});

it('answers 429 once a subscriber reaches the hourly limit', function () {
    config(['security.contact_per_subscriber_per_hour' => 2]);

    sendContactForm(['email' => 'one@example.com'])->assertSessionHasNoErrors();
    sendContactForm(['email' => 'two@example.com'])->assertSessionHasNoErrors();
    sendContactForm(['email' => 'three@example.com'])->assertStatus(429);

    expect(Inquiry::count())->toBe(2);
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

it('hides the sender of the account messages, and of the guest messages from its verified email, when the account is anonymized', function () {
    $user = User::factory()->create(['email' => 'secret@example.com', 'email_verified_at' => now()]);
    $inquiry = Inquiry::factory()->create(['user_id' => $user->id, 'name' => 'اسم سري', 'email' => 'secret@example.com', 'phone' => '0501112223']);
    $deleted = Inquiry::factory()->create(['user_id' => $user->id, 'email' => 'secret@example.com']);
    $deleted->delete();
    $guest = Inquiry::factory()->create(['email' => 'Secret@Example.com', 'name' => 'اسم قبل التسجيل']);
    $other = Inquiry::factory()->create(['email' => 'other@example.com']);
    $user->forceFill(['deletion_requested_at' => now()])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user);

    $user->refresh();

    expect($user->email)->not->toBe('secret@example.com')
        ->and($inquiry->fresh())->name->toBe(__('users.anonymized_name'))->email->toBe($user->email)->phone->toBeNull()
        ->and(Inquiry::withTrashed()->find($deleted->id)->email)->toBe($user->email)
        ->and($guest->fresh())->email->toBe($user->email)->name->toBe(__('users.anonymized_name'))
        ->and($other->fresh()->email)->toBe('other@example.com');
});

it('deletes the text of the sender messages on anonymization, keeping what the statistics need', function () {
    $user = User::factory()->create(['email' => 'writer@example.com', 'email_verified_at' => now()]);
    $text = ['subject' => 'موضوع فيه اسمي', 'message' => 'أنا فلان، جوالي 0501112223', 'internal_note' => 'نقلت من رسالته'];
    $inquiry = Inquiry::factory()->create(['user_id' => $user->id, 'type' => InquiryType::Suggestion, 'status' => InquiryStatus::Closed, ...$text]);
    $deleted = Inquiry::factory()->create(['user_id' => $user->id, ...$text]);
    $deleted->delete();
    $guest = Inquiry::factory()->create(['email' => 'Writer@Example.com', ...$text]);
    $other = Inquiry::factory()->create(['email' => 'other@example.com', ...$text]);
    $user->forceFill(['deletion_requested_at' => now()])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user);

    foreach ([$inquiry->fresh(), Inquiry::withTrashed()->find($deleted->id), $guest->fresh()] as $cleared) {
        expect($cleared)->subject->toBeNull()->message->toBeNull()->internal_note->toBeNull();
    }

    expect($inquiry->fresh())
        ->reference->toBe($inquiry->reference)
        ->type->toBe(InquiryType::Suggestion)
        ->status->toBe(InquiryStatus::Closed)
        ->created_at->toEqual($inquiry->created_at)
        ->and($other->fresh())->subject->toBe('موضوع فيه اسمي')->message->toBe('أنا فلان، جوالي 0501112223');
});

it('leaves the guest messages of an unverified email untouched, since nothing proves they are the same person', function () {
    $user = User::factory()->create(['email' => 'claimed@example.com', 'email_verified_at' => null]);
    $guest = Inquiry::factory()->create(['email' => 'claimed@example.com']);
    $user->forceFill(['deletion_requested_at' => now()])->save();

    expect(app(ExportUserData::class)->handle($user)['inquiries'])->toBe([]);

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user);

    expect($guest->fresh()->email)->toBe('claimed@example.com');
});

it('includes the guest messages from a verified email in the data copy', function () {
    $user = User::factory()->create(['email' => 'member@example.com', 'email_verified_at' => now()]);
    $guest = Inquiry::factory()->create(['email' => 'Member@Example.com']);

    expect(app(ExportUserData::class)->handle($user)['inquiries'][0]['reference'])->toBe($guest->reference);
});

it('still saves the message without an association email in the settings', function () {
    Setting::put('contact', []);

    sendContactForm()->assertSessionHasNoErrors();

    expect(Inquiry::count())->toBe(1);
    Notification::assertSentOnDemandTimes(InquiryReceived::class, 0);
    Notification::assertSentOnDemand(InquiryConfirmation::class);
});
