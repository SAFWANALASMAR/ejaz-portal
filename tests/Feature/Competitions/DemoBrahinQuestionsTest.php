<?php

use App\Console\Commands\CreateDemoBrahinQuestions;
use App\Enums\QuestionType;
use App\Models\Competition;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Database\Seeders\BrahinCompetitionSeeder;

/*
| أربعون سؤالاً تجريبياً في بنك مسابقة البراهين لرابط العرض وحده (قرار صفوان، 3 نوفمبر 2026 — البند ٩ من إضافة توجيه المدير،
| docs/decisions/2026-11-03-director-home.md): موسومة «تجريبي» بلا محتوى علمي أو شرعي، ولا تُضاف في الإنتاج، وتُحذف بأمر واحد.
*/

function brahinCompetition(): Competition
{
    (new BrahinCompetitionSeeder)->run();

    return Competition::query()->where('slug', 'brahin-1448')->firstOrFail();
}

it('adds exactly forty demo questions marked «تجريبي» to one round of the Brahin bank, once', function () {
    $competition = brahinCompetition();

    $this->artisan('ejaz:demo-brahin-questions')->assertSuccessful();

    // بترتيب الإدراج صراحة: الاستعلام بلا ترتيب قد يعيدها بغيره تحت التشغيل المتوازي
    $questions = CreateDemoBrahinQuestions::demoQuestions($competition)->orderBy('id')->get();
    expect($questions)->toHaveCount(40)
        ->and($questions->pluck('round_id')->unique())->toHaveCount(1)
        ->and(CompetitionRound::query()->whereKey($questions->first()->round_id)->value('competition_id'))->toBe($competition->id)
        ->and($questions->first()->prompt)->toBe('سؤال تجريبي رقم 1 — 【ناقص: نص السؤال المعتمد من الجمعية】')
        ->and($questions->last()->prompt)->toBe('سؤال تجريبي رقم 40 — 【ناقص: نص السؤال المعتمد من الجمعية】');

    foreach ($questions as $question) {
        expect($question->type)->toBe(QuestionType::MultipleChoice)
            ->and(array_column($question->options, 'label'))->toBe(['خيار أ (تجريبي)', 'خيار ب (تجريبي)', 'خيار ج (تجريبي)', 'خيار د (تجريبي)']);
    }

    // لا تتكرر
    $this->artisan('ejaz:demo-brahin-questions')->assertSuccessful();
    expect(CreateDemoBrahinQuestions::demoQuestions($competition)->count())->toBe(40);
});

it('removes all the demo questions with one command, keeping the real ones', function () {
    $competition = brahinCompetition();
    $round = CompetitionRound::query()->where('competition_id', $competition->id)->orderBy('sort_order')->firstOrFail();
    $real = new CompetitionQuestion(['type' => QuestionType::MultipleChoice, 'prompt' => 'سؤال من البنك', 'options' => [['key' => 'a', 'label' => 'أ'], ['key' => 'b', 'label' => 'ب']], 'correct_option' => 'a', 'points' => 1, 'sort_order' => 1, 'round_id' => $round->id]);
    $real->competition_id = $competition->id;
    $real->save();

    $this->artisan('ejaz:demo-brahin-questions')->assertSuccessful();
    expect(CompetitionQuestion::query()->where('competition_id', $competition->id)->count())->toBe(41);

    $this->artisan('ejaz:demo-brahin-questions --remove')->assertSuccessful();

    expect(CreateDemoBrahinQuestions::demoQuestions($competition)->count())->toBe(0)
        ->and(CompetitionQuestion::query()->where('competition_id', $competition->id)->pluck('prompt')->all())->toBe(['سؤال من البنك']);
});

it('refuses to add the demo questions in production', function () {
    $competition = brahinCompetition();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('ejaz:demo-brahin-questions')->assertFailed();

    expect(CompetitionQuestion::query()->where('competition_id', $competition->id)->count())->toBe(0);
});

it('fails without the Brahin competition, adding nothing', function () {
    $this->artisan('ejaz:demo-brahin-questions')->assertFailed();

    expect(CompetitionQuestion::query()->count())->toBe(0);
});

// مراجعة المشرف على #157: والحذف أيضاً مرفوض في الإنتاج كأوامر العرض الأخرى، فلا يمس الأمر بنكاً حقيقياً
it('refuses to remove questions in production, deleting nothing', function () {
    $competition = brahinCompetition();
    $this->artisan('ejaz:demo-brahin-questions')->assertSuccessful();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('ejaz:demo-brahin-questions --remove')
        ->expectsOutput(__('competitions.demo_questions.refused'))
        ->assertFailed();

    expect(CreateDemoBrahinQuestions::demoQuestions($competition)->count())->toBe(40);
});
