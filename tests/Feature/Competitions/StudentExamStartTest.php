<?php

use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\StartAttempt;
use App\Enums\RankingScope;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Support\RoundQuestions;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/*
| بدء الاختبار وصفحاته تحت الحمل (reviews/2026-10-27-أداء-بدء-الاختبار.md): بنك المرحلة يُبنى مرة لكل إصدار في
| العملية، وفحص العدد مخزَّن بالإصدار، وحفظ المسابقة لا يفرّغ البنوك إلا بتغيّر الفئات المفتوحة أو نطاق الترتيب،
| والتسليم الآلي بتأخير عشوائي داخل هامش الشبكة.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    $this->competition = Competition::factory()->student()->create(['slug' => 'students']);
    $this->round = $this->competition->rounds()->firstOrFail();
});

/**
 * قراءات الذاكرة المشتركة لمفاتيح تبدأ بالبادئة، أصابت أو أخطأت.
 *
 * @return object{hits: int, misses: int}
 */
function cacheReads(string $prefix): object
{
    $reads = (object) ['hits' => 0, 'misses' => 0];

    Event::listen(CacheHit::class, fn (CacheHit $event) => $reads->hits += (int) str_starts_with($event->key, $prefix));
    Event::listen(CacheMissed::class, fn (CacheMissed $event) => $reads->misses += (int) str_starts_with($event->key, $prefix));

    return $reads;
}

it('builds the round bank once per version in the process, and sees a bank change at once', function () {
    $question = studentQuestion($this->round);
    $bank = RoundQuestions::load($this->round);

    expect(RoundQuestions::load($this->round)['questions'])->toBe($bank['questions']);

    $question->forceFill(['prompt' => 'نص معدّل'])->save();
    RoundQuestions::forget($this->round->id);

    expect(RoundQuestions::load($this->round)['questions']->get($question->id)?->prompt)->toBe('نص معدّل');
});

it('does not bring back a bank kept in the process after the shared cache is emptied', function () {
    $question = studentQuestion($this->round);
    Cache::flush();
    RoundQuestions::load($this->round);

    $question->forceFill(['prompt' => 'بعد التفريغ'])->save();
    RoundQuestions::forget($this->round->id);
    // تفريغ ثانٍ: رقم متسلسل كان يعود صفراً فيطابق البنك القديم المحفوظ في العملية
    Cache::flush();

    expect(RoundQuestions::load($this->round)['questions']->get($question->id)?->prompt)->toBe('بعد التفريغ');
});

it('reads the bank from the shared cache once per process and checks the bank count once per version, however many students start', function () {
    studentQuestion($this->round);
    $students = [registeredStudent($this->competition, 1), registeredStudent($this->competition, 2)];
    $bank = cacheReads('competition-round-questions:');
    $shortfall = cacheReads('competition-round-shortfall:');

    app(StartAttempt::class)->handle($students[0], $this->round->fresh());
    $first = [clone $bank, clone $shortfall];
    app(StartAttempt::class)->handle($students[1], $this->round->fresh());

    expect($first[0]->hits + $first[0]->misses)->toBe(1)
        ->and($first[1]->misses)->toBeGreaterThan(0)
        ->and($bank->hits + $bank->misses)->toBe(1)
        ->and($shortfall->misses)->toBe($first[1]->misses)
        ->and($this->round->attempts()->count())->toBe(2);
});

it('sends the student from start straight to the first open unit, and back to it when starting again', function () {
    studentQuestion($this->round);
    studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $base = '/competitions/students/rounds/'.$this->round->uuid;

    $this->actingAs($student)->post($base.'/start')->assertRedirect(url($base.'/units/1'));

    $attempt = $this->round->attempts()->sole();
    $first = $attempt->layout[0]['questions'][0];
    $this->post($base.'/units/1', ['go' => 'next', 'answers' => [$first['id'] => $attempt->optionToken($first['id'], 'b')]]);

    // سؤالان منفردان: وحدتان بسؤال لكل منهما، والأولى أُجيبت
    $this->post($base.'/start')->assertRedirect(url($base.'/units/2'));
    expect($this->round->attempts()->count())->toBe(1);
});

it('keeps the round banks when the competition is saved without touching its open categories or ranking scope', function () {
    studentQuestion($this->round);
    $director = userWithRole(RoleName::ExecutiveDirector);
    $save = fn (array $data) => app(SaveCompetition::class)->handle($director, $this->competition->fresh(), [
        'title' => 'عنوان معدّل أثناء النافذة', 'slug' => 'students', 'opens_at' => $this->competition->opens_at, 'closes_at' => $this->competition->closes_at, ...$data,
    ]);
    $key = RoundQuestions::key($this->round->id);

    $save([]);
    expect(RoundQuestions::key($this->round->id))->toBe($key);

    $save(['ranking_scope' => RankingScope::Category->value]);
    expect(RoundQuestions::key($this->round->id))->not->toBe($key);

    $key = RoundQuestions::key($this->round->id);
    $save(['open_categories' => [CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id')]]);
    expect(RoundQuestions::key($this->round->id))->not->toBe($key);
});

it('spreads the automatic submission over at most half the network grace and ten seconds', function (int $grace, int $spread) {
    config(['competitions.student.grace_seconds' => $grace]);
    studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    app(StartAttempt::class)->handle($student, $this->round->fresh());

    $this->actingAs($student)->get('/competitions/students/rounds/'.$this->round->uuid.'/units/1')
        ->assertOk()
        ->assertSee('data-exam-auto-spread="'.$spread.'"', false);
})->with(['default grace' => [20, 10], 'short grace' => [6, 3], 'long grace' => [60, 10]]);
