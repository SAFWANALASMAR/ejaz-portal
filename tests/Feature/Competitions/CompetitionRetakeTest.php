<?php

use App\Actions\Competitions\BuildStudentCompetitionCards;
use App\Actions\Competitions\GrantCompetitionRetake;
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
| للمدير التنفيذي والمسؤول التقني وحدهما بسبب مكتوب، والمحاولة القديمة تُحفظ في السجل وتخرج من النتائج، والإعادة في نافذة
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

it('archives the submitted attempt, lets the student start again with the full duration, and emails them', function () {
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'انقطع الاتصال في المدرسة');

    expect(CompetitionAttempt::query()->whereKey($this->attempt->id)->exists())->toBeFalse()
        ->and($retake->archived_attempt['id'])->toBe($this->attempt->id)
        ->and($retake->archived_attempt['reference'])->toBe($this->attempt->reference)
        ->and($retake->granted_by)->toBe($this->director->id)
        ->and($retake->reason)->toBe('انقطع الاتصال في المدرسة');
    Notification::assertSentTo($this->student, CompetitionRetakeGranted::class);

    $new = app(StartAttempt::class)->handle($this->student, $this->round->fresh());

    expect($new->id)->not->toBe($this->attempt->id)
        ->and($new->status)->toBe(AttemptStatus::InProgress)
        ->and((int) round($new->started_at->diffInMinutes($new->deadline_at)))->toBe($this->round->duration_minutes)
        ->and($retake->fresh()->used_at)->not->toBeNull();

    // الإعادة لمرة واحدة: بعد التسليم لا بدء ثالث
    app(SubmitAttempt::class)->handle($this->student, $new);
    expect(CompetitionAttempt::query()->where('submission_id', $new->submission_id)->count())->toBe(1);
});

it('requires a deadline after the round closes, and opens the closed round to this student alone until it', function () {
    closeRound($this->round);
    $other = registeredStudent($this->competition, 2);

    expect(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة'))->toThrow(ValidationException::class)
        ->and(fn () => app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->subMinute()))->toThrow(ValidationException::class);

    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDay());

    expect(fn () => app(StartAttempt::class)->handle($other, $this->round->fresh()))->toThrow(AuthorizationException::class);

    $new = app(StartAttempt::class)->handle($this->student, $this->round->fresh());
    // المهلة مهلة البدء، والمحاولة بمدتها الكاملة ولو بعد إغلاق النافذة
    expect((int) round($new->started_at->diffInMinutes($new->deadline_at)))->toBe($this->round->duration_minutes);
});

it('does not open the round after the retake deadline passes', function () {
    closeRound($this->round);
    $retake = app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addHour());
    $this->travel(2)->hours();

    expect(fn () => app(StartAttempt::class)->handle($this->student, $this->round->fresh()))->toThrow(AuthorizationException::class)
        ->and($retake->fresh()->used_at)->toBeNull();
});

it('lets only the executive director and the technical admin grant a retake', function (RoleName $role, bool $allowed) {
    $actor = userWithRole($role);
    $grant = fn () => app(GrantCompetitionRetake::class)->handle($actor, $this->attempt, 'سبب الإعادة');

    if ($allowed) {
        expect($grant())->toBeInstanceOf(CompetitionRetake::class);

        return;
    }

    expect($grant)->toThrow(AuthorizationException::class)
        ->and(CompetitionAttempt::query()->whereKey($this->attempt->id)->exists())->toBeTrue()
        ->and(CompetitionRetake::query()->count())->toBe(0);
    Notification::assertNothingSent();
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
        ->and(CompetitionRetake::query()->count())->toBe(0)
        ->and(CompetitionAttempt::query()->whereKey($this->attempt->id)->exists())->toBeTrue();
});

it('shows the start button on the account card and the round page, with the retake notice', function () {
    closeRound($this->round);
    app(GrantCompetitionRetake::class)->handle($this->director, $this->attempt, 'سبب الإعادة', now()->addDay());

    $cards = app(BuildStudentCompetitionCards::class)->handle($this->student);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['state'])->toBe('start')
        ->and($cards[0]['round']->is($this->round))->toBeTrue();

    $this->actingAs($this->student)->get(route('competitions.student.round', ['students', $this->round->uuid]))
        ->assertOk()->assertSee('data-retake', false)->assertSee(__('competitions.student.start'));
});
