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
use App\Notifications\AttemptReceived;
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

    Http::assertSent(fn (Request $request): bool => $request['template']['name'] === 'ejaz_exam_receipt');
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
