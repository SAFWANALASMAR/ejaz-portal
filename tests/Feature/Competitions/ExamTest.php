<?php

use App\Actions\Competitions\SubmitExam;
use App\Contracts\WhatsAppNotifier;
use App\Enums\EducationStage;
use App\Enums\ExamStage;
use App\Enums\QuestionMedia;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| رحلة الاختبار الإلكتروني (docs/plans/competition-exam.md): ترتيب المراحل في الخادم، والنصوص الحرفية،
| وغياب الإجابة الصحيحة، والإرسال متكرر الأمان، ورقم الهوية وواتساب خلف مفتاحين معطّلين.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    config(['services.whatsapp.driver' => 'fake']);
    Storage::disk('local')->put('competition-media/intro.mp4', mp4Contents(30));

    $this->competition = Competition::factory()->exam()->create(['slug' => 'exam']);
    $this->questions = $this->competition->questions->values();
    $this->applicant = User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]);
    $this->actingAs($this->applicant)->post('/competitions/exam/entry/start');
});

function examSubmission(): CompetitionSubmission
{
    return CompetitionSubmission::query()->where('user_id', test()->applicant->id)->sole();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function examRegistration(array $overrides = []): array
{
    return ['full_name' => 'مشارك تجريبي', 'contact_email' => '', 'education_stage' => 'secondary', 'pledge' => '1', ...$overrides];
}

/**
 * المشارك حتى مرحلة الأسئلة: التسجيل، ثم الفيديو كاملاً، ثم التعليمات.
 */
function examReachQuestions(): void
{
    $test = test();
    $test->post('/competitions/exam/exam/register', examRegistration());
    $test->postJson('/competitions/exam/exam/intro/start')->assertOk();
    test()->travel(31)->seconds();
    $test->post('/competitions/exam/exam/intro/finish');
    $test->post('/competitions/exam/exam/instructions', ['accept' => '1']);
}

function examAnswerAll(string $option = 'a'): void
{
    foreach (test()->questions as $question) {
        test()->post('/competitions/exam/exam/answer', ['question' => $question->id, 'option' => $option, 'go' => 'next']);
    }
}

it('sends an exam entry to the staged journey, starting with the registration page and its exact texts', function () {
    $this->get('/competitions/exam/entry')->assertRedirect('/competitions/exam/exam');

    $page = $this->get('/competitions/exam/exam')->assertOk()
        ->assertSee('أتعهد بأن البيانات التي تمت تعبئتها صحيحة')
        ->assertSee('المتوسطة')
        ->assertSee('الثانوية')
        ->assertSee('الجامعية')
        ->assertSee('المهتمون بالثقافة العلمية')
        ->assertSee(__('competitions.exam.full_name'))
        ->assertSee('data-exam-gate-submit', false)
        ->assertDontSee(__('competitions.exam.national_id'));

    // زر المتابعة معطّل حتى تكتمل الحقول
    expect($page->getContent())->toMatch('/<button[^>]*data-exam-gate-submit[^>]*disabled/');
    expect(array_map(fn (EducationStage $stage): string => $stage->getLabel(), EducationStage::cases()))
        ->toBe(['المتوسطة', 'الثانوية', 'الجامعية', 'المهتمون بالثقافة العلمية']);
});

it('refuses an incomplete registration and keeps the participant on it', function () {
    $this->post('/competitions/exam/exam/register', examRegistration(['full_name' => '']))->assertSessionHasErrors('full_name');
    $this->post('/competitions/exam/exam/register', examRegistration(['pledge' => null]))->assertSessionHasErrors('pledge');
    $this->post('/competitions/exam/exam/register', examRegistration(['education_stage' => 'primary']))->assertSessionHasErrors('education_stage');
    $this->post('/competitions/exam/exam/register', examRegistration(['contact_email' => 'not-an-email']))->assertSessionHasErrors('contact_email');
    // رقم الهوية لا يُقبل والمفتاح معطّل
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '1000000008']))->assertSessionHasErrors('national_id');

    expect(examSubmission()->examStage())->toBe(ExamStage::Registration);

    $this->post('/competitions/exam/exam/register', examRegistration(['contact_email' => 'me@example.com']))->assertRedirect('/competitions/exam/exam');

    $submission = examSubmission();
    expect($submission->examStage())->toBe(ExamStage::Intro)
        ->and($submission->full_name)->toBe('مشارك تجريبي')
        ->and($submission->education_stage)->toBe(EducationStage::Secondary)
        ->and($submission->contact_email)->toBe('me@example.com')
        ->and($submission->national_id)->toBeNull();
});

it('lets no stage be skipped by a crafted request', function () {
    $first = $this->questions[0];

    $this->post('/competitions/exam/exam/intro/finish')->assertRedirect()->assertSessionHas('error');
    $this->postJson('/competitions/exam/exam/intro/start')->assertForbidden();
    $this->post('/competitions/exam/exam/instructions', ['accept' => '1'])->assertSessionHas('error');
    $this->get('/competitions/exam/exam/questions/1')->assertRedirect('/competitions/exam/exam');
    $this->get('/competitions/exam/exam/review')->assertRedirect('/competitions/exam/exam');
    $this->post('/competitions/exam/exam/answer', ['question' => $first->id, 'option' => 'a', 'go' => 'next'])->assertSessionHas('error');
    $this->post('/competitions/exam/exam/submit')->assertSessionHas('error');
    $this->get('/competitions/exam/exam/intro/video')->assertNotFound();

    // بعد التسجيل: لا تعليمات قبل الفيديو، ولا أسئلة قبل التعليمات
    $this->post('/competitions/exam/exam/register', examRegistration());
    $this->post('/competitions/exam/exam/instructions', ['accept' => '1'])->assertSessionHas('error');
    $this->post('/competitions/exam/exam/register', examRegistration(['full_name' => 'تغيير']))->assertSessionHas('error');

    $submission = examSubmission();
    expect($submission->examStage())->toBe(ExamStage::Intro)
        ->and($submission->full_name)->toBe('مشارك تجريبي')
        ->and($submission->answers)->toBeEmpty()
        ->and($submission->status)->toBe(SubmissionStatus::Draft);

    // نموذج المشاركة العام لا يتجاوز الرحلة
    $this->postJson('/competitions/exam/entry/autosave', ['answers' => [$first->id => 'a']])->assertForbidden();
    $this->post('/competitions/exam/entry/submit', ['answers' => [$first->id => 'a']])->assertForbidden();
});

it('enables the next step only after the whole intro video, checked on the server by its real duration', function () {
    $this->post('/competitions/exam/exam/register', examRegistration());

    $page = $this->get('/competitions/exam/exam')->assertOk()
        ->assertSee(__('competitions.exam.intro_heading'))
        ->assertSee('الانتقال إلى التعليمات')
        ->assertSee(route('competitions.exam.intro.video', 'exam'), false);
    expect($page->getContent())->toMatch('/<button[^>]*data-exam-intro-next[^>]*disabled/');

    $this->get('/competitions/exam/exam/intro/video')->assertOk()->assertHeader('Content-Type', 'video/mp4');

    // بلا بدء مسجل، ثم قبل مرور مدة المقطع: مرفوض
    $this->post('/competitions/exam/exam/intro/finish')->assertSessionHasErrors('intro');
    $this->postJson('/competitions/exam/exam/intro/start')->assertOk();
    $this->travel(10)->seconds();
    $this->post('/competitions/exam/exam/intro/finish')->assertSessionHasErrors('intro');

    // إعادة التشغيل لا تؤخر موعد البدء المسجل
    $started = examSubmission()->intro_started_at;
    $this->postJson('/competitions/exam/exam/intro/start')->assertOk();
    expect(examSubmission()->intro_started_at->equalTo($started))->toBeTrue();

    $this->travel(21)->seconds();
    $this->post('/competitions/exam/exam/intro/finish')->assertRedirect('/competitions/exam/exam')->assertSessionHasNoErrors();

    expect(examSubmission()->intro_watched_at)->not->toBeNull()
        ->and(examSubmission()->examStage())->toBe(ExamStage::Instructions);
    $this->get('/competitions/exam/exam/intro/video')->assertNotFound();
});

it('shows the nine instructions verbatim and starts only after the agreement box', function () {
    $this->post('/competitions/exam/exam/register', examRegistration());
    $this->postJson('/competitions/exam/exam/intro/start');
    $this->travel(31)->seconds();
    $this->post('/competitions/exam/exam/intro/finish');

    $page = $this->get('/competitions/exam/exam')->assertOk()->assertSeeInOrder([
        'التأكد من استقرار الاتصال بالإنترنت واستخدام جهاز ومتصفح مناسبين.',
        'الدخول عبر الرابط الرسمي وفي الموعد المحدد.',
        'اختيار إجابة واحدة فقط لكل سؤال.',
        'إمكانية التنقل إلى الأسئلة السابقة وتعديل الإجابات قبل الإرسال النهائي.',
        'عدم إغلاق الصفحة أو تحديثها أثناء الاختبار إلا عند الضرورة.',
        'عدم مشاركة رابط الاختبار أو بيانات الدخول مع الآخرين.',
        'احتساب النتيجة بناءً على الإجابات المسجلة في النظام الإلكتروني.',
        'عدم إمكانية تعديل الإجابات بعد الضغط على «إرسال وإنهاء الاختبار».',
        'عدم الضغط على زر الإرسال أكثر من مرة، والانتظار حتى ظهور رسالة تأكيد استلام الاستجابة.',
        'قرأت تعليمات الاختبار وأوافق على الالتزام بها',
        'بدء الاختبار',
    ], false);
    expect($page->getContent())->toMatch('/<button[^>]*data-exam-gate-submit[^>]*disabled/')
        ->and(__('competitions.exam.instructions'))->toHaveCount(9);

    $this->post('/competitions/exam/exam/instructions', [])->assertSessionHasErrors('accept');
    expect(examSubmission()->examStage())->toBe(ExamStage::Instructions);

    $this->post('/competitions/exam/exam/instructions', ['accept' => '1'])->assertRedirect('/competitions/exam/exam');
    $this->get('/competitions/exam/exam')->assertRedirect('/competitions/exam/exam/questions/1');
});

it('shows one question per page with its number, progress, four options and the status list, never the correct answer', function () {
    examReachQuestions();

    $page = $this->get('/competitions/exam/exam/questions/1')->assertOk()
        ->assertSee(__('competitions.exam.question_of', ['number' => 1, 'total' => 3]))
        ->assertSee('<progress max="3" value="1"', false)
        ->assertSee('نص علمي تجريبي')
        ->assertSee('سؤال الاختبار التجريبي 1')
        ->assertDontSee('سؤال الاختبار التجريبي 2')
        ->assertSee('السابق')
        ->assertSee('التالي')
        ->assertSee(__('competitions.exam.go_to', ['number' => 2, 'status' => __('competitions.exam.unanswered')]));

    $html = $page->getContent();
    expect(substr_count($html, 'type="radio" name="option"'))->toBe(4)
        ->and($html)->not->toContain('correct')
        ->and($html)->not->toContain('is_correct');

    $this->get('/competitions/exam/exam/questions/4')->assertNotFound();

    // السؤال المخزَّن في الذاكرة المؤقتة بلا عمود الإجابة الصحيحة
    expect(serialize(Cache::get('competition-exam-questions:'.$this->competition->id)))->not->toContain('correct_option');
});

it('saves each answer on the server while moving between questions, and allows changing it before submitting', function () {
    examReachQuestions();
    [$first, $second, $third] = $this->questions->all();

    $this->post('/competitions/exam/exam/answer', ['question' => $first->id, 'option' => 'b', 'go' => 'next'])->assertRedirect('/competitions/exam/exam/questions/2');
    $this->post('/competitions/exam/exam/answer', ['question' => $second->id, 'option' => '', 'go' => 'prev'])->assertRedirect('/competitions/exam/exam/questions/1');
    $this->post('/competitions/exam/exam/answer', ['question' => $first->id, 'option' => 'd', 'go' => '3'])->assertRedirect('/competitions/exam/exam/questions/3');
    $this->post('/competitions/exam/exam/answer', ['question' => $third->id, 'option' => 'a', 'go' => 'next'])->assertRedirect('/competitions/exam/exam/review');

    expect(examSubmission()->answers)->toBe([(string) $first->id => 'd', (string) $third->id => 'a']);

    $this->get('/competitions/exam/exam/questions/1')->assertSee(__('competitions.exam.go_to', ['number' => 1, 'status' => __('competitions.exam.answered')]));

    // خيار ليس من خيارات السؤال، وسؤال من مسابقة أخرى: مرفوضان
    $other = Competition::factory()->exam()->create();
    $this->post('/competitions/exam/exam/answer', ['question' => $first->id, 'option' => 'z', 'go' => 'next'])->assertSessionHasErrors('option');
    $this->post('/competitions/exam/exam/answer', ['question' => $other->questions->first()->id, 'option' => 'a', 'go' => 'next'])->assertSessionHasErrors('question');
    expect(examSubmission()->answers)->toBe([(string) $first->id => 'd', (string) $third->id => 'a']);
});

it('shows the review page with statuses, the exact submit button and the exact confirmation dialog', function () {
    examReachQuestions();
    $this->post('/competitions/exam/exam/answer', ['question' => $this->questions[0]->id, 'option' => 'a', 'go' => 'review']);

    $page = $this->get('/competitions/exam/exam/review')->assertOk()
        ->assertSee('إرسال وإنهاء الاختبار')
        ->assertSee('هل أنت متأكد من إرسال إجاباتك وإنهاء الاختبار؟ لن تتمكن من تعديل الإجابات بعد الإرسال')
        ->assertSee('العودة للمراجعة')
        ->assertSee('تأكيد الإرسال')
        ->assertSee('popovertarget="exam-confirm"', false);

    expect(substr_count($page->getContent(), __('competitions.exam.answered')))->toBeGreaterThanOrEqual(1)
        ->and(substr_count($page->getContent(), __('competitions.exam.unanswered')))->toBe(2);

    // الإلزامي غير المجاب يمنع الإرسال
    $this->post('/competitions/exam/exam/submit')->assertSessionHasErrors('answers');
    expect(examSubmission()->isDraft())->toBeTrue();
});

it('submits once, shows the exact finish message without answers or score, and allows no edits after', function () {
    examReachQuestions();
    examAnswerAll('c');

    $this->post('/competitions/exam/exam/submit')->assertRedirect('/competitions/exam/exam');
    $submission = examSubmission();

    expect($submission->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->reference)->not->toBeNull()
        ->and((float) $submission->auto_score)->toBe(3.0);

    $page = $this->get('/competitions/exam/exam')->assertOk()
        ->assertSee('شكرًا لك، تم الانتهاء من الاختبار،، نسأل الله لك التوفيق والسداد')
        ->assertSee($submission->reference);
    expect($page->getContent())->not->toContain('خيار ج1')->not->toContain('auto_score');

    // الإرسال الثاني يعيدها كما هي: بلا خطأ، وبلا رقم جديد ولا إشعار ثانٍ
    $this->post('/competitions/exam/exam/submit')->assertRedirect('/competitions/exam/exam')->assertSessionHasNoErrors();
    $again = app(SubmitExam::class)->handle($this->applicant, examSubmission());

    expect($again->reference)->toBe($submission->reference);
    Notification::assertSentToTimes($this->applicant, SubmissionReceived::class, 1);

    // لا تعديل بعد الإرسال
    $this->post('/competitions/exam/exam/answer', ['question' => $this->questions[0]->id, 'option' => 'a', 'go' => 'next'])->assertSessionHas('error');
    $this->get('/competitions/exam/exam/questions/1')->assertRedirect('/competitions/exam/exam');
    expect(examSubmission()->answers[(string) $this->questions[0]->id])->toBe('c');
});

it('handles two concurrent submits as one, under the row lock', function () {
    examReachQuestions();
    examAnswerAll();

    $submission = examSubmission();
    $first = app(SubmitExam::class)->handle($this->applicant, $submission);
    $second = app(SubmitExam::class)->handle($this->applicant, $submission->fresh());

    expect($second->reference)->toBe($first->reference)
        ->and(DB::table('competition_submissions')->whereNotNull('reference')->count())->toBe(1);
});

it('sends no WhatsApp message by default, and when enabled sends receipt, date and signature without answers or score', function () {
    $whatsapp = app(WhatsAppNotifier::class);

    examReachQuestions();
    examAnswerAll('c');
    $this->post('/competitions/exam/exam/submit');

    expect($whatsapp->sent)->toBe([]);

    // مشارك ثانٍ والمفتاح مفعّل
    config(['competitions.exam.whatsapp' => true]);
    $this->applicant = User::factory()->create(['phone' => '+966500000002', 'phone_verified_at' => now()]);
    $this->actingAs($this->applicant)->post('/competitions/exam/entry/start');
    examReachQuestions();
    examAnswerAll('c');
    $this->post('/competitions/exam/exam/submit');
    $this->post('/competitions/exam/exam/submit');

    expect($whatsapp->sent)->toHaveCount(1);
    $message = $whatsapp->sent[0]['message'];

    expect($whatsapp->sent[0]['phone'])->toBe('+966500000002')
        ->and($message)->toContain('جمعية إعجاز للدراسات والبحوث العلمية')
        ->and($message)->toContain('شكرًا لك، تم الانتهاء من الاختبار،، نسأل الله لك التوفيق والسداد')
        ->and($message)->toContain(now()->setTimezone(config('app.display_timezone'))->format('Y-m-d'))
        ->and($message)->not->toContain('خيار ج')
        ->and($message)->not->toContain('3.00');
});

it('collects the national ID only when enabled, validates its format, encrypts it and refuses a duplicate', function () {
    config(['competitions.exam.national_id' => true]);

    $this->get('/competitions/exam/exam')->assertSee(__('competitions.exam.national_id'));

    $this->post('/competitions/exam/exam/register', examRegistration())->assertSessionHasErrors('national_id');
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '1234567890']))->assertSessionHasErrors('national_id');
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '3000000005']))->assertSessionHasErrors('national_id');
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '١٠٠٠٠٠٠٠٠٨']))->assertSessionHasNoErrors();

    $submission = examSubmission();
    expect($submission->national_id)->toBe('1000000008')
        ->and(DB::table('competition_submissions')->where('id', $submission->id)->value('national_id'))->not->toContain('1000000008')
        ->and($submission->toArray())->not->toHaveKey('national_id');

    // مشارك آخر بالرقم نفسه في المسابقة نفسها
    $other = User::factory()->create(['phone' => '+966500000003', 'phone_verified_at' => now()]);
    $this->actingAs($other)->post('/competitions/exam/entry/start');
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '1000000008']))
        ->assertSessionHasErrors(['national_id' => __('competitions.exam.national_id_taken')]);
    $this->post('/competitions/exam/exam/register', examRegistration(['national_id' => '2000000006']))->assertSessionHasNoErrors();
});

it('serves question media only during the questions stage', function () {
    Storage::disk('local')->put('competition-media/q1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $this->questions[0]->forceFill(['media_type' => QuestionMedia::Image, 'media_path' => 'competition-media/q1.png'])->save();

    $this->get('/competitions/exam/exam/questions/1/media')->assertNotFound();

    examReachQuestions();

    $this->get('/competitions/exam/exam/questions/1')->assertSee(route('competitions.exam.media', ['exam', 1]), false);
    $this->get('/competitions/exam/exam/questions/1/media')->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get('/competitions/exam/exam/questions/2/media')->assertNotFound();
});

it('keeps the journey to its owner and to exam competitions', function () {
    $stranger = User::factory()->create(['phone' => '+966500000009', 'phone_verified_at' => now()]);

    $this->actingAs($stranger)->get('/competitions/exam/exam')->assertRedirect('/competitions/exam');
    $this->get('/competitions/exam/exam/questions/1')->assertNotFound();
    $this->post('/competitions/exam/exam/register', examRegistration())->assertNotFound();
    $this->post('/competitions/exam/exam/submit')->assertNotFound();

    $regular = Competition::factory()->withQuestions()->create(['slug' => 'regular']);
    $this->get('/competitions/regular/exam')->assertNotFound();

    auth()->logout();
    $this->get('/competitions/exam/exam')->assertRedirect('/login');
});

it('shows a closed exam as closed, and refuses its steps', function () {
    $this->post('/competitions/exam/exam/register', examRegistration());
    $this->competition->forceFill(['closes_at' => now()->subMinute()])->save();

    $this->get('/competitions/exam/exam')->assertOk()->assertSee(__('competitions.exam.closed'));
    $this->postJson('/competitions/exam/exam/intro/start')->assertForbidden();
});
