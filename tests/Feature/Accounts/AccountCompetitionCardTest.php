<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\Dates;

/*
| بطاقة مسابقة الطلاب أعلى «حسابي» (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026، المجموعة ج — BuildStudentCompetitionCards): المرحلة
| الحالية وزر واحد بحالته، و«مسابقاتي» في القائمة الجانبية بأيقونة الكأس.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    $this->competition = Competition::factory()->student()->create(['slug' => 'brahin-test', 'title' => 'مسابقة البراهين التجريبية']);
    $this->round = $this->competition->rounds()->firstOrFail();
    studentQuestion($this->round);
    $this->student = registeredStudent($this->competition);
});

/**
 * @return array{state: string, html: string}
 */
function accountCompetitionCard(User $user): array
{
    $html = (string) test()->actingAs($user)->get(route('account.dashboard'))->assertOk()->getContent();
    preg_match('/data-account-competition="(\w+)"/', $html, $state);

    return ['state' => $state[1] ?? '', 'html' => (string) str($html)->after('data-account-competition=')->before('</section>')];
}

it('shows the current stage with «start the test» while its window is open, before the other account sections', function () {
    $card = accountCompetitionCard($this->student);
    $html = (string) $this->get(route('account.dashboard'))->getContent();

    expect($card['state'])->toBe('start')
        ->and($card['html'])->toContain('مسابقة البراهين التجريبية')
        ->toContain(e(__('competitions.account_card.round', ['name' => $this->round->name])))
        ->toContain(__('competitions.account_card.start'))
        ->toContain(route('competitions.student.round', [$this->competition->slug, $this->round->uuid]))
        // أعلى الصفحة قبل بطاقات الأقسام
        ->and(strpos($html, 'data-account-competition='))->toBeLessThan(strpos($html, 'data-account-cards'));
});

it('says «continue the test» once started, and «answers submitted ✓» with the status link after submitting', function () {
    $attempt = app(StartAttempt::class)->handle($this->student, $this->round);

    expect(accountCompetitionCard($this->student))->state->toBe('resume')
        ->html->toContain(__('competitions.account_card.resume'));

    app(SubmitAttempt::class)->handle($this->student, $attempt);

    expect(accountCompetitionCard($this->student))->state->toBe('submitted')
        ->html->toContain(__('competitions.account_card.submitted'))
        ->toContain(route('competitions.student.status', [$this->competition->slug, $this->round->uuid]));
});

it('says «time is up» when the started attempt or the stage window has run out, with no button', function () {
    $attempt = app(StartAttempt::class)->handle($this->student, $this->round);
    $this->travelTo($attempt->deadline_at->copy()->addMinutes(5));

    expect(accountCompetitionCard($this->student))->state->toBe('ended')
        ->html->toContain(__('competitions.account_card.ended'))->not->toContain('<a ');

    $other = registeredStudent($this->competition, 2);
    $this->travelTo($this->round->ends_at->copy()->addMinute());

    expect(accountCompetitionCard($other))->state->toBe('ended');
});

it('shows the next stage with its start time when none is running', function () {
    $next = new CompetitionRound(['name' => 'المرحلة الثانية', 'sort_order' => 2, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(3), 'question_count' => 1, 'duration_minutes' => 30]);
    $next->competition_id = $this->competition->id;
    $next->save();
    $this->round->forceFill(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()])->save();

    expect(accountCompetitionCard($this->student))->state->toBe('upcoming')
        ->html->toContain(e(__('competitions.account_card.round', ['name' => 'المرحلة الثانية'])))
        ->toContain(e(__('competitions.account_card.upcoming', ['date' => Dates::display($next->starts_at, withTime: true)])))
        ->not->toContain('<a ');
});

it('shows no card to an unregistered account, for an unpublished competition, or after the registration is cancelled', function () {
    expect(accountCompetitionCard(User::factory()->create())['state'])->toBe('');

    $this->competition->update(['is_published' => false]);
    expect(accountCompetitionCard($this->student)['state'])->toBe('');

    $this->competition->update(['is_published' => true]);
    CompetitionSubmission::query()->where('user_id', $this->student->id)->firstOrFail()->delete();
    expect(accountCompetitionCard($this->student)['state'])->toBe('');
});

it('names the account competitions page «my competitions» with the trophy icon', function () {
    $html = (string) $this->actingAs($this->student)->get(route('account.competitions'))->assertOk()->getContent();

    expect(__('competitions.account.nav'))->toBe('مسابقاتي')
        ->and(__('competitions.account.title'))->toBe('مسابقاتي')
        ->and($html)->toContain('مسابقاتي')->not->toContain('مشاركاتي')
        ->and(collect(config('account.navigation'))->firstWhere('route', 'account.competitions')['icon'])->toBe('trophy');
});
