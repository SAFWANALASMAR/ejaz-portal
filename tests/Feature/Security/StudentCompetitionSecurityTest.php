<?php

use App\Actions\Competitions\ImportStudentQuestions;
use App\Enums\RoleName;
use App\Filament\Exports\ServiceRequestExporter;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Competitions\RelationManagers\RegistrationsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\SchoolNetworkLimits;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/*
| بعد دمج مسابقة الطلاب (#67) في الاختبار الأمني 0.14.0: حد تعيين كلمة المرور يتسع أيام الاختبار كالدخول، واستيراد
| الأسئلة يعيد الخلية التي اقتبسها safeCell كما كانت، ولو سبقت علامتَها فراغات، وحقول التسجيل المخصصة مرمَّزة.
|
| وبقية البند ٦ على main مغطاة في اختبارات المسابقة نفسها: محاولة طالب آخر وإجاباته (StudentCompetitionTest «does not let
| a student reach another student attempt»)، والدرجة قبل النشر («never shows the score before the results are
| published»)، والشهادة لصاحبها وحده («lets the owner download the certificate»)، والرابط المختصر لمسابقة منشورة
| بمسار داخلي وحده (BrahinCompetitionTest).
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

it('keeps the everyday reset-password cap per subscriber outside an exam window', function () {
    config(['security.password_update_per_subscriber_per_minute' => 3, 'competitions.student.window_limits.password_update_per_subscriber_per_minute' => 6]);
    $attempt = fn (int $i) => $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.40'])
        ->post('/reset-password', ['token' => 'x', 'email' => "student{$i}@example.test", 'password' => 'p', 'password_confirmation' => 'p']);

    expect(SchoolNetworkLimits::examWindow())->toBeFalse();

    foreach (range(1, 3) as $i) {
        expect($attempt($i)->status())->not->toBe(429);
    }

    $attempt(4)->assertTooManyRequests();
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

// البند ٦ على main بعد دمج #67 (الاختبار الأمني 0.14.0، «ما بعد دمج #67»): حقول التسجيل المخصصة في XSS. تسمية الحقل
// وخياراته من اللوحة في صفحة التسجيل، وقيمة الطالب (نص حر بلا PlainText) في جدول المسجلين في اللوحة
it('escapes the custom registration fields: labels and options on the form, and the student value in the panel', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    $payload = '"><img src=x onerror=alert(1)><script>alert(1)</script>';
    $competition = Competition::factory()->student()->create(['slug' => 'xss-students']);
    $text = $competition->fields()->create(['label' => $payload, 'type' => 'text', 'is_required' => false, 'sort_order' => 1]);
    $competition->fields()->create(['label' => 'المسار', 'type' => 'select', 'options' => [$payload], 'is_required' => false, 'sort_order' => 2]);

    $form = $this->actingAs(User::factory()->create())->get('/competitions/xss-students/register')->assertOk()->getContent();

    expect($form)->not->toContain('<img src=x onerror')->not->toContain('<script>alert(1)')->toContain(e($payload));

    registeredStudent($competition, 5, ['fields' => [$text->id => $payload]]);
    expect(CompetitionSubmission::query()->sole()->extra[(string) $text->id])->toBe($payload);

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));
    $panel = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $competition, 'pageClass' => ViewCompetition::class])->assertOk()->html();

    expect($panel)->not->toContain('<img src=x onerror')->not->toContain('<script>alert(1)')->toContain(e($payload));
});
