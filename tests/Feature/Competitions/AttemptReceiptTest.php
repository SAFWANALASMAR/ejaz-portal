<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Actions\Messaging\DeliverMessage;
use App\Contracts\WhatsAppNotifier;
use App\Jobs\SendExamReceipt;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Notifications\AttemptReceived;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| بريد تأكيد الاستلام بعد تسليم محاولة في مسابقة الطلاب، يدوياً أو آلياً (قرار صفوان والمدير التنفيذي، 30 أكتوبر 2026):
| لمن في حسابه بريد وحده، ومرة واحدة لكل محاولة، بلا إجابات ولا درجة. وواتساب خلف مفتاحه من اللوحة.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

    $this->competition = Competition::factory()->student()->create(['slug' => 'students', 'title' => 'مسابقة تجريبية للإشعار']);
    $this->round = $this->competition->rounds()->firstOrFail();
    $this->round->forceFill(['branch' => 'الحقائق العلمية في الأنفس'])->save();
    $this->question = studentQuestion($this->round);
});

function receiptAttempt(bool $withEmail = true, int $serial = 1): CompetitionAttempt
{
    $student = registeredStudent(test()->competition, $serial);
    $student->forceFill(['email' => $withEmail ? 'student'.$serial.'@example.test' : null])->save();

    return app(StartAttempt::class)->handle($student, test()->round);
}

it('emails the receipt when the student submits, with the stage, the domain, the time and the reference but no answers or score', function () {
    Notification::fake();
    $attempt = receiptAttempt();

    $this->actingAs($attempt->user)->post('/competitions/students/rounds/'.$this->round->uuid.'/submit')->assertRedirect();

    $attempt->refresh();
    expect($attempt->receipt_sent_at)->not->toBeNull();

    Notification::assertSentTo($attempt->user, AttemptReceived::class, function (AttemptReceived $notification) use ($attempt): bool {
        $mail = $notification->toMail($attempt->user);
        $text = implode("\n", $mail->introLines);

        return $mail->subject === __('competitions.student.receipt_mail.subject')
            && str_contains($text, 'مسابقة تجريبية للإشعار')
            && str_contains($text, __('competitions.student.receipt_mail.round', ['round' => $this->round->name]))
            && str_contains($text, 'الحقائق العلمية في الأنفس')
            && str_contains($text, (string) $attempt->reference)
            && str_contains($text, __('competitions.student.receipt_mail.manual'))
            && ! str_contains($text, 'خيار ب')
            && ! str_contains($text, __('competitions.student.your_score', ['score' => '0', 'max' => '1']));
    });
    Notification::assertCount(1);
});

it('emails the receipt when the scheduled command closes an expired attempt, marked automatic', function () {
    Notification::fake();
    $attempt = receiptAttempt();

    $this->travel(31)->minutes();
    $this->artisan('ejaz:close-expired-attempts')->assertSuccessful();

    Notification::assertSentTo($attempt->user, AttemptReceived::class, fn (AttemptReceived $notification): bool => $notification->attempt->auto_submitted
        && str_contains(implode("\n", $notification->toMail($attempt->user)->introLines), __('competitions.student.receipt_mail.auto')));
});

it('skips the email silently for an account without an email', function () {
    Notification::fake();
    $attempt = receiptAttempt(withEmail: false);

    $this->actingAs($attempt->user)->post('/competitions/students/rounds/'.$this->round->uuid.'/submit')->assertRedirect();

    Notification::assertNothingSent();
    expect($attempt->fresh()->receipt_sent_at)->toBeNull()->and($attempt->fresh()->submitted_at)->not->toBeNull();
});

it('sends the receipt once however often the attempt is submitted', function () {
    Notification::fake();
    $attempt = receiptAttempt();

    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    $this->travel(31)->minutes();
    $this->artisan('ejaz:close-expired-attempts')->assertSuccessful();
    app(SubmitAttempt::class)->handle(null, $attempt->fresh(), auto: true);

    Notification::assertSentTimes(AttemptReceived::class, 1);
});

it('queues the email after the submission is committed', function () {
    expect(new AttemptReceived(new CompetitionAttempt))->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('does not send the WhatsApp receipt while its switch is off, and sends it with the attempt time when on', function () {
    Queue::fake([SendExamReceipt::class]);
    $attempt = receiptAttempt();
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);

    Queue::assertNotPushed(SendExamReceipt::class);

    config(['services.whatsapp.driver' => 'log']);
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890', 'templates' => ['exam_receipt' => 'ejaz_exam_receipt'], 'usages' => ['exam_receipt' => true]],
        'secrets' => ['access_token' => 'wa-token'],
    ]);
    $second = receiptAttempt(serial: 2);
    $second->user->forceFill(['phone' => '+966500000124'])->save();
    app(SubmitAttempt::class)->handle($second->user, $second);

    Queue::assertPushed(SendExamReceipt::class, fn (SendExamReceipt $job): bool => $job->attemptId === $second->id && $job->submissionId === $second->submission_id);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.Y']]])]);
    (new SendExamReceipt($second->submission_id, $second->id))->handle(app(WhatsAppNotifier::class), app(DeliverMessage::class));

    // registeredStudent يضع «رقم الجوال» في التسجيل، فإليه تذهب الرسالة لا إلى جوال الحساب (قرار صفوان، 31 أكتوبر 2026)
    Http::assertSent(fn (Request $request): bool => $request['template']['name'] === 'ejaz_exam_receipt'
        && $request['to'] === $second->submission->guardian_phone && $request['to'] !== '+966500000124');
});

it('skips the WhatsApp receipt for an attempt of another registration', function () {
    Http::fake();
    $attempt = receiptAttempt();
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    $other = registeredStudent($this->competition, 3);
    $other->forceFill(['phone' => '+966500000125'])->save();
    $otherSubmission = (int) CompetitionSubmission::query()->where('user_id', $other->id)->value('id');

    (new SendExamReceipt($otherSubmission, $attempt->id))->handle(app(WhatsAppNotifier::class), app(DeliverMessage::class));

    Http::assertNothingSent();
});

/*
| البريد غير الموثق يصله الإيصال كما أدخله صاحبه، ومعه رابط التوثيق الموقّع نفسه (قرار صفوان، 31 أكتوبر 2026).
*/

/**
 * رابط التوثيق في الإيصال كما يظهر في HTML الرسالة بعد فك ترميزه كما يقرؤه المتصفح، أو null إن لم يكن فيه.
 */
function receiptVerificationLink(AttemptReceived $notification, User $user): ?string
{
    $html = (string) $notification->toMail($user)->render();

    return preg_match('/href="([^"]*\/email\/verify\/[^"]*)"/', $html, $match) === 1 ? html_entity_decode($match[1]) : null;
}

/**
 * رابط التوثيق في الإيصال الوحيد المرسل إلى الطالب.
 */
function sentReceiptLink(User $student): ?string
{
    $link = null;
    Notification::assertSentTo($student, AttemptReceived::class, function (AttemptReceived $notification, array $channels, User $notifiable) use (&$link): bool {
        $link = receiptVerificationLink($notification, $notifiable);

        return true;
    });

    return $link;
}

it('emails the receipt to an unverified email as entered, with a link that verifies it, and never to a deactivated account', function () {
    Notification::fake();
    enableVerification();
    $unverified = receiptAttempt();
    $unverified->user->forceFill(['email_verified_at' => null])->save();
    app(SubmitAttempt::class)->handle($unverified->user, $unverified);

    $inactive = receiptAttempt(serial: 2);
    $inactive->user->forceFill(['email_verified_at' => now(), 'is_active' => false])->save();
    app(SubmitAttempt::class)->handle(null, $inactive, auto: true);

    Notification::assertSentTimes(AttemptReceived::class, 1);
    Notification::assertSentTo($unverified->user, AttemptReceived::class, fn (AttemptReceived $notification, array $channels, User $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'student1@example.test'
        && str_contains(implode("\n", $notification->toMail($notifiable)->outroLines), __('competitions.student.receipt_mail.verify_line', ['minutes' => 60])));

    $link = sentReceiptLink($unverified->user);
    expect($link)->toStartWith(url('/email/verify/'.$unverified->user->id.'/'))->toContain('signature=')->toContain('expires=');

    $this->actingAs($unverified->user)->get((string) $link)->assertRedirect();

    expect($unverified->user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('adds no verification link when the email is already verified, or once it is verified before the receipt goes out', function () {
    Notification::fake();
    enableVerification();
    $verified = receiptAttempt();
    $verified->user->forceFill(['email_verified_at' => now()])->save();
    app(SubmitAttempt::class)->handle($verified->user, $verified);

    $late = receiptAttempt(serial: 2);
    $late->user->forceFill(['email_verified_at' => null])->save();
    app(SubmitAttempt::class)->handle($late->user, $late);
    $late->user->forceFill(['email_verified_at' => now()])->save();

    Notification::assertSentTimes(AttemptReceived::class, 2);
    foreach ([$verified->user, $late->user] as $user) {
        Notification::assertSentTo($user, AttemptReceived::class, fn (AttemptReceived $notification, array $channels, User $notifiable): bool => receiptVerificationLink($notification, $notifiable->fresh()) === null
            && ! str_contains(implode("\n", $notification->toMail($notifiable->fresh())->outroLines), __('competitions.student.receipt_mail.verify_line', ['minutes' => 60])));
    }
});

it('sends the unverified receipt with its link while verification is off too', function () {
    Notification::fake();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['email_verified_at' => null])->save();
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);

    expect(sentReceiptLink($attempt->user))->not->toBeNull();
});

it('rejects the receipt link without signing in, for another signed-in account, a tampered link, or after it expires', function () {
    Notification::fake();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['email_verified_at' => null])->save();
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    $link = (string) sentReceiptLink($attempt->user);
    $intruder = receiptAttempt(serial: 2)->user;
    $intruder->forceFill(['email_verified_at' => null])->save();

    $this->get($link)->assertRedirect(route('login'));
    $this->actingAs($intruder)->get($link)->assertForbidden();
    $this->actingAs($attempt->user)->get(str_replace('signature=', 'signature=0', $link))->assertForbidden();
    $this->travel(61)->minutes();
    $this->actingAs($attempt->user)->get($link)->assertForbidden();

    expect($attempt->user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($intruder->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('does not verify through the receipt link an email another account already verified', function () {
    Notification::fake();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['email_verified_at' => null])->save();
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    $link = (string) sentReceiptLink($attempt->user);
    User::factory()->create(['email' => 'student1@example.test', 'email_verified_at' => now()]);

    $this->actingAs($attempt->user)->get($link);

    expect($attempt->user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends no receipt to an unverified email that another account has verified, since it belongs to someone else', function () {
    Notification::fake();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['email_verified_at' => null])->save();
    User::factory()->create(['email' => 'student1@example.test', 'email_verified_at' => now()]);

    app(SubmitAttempt::class)->handle($attempt->user, $attempt);

    Notification::assertNothingSent();
    expect($attempt->fresh()->submitted_at)->not->toBeNull();
});

/*
| واتساب عند التسليم خلف مفتاحه: رسالة واحدة إلى جوال ولي الأمر إن وُجد، وإلا جوال الطالب (قرار صفوان، 31 أكتوبر 2026).
*/

function enableExamWhatsApp(): void
{
    config(['services.whatsapp.driver' => 'log']);
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890', 'templates' => ['exam_receipt' => 'ejaz_exam_receipt'], 'usages' => ['exam_receipt' => true]],
        'secrets' => ['access_token' => 'wa-token'],
    ]);
}

/**
 * يسلّم المحاولة ثلاث مرات (يدوياً مرتين ثم آلياً)، ويشغّل كل مهمة واتساب دُفعت، ويعيد الأرقام التي أُرسل إليها.
 *
 * @return list<string>
 */
function submitAndCollectWhatsApp(CompetitionAttempt $attempt): array
{
    Queue::fake([SendExamReceipt::class]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.Z']]])]);

    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    app(SubmitAttempt::class)->handle($attempt->user, $attempt);
    app(SubmitAttempt::class)->handle(null, $attempt->fresh(), auto: true);

    Queue::pushed(SendExamReceipt::class)->each(fn (SendExamReceipt $job) => $job->handle(app(WhatsAppNotifier::class), app(DeliverMessage::class)));

    return Http::recorded()->map(fn (array $pair): string => (string) $pair[0]['to'])->values()->all();
}

it('sends one WhatsApp receipt to the guardian phone when the registration has one', function () {
    enableExamWhatsApp();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['phone' => '+966500000124'])->save();
    $attempt->submission->forceFill(['guardian_phone' => '+966511111111'])->save();

    expect(submitAndCollectWhatsApp($attempt))->toBe(['+966511111111']);
});

it('sends one WhatsApp receipt to the student phone when the registration has no guardian phone', function () {
    enableExamWhatsApp();
    $attempt = receiptAttempt();
    $attempt->user->forceFill(['phone' => '+966500000124'])->save();
    $attempt->submission->forceFill(['guardian_phone' => null])->save();

    expect(submitAndCollectWhatsApp($attempt))->toBe(['+966500000124']);
});

it('sends no WhatsApp receipt without a guardian or a student phone, or while the switch is off', function () {
    enableExamWhatsApp();
    $attempt = receiptAttempt();
    $attempt->submission->forceFill(['guardian_phone' => null])->save();

    expect(submitAndCollectWhatsApp($attempt))->toBe([]);

    MessagingChannel::query()->delete();
    $off = receiptAttempt(serial: 2);
    $off->user->forceFill(['phone' => '+966500000126'])->save();

    expect(submitAndCollectWhatsApp($off))->toBe([]);
    Queue::assertNotPushed(SendExamReceipt::class);
});

it('keeps the WhatsApp receipt of the older electronic exam on the account phone, which has no guardian phone', function () {
    enableExamWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.E']]])]);
    $submission = CompetitionSubmission::factory()->submitted()->create(['user_id' => User::factory()->create(['phone' => '+966500000127'])->id]);

    (new SendExamReceipt($submission->id))->handle(app(WhatsAppNotifier::class), app(DeliverMessage::class));

    expect($submission->fresh()->guardian_phone)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['to'] === '+966500000127');
});

it('puts the receipts on their own queue', function () {
    expect((new AttemptReceived(new CompetitionAttempt))->viaQueues())->toBe(['mail' => 'receipts'])
        ->and(config('horizon.defaults.supervisor-receipts.queue'))->toBe(['receipts']);
});

it('registers load-test students with the required mobile number and school field', function () {
    (new BrahinCompetitionSeeder)->run();
    $brahin = Competition::query()->where('slug', 'brahin-1448')->sole();
    $brahin->forceFill(['is_published' => true])->save();
    $this->artisan('ejaz:load-accounts', ['count' => 2])->assertSuccessful();

    $this->artisan('ejaz:load-students', ['competition' => 'brahin-1448', 'count' => 2])->assertSuccessful();

    $submissions = CompetitionSubmission::query()->where('competition_id', $brahin->id)->get();
    expect($submissions)->toHaveCount(2)
        ->and($submissions->every(fn (CompetitionSubmission $submission): bool => filled($submission->guardian_phone)))->toBeTrue();
});
