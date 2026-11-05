<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

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

it('links a started attempt past its deadline to its status, and says «time is up» with no button once the stage window is over', function () {
    $attempt = app(StartAttempt::class)->handle($this->student, $this->round);
    $this->travelTo($attempt->deadline_at->copy()->addMinutes(5));

    // ومحاولته المنتهية تسلّمها صفحة الحالة آلياً، فالزر إليها (مراجعة المشرف على #182، L5)
    expect(accountCompetitionCard($this->student))->state->toBe('expired')
        ->html->toContain(__('competitions.account_card.expired'))
        ->toContain(route('competitions.student.status', [$this->competition->slug, $this->round->uuid]));

    $other = registeredStudent($this->competition, 2);
    $this->travelTo($this->round->ends_at->copy()->addMinute());

    expect(accountCompetitionCard($other))->state->toBe('ended')
        ->html->toContain(__('competitions.account_card.ended'))->not->toContain('<a ');
});

// الحدود كقواعد المرحلة نفسها (CompetitionRound::isRunning): تبدأ عند موعدها بالضبط وتنتهي عند نهايتها بالضبط (L3)
it('starts exactly at the stage start and ends exactly at its end', function () {
    $this->round->forceFill(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(4)])->save();

    $this->travelTo($this->round->starts_at);
    expect(accountCompetitionCard($this->student)['state'])->toBe('start');

    $this->travelTo($this->round->ends_at);
    expect(accountCompetitionCard($this->student)['state'])->toBe('ended');
});

// البطاقة لا تعرض رابطاً يرفضه المسار (StartAttempt — مراجعة المشرف على #182، I2)
it('offers no start for a stage whose results are published, or for a qualified-only stage the student did not qualify for', function () {
    $this->round->forceFill(['results_published_at' => now()])->save();
    expect(accountCompetitionCard($this->student))->state->toBe('ended')->html->not->toContain('<a ');
    expect(fn () => app(StartAttempt::class)->handle($this->student, $this->round->fresh()))->toThrow(AuthorizationException::class);

    // مرحلة ثانية للمتأهلين جارية، والطالب سلّم الأولى ولم يتأهل
    $this->round->forceFill(['results_published_at' => null])->save();
    app(SubmitAttempt::class)->handle($this->student, app(StartAttempt::class)->handle($this->student, $this->round));
    $this->round->forceFill(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'results_published_at' => now()->subHour()])->save();
    $second = new CompetitionRound(['name' => 'المرحلة الثانية', 'sort_order' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(3), 'question_count' => 1, 'duration_minutes' => 30, 'qualified_only' => true]);
    $second->competition_id = $this->competition->id;
    $second->save();

    expect(accountCompetitionCard($this->student))->state->toBe('not_qualified')
        ->html->toContain(__('competitions.account_card.not_qualified'))->not->toContain('<a ');
    expect(fn () => app(StartAttempt::class)->handle($this->student, $second))->toThrow(AuthorizationException::class, __('competitions.student.not_qualified'));

    // ومن تأهل يرى «ابدأ الاختبار»
    CompetitionAttempt::query()->where('round_id', $this->round->id)->update(['qualified_at' => now()]);
    expect(accountCompetitionCard($this->student)['state'])->toBe('start');
});

it('shows no card for a soft-deleted competition', function () {
    $this->competition->delete();

    expect(accountCompetitionCard($this->student)['state'])->toBe('');
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

it('renames the competitions card of the account home once, keeping an admin edited text', function () {
    $migration = require database_path('migrations/2026_11_05_010000_rename_competitions_account_card.php');
    $card = fn () => DB::table('account_cards')->where('route', 'account.competitions');

    // بيئة أُنشئت بطاقتها قبل التسمية الجديدة
    $card()->update(['title' => 'مشاركاتي', 'description' => 'مشاركاتك في المسابقات وحالة كل منها ورقمها المرجعي.']);
    $migration->up();
    expect($card()->first())->title->toBe('مسابقاتي')->description->toBe('مسابقاتك وحالة كل منها ورقمها المرجعي.');

    $html = (string) $this->actingAs($this->student)->get(route('account.dashboard'))->getContent();
    expect((string) str($html)->after('data-account-cards'))->toContain('مسابقاتي')->not->toContain('مشاركاتي');

    // وما عدّلته الإدارة لا يُمس
    $card()->update(['title' => 'عنوان من اللوحة']);
    $migration->down();
    $migration->up();
    expect($card()->value('title'))->toBe('عنوان من اللوحة');
});
