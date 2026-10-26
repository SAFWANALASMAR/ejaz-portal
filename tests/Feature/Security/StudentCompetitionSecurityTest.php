<?php

use App\Actions\Competitions\ImportStudentQuestions;
use App\Enums\RoleName;
use App\Filament\Exports\ServiceRequestExporter;
use App\Models\Competition;
use App\Support\SchoolNetworkLimits;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/*
| بعد دمج مسابقة الطلاب (#67) في الاختبار الأمني 0.14.0: حد تعيين كلمة المرور يتسع أيام الاختبار كالدخول، واستيراد
| الأسئلة يعيد الخلية التي اقتبسها safeCell كما كانت، ولو سبقت علامتَها فراغات.
*/

beforeEach(function () {
    SchoolNetworkLimits::forget();
});

it('widens the reset-password cap per subscriber on exam days only', function () {
    expect(SchoolNetworkLimits::limit('password_update_per_subscriber_per_minute'))->toBe(120);

    Competition::factory()->student()->create();
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::examWindow())->toBeTrue()
        ->and(SchoolNetworkLimits::limit('password_update_per_subscriber_per_minute'))->toBe(600);
});

it('lets a school behind one address reset passwords past the everyday cap on an exam day', function () {
    config(['security.password_update_per_subscriber_per_minute' => 3, 'competitions.student.window_limits.password_update_per_subscriber_per_minute' => 6]);
    $attempt = fn (int $i) => $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.40'])
        ->post('/reset-password', ['token' => 'x', 'email' => "student{$i}@example.test", 'password' => 'p', 'password_confirmation' => 'p']);

    Competition::factory()->student()->create();
    SchoolNetworkLimits::forget();

    foreach (range(1, 6) as $i) {
        expect($attempt($i)->status())->not->toBe(429);
    }

    $attempt(7)->assertTooManyRequests();
});

it('imports back a question cell that safeCell quoted, even after leading spaces', function (string $prompt) {
    config(['competitions.student.min_questions' => 1]);
    $manager = userWithRole(RoleName::ProgramManager);
    $round = Competition::factory()->student()->create(['created_by' => $manager->id])->rounds()->firstOrFail();

    $path = tempnam(sys_get_temp_dir(), 'q').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['النوع', 'الفئة', 'النص', 'السؤال', '1', '2', '3', '4', '5', '6', 'الصحيح', 'الدرجة']));
    $writer->addRow(Row::fromValues(['اختيار من متعدد', '', '', ServiceRequestExporter::safeCell($prompt), 'أ', 'ب', '', '', '', '', '1', '1']));
    $writer->close();

    expect(app(ImportStudentQuestions::class)->handle($manager, $round, $path))->toBe(1)
        ->and($round->questions()->sole()->prompt)->not->toStartWith("'")
        ->and($round->questions()->sole()->prompt)->toEndWith(ltrim($prompt, " \u{00A0}\u{FEFF}"));
})->with(['formula' => '=1+1', 'nbsp before' => "\u{00A0}=1+1", 'space before' => ' @SUM(1)']);
