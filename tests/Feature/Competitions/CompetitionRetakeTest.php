<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Competitions\BuildStudentCompetitionCards;
use App\Actions\Competitions\GrantCompetitionRetake;
use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Actions\Competitions\RevokeCompetitionRetake;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRetake;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Notifications\CompetitionRetakeGranted;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| «السماح بإعادة الاختبار» لطالب بعينه (قرار صفوان، 13 نوفمبر 2026 — docs/decisions/2026-11-13-feature-competition-retake.md):
| للمدير التنفيذي والمسؤول التقني وحدهما بسبب مكتوب، والمحاولة القديمة تبقى حتى البدء ثم تُحفظ في السجل وتخرج من النتائج، والإعادة في نافذة
| المرحلة أو حتى مهلة يحددها المسؤول بعد إغلاقها، بمدتها الكاملة، ويُبلَّغ الطالب ببطاقة «حسابي» وبالبريد.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Notification::fake();

    $this->competition = Competition::factory()->student()->create(['slug' => 'students']);
    $this->round = $this->competition->rounds()->firstOrFail();
    studentQuestion($this->round);
    $this->student = registeredStudent($this->competition);
    $this->attempt = app(SubmitAttempt::class)->handle($this->student, app(StartAttempt::class)->handle($this->student, $this->round->fresh()));
    $this->director = userWithRole(RoleName::ExecutiveDirector);
});

function closeRound(CompetitionRound $round): void
{
    CompetitionRound::query()->whereKey($round->id)->update(['starts_at' => now()->subHours(5), 'ends_at' => now()->subHour()]);
}

it('keeps the attempt in the results until the student starts, then archives it and gives the full duration', function () {
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'انقطع الاتصال في المدرسة');

    // المحاولة باقية في النتائج حتى يبدأ الإعادة فعلاً
    expect(CompetitionAttempt::query()->whereKey($this->attempt->id)->exists())->toBeTrue()
        ->and($retake->archived_attempt)->toBeNull()
        ->and($retake->granted_by)->toBe($this->director->id);
    Notification::assertSentTo($this->student, CompetitionRetakeGranted::class);

    $new = app(StartAttempt::class)->handle($this->student, $this->round->fresh());
    $retake->refresh();

    expect($new->id)->not->toBe($this->attempt->id)
        ->and($new->status)->toBe(AttemptStatus::InProgress)
        ->and((int) round($new->started_at->diffInMinutes($new->deadline_at)))->toBe($this->round->duration_minutes)
        ->and(CompetitionAttempt::query()->whereKey($this->attempt->id)->exists())->toBeFalse()
        ->and($retake->archived_attempt['reference'])->toBe($this->attempt->reference)
        ->and($retake->used_at)->not->toBeNull();

    // ضغطة «ابدأ» ثانية تعيد المحاولة الجارية نفسها
    expect(app(StartAttempt::class)->handle($this->student, $this->round->fresh())->id)->toBe($new->id);

    // والإعادة لمرة واحدة: بعد التسليم يعيد البدء المحاولة المسلَّمة ولا ينشئ ثالثة
    $submitted = app(SubmitAttempt::class)->handle($this->student, $new);
    expect(app(StartAttempt::class)->handle($this->student, $this->round->fresh())->id)->toBe($submitted->id)
        ->and(CompetitionAttempt::query()->where('submission_id', $new->submission_id)->count())->toBe(1);
});

it('keeps the original attempt when the student does not start before the deadline', function () {
    closeRound($this->round);
    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addHour());
    $this->travel(2)->hours();

    expect(app(StartAttempt::class)->handle($this->student, $this->round->fresh())->id)->toBe($this->attempt->id)
        ->and(CompetitionAttempt::query()->findOrFail($this->attempt->id)->status)->toBe(AttemptStatus::Submitted);
});

it('requires a deadline after the round closes, and opens the closed round to this student alone until it', function () {
    closeRound($this->round);
    $other = registeredStudent($this->competition, 2);

    expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة'))->toThrow(ValidationException::class)
        ->and(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->subMinute()))->toThrow(ValidationException::class)
        ->and(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDays(GrantCompetitionRetake::MAX_DAYS + 1)))->toThrow(ValidationException::class);

    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDay());

    expect(fn () => app(StartAttempt::class)->handle($other, $this->round->fresh()))->toThrow(AuthorizationException::class);

    $new = app(StartAttempt::class)->handle($this->student, $this->round->fresh());
    // المهلة مهلة البدء، والمحاولة بمدتها الكاملة ولو بعد إغلاق النافذة
    expect((int) round($new->started_at->diffInMinutes($new->deadline_at)))->toBe($this->round->duration_minutes);
});

it('lets only the executive director and the technical admin grant a retake', function (RoleName $role, bool $allowed) {
    $actor = userWithRole($role);
    $grant = fn () => app(GrantCompetitionRetake::class)->handle($actor, $this->attempt, 'سبب الإعادة');

    if ($allowed) {
        expect($grant())->toBeInstanceOf(CompetitionRetake::class);

        return;
    }

    expect($grant)->toThrow(AuthorizationException::class)
        ->and(CompetitionRetake::query()->count())->toBe(0);
    // إيصال تسليم المحاولة الأصلية يصل في التهيئة، ولا إشعار إعادة
    Notification::assertNotSentTo($this->student, CompetitionRetakeGranted::class);
})->with([
    'executive director' => [RoleName::ExecutiveDirector, true],
    'technical admin' => [RoleName::TechnicalAdmin, true],
    'super admin (read only)' => [RoleName::SuperAdmin, false],
    'content editor' => [RoleName::ContentEditor, false],
    'program manager' => [RoleName::ProgramManager, false],
]);

it('refuses a student account', function () {
    expect(fn () => app(GrantCompetitionRetake::class)->handle(User::factory()->create(), $this->attempt, 'سبب الإعادة'))->toThrow(AuthorizationException::class);
});

it('refuses an attempt still in progress, a missing reason, and a round whose results are published', function () {
    $other = registeredStudent($this->competition, 2);
    $running = app(StartAttempt::class)->handle($other, $this->round->fresh());

    expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $running, 'سبب الإعادة'))->toThrow(ValidationException::class)
        ->and(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, '  '))->toThrow(ValidationException::class);

    CompetitionRound::query()->whereKey($this->round->id)->update(['results_published_at' => now()]);

    expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt->fresh(), 'سبب الإعادة'))->toThrow(ValidationException::class)
        ->and(CompetitionRetake::query()->count())->toBe(0);
});

it('refuses a second open retake, a pending schedule and an unpublished competition', function () {
    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة');
    expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب ثانٍ'))->toThrow(ValidationException::class);

    CompetitionRetake::query()->delete();

    foreach ([['schedule_pending' => true], ['schedule_pending' => false, 'is_published' => false]] as $change) {
        $this->competition->forceFill($change)->save();
        expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt->fresh(), 'سبب الإعادة'))->toThrow(ValidationException::class);
    }

    expect(CompetitionRetake::query()->count())->toBe(0);
});

it('blocks publishing while an open retake waits, and never reopens a published round', function () {
    closeRound($this->round);
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDay());

    expect(fn () => app(PublishRoundResults::class)->handle($this->director, $this->round->fresh()))->toThrow(ValidationException::class)
        ->and($this->round->fresh()->resultsPublished())->toBeFalse();

    // ولو نُشرت النتائج بطريق آخر، لا تفتح الإعادة المرحلة ولا تظهر بطاقة البدء
    CompetitionRound::query()->whereKey($this->round->id)->update(['results_published_at' => now()]);

    expect(fn () => app(StartAttempt::class)->handle($this->student, $this->round->fresh()))->not->toThrow(Throwable::class)
        ->and(app(StartAttempt::class)->handle($this->student, $this->round->fresh())->id)->toBe($this->attempt->id)
        ->and($retake->fresh()->used_at)->toBeNull()
        ->and(app(BuildStudentCompetitionCards::class)->handle($this->student)[0]['state'])->not->toBe('start');
});

it('purges archived answers and the reason after the retention period, and exports the retake without its reason', function () {
    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب داخلي');
    app(StartAttempt::class)->handle($this->student, $this->round->fresh());

    $export = app(ExportUserData::class)->handle($this->student);
    expect($export['competition_retakes'])->toHaveCount(1)
        ->and(json_encode($export['competition_retakes'], JSON_UNESCAPED_UNICODE))->not->toContain('سبب داخلي');

    app(PurgeSubmissionPersonalData::class)->handle($this->attempt->submission()->withTrashed()->firstOrFail());
    $retake = CompetitionRetake::query()->sole();

    expect($retake->reason)->toBe('')
        ->and($retake->archived_attempt['answers'])->toBeNull()
        ->and($retake->archived_attempt['reference'])->toBe($this->attempt->reference);
});

it('shows the start button on the account card and the round pages, with the retake notice', function () {
    closeRound($this->round);
    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDay());

    $cards = app(BuildStudentCompetitionCards::class)->handle($this->student);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['state'])->toBe('start')
        ->and($cards[0]['round']->is($this->round))->toBeTrue();

    $this->actingAs($this->student)->get(route('competitions.student.round', ['students', $this->round->uuid]))
        ->assertOk()->assertSee('data-retake', false)->assertSee(__('competitions.student.start'));
    $this->actingAs($this->student)->get(route('competitions.student.status', ['students', $this->round->uuid]))
        ->assertOk()->assertSee('data-retake', false);
});

it('lets who grants a retake revoke it before it is used, which unblocks publishing and keeps the attempt', function () {
    closeRound($this->round);
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'منحت خطأً', now()->addDay());

    expect(fn () => app(RevokeCompetitionRetake::class)->handle(userWithRole(RoleName::SuperAdmin), $retake))->toThrow(AuthorizationException::class);

    app(RevokeCompetitionRetake::class)->handle(userWithRole(RoleName::TechnicalAdmin), $retake);

    expect(CompetitionRetake::query()->count())->toBe(0)
        ->and(app(StartAttempt::class)->handle($this->student, $this->round->fresh())->id)->toBe($this->attempt->id);

    $this->travel(1)->minutes();
    app(PublishRoundResults::class)->handle($this->director, $this->round->fresh());
    expect($this->round->fresh()->resultsPublished())->toBeTrue();
});

it('does not revoke a retake the student already started', function () {
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة');
    app(StartAttempt::class)->handle($this->student, $this->round->fresh());

    expect(fn () => app(RevokeCompetitionRetake::class)->handle($this->director, $retake->fresh()))->toThrow(ValidationException::class)
        ->and(CompetitionRetake::query()->count())->toBe(1);
});
