<?php

use App\Actions\Accounts\ExportUserData;
use App\Enums\ServiceRequestStatus;
use App\Enums\VideoStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\SubmissionVideo;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
| قرار صفوان على مراجعة #2، بالإعداد الموحد مع المسابقات (القضية #6): إجابات الطلبات ومرفقاتها وإجابات
| التسجيل في البرامج لمن حُذف حسابه تُحذف بعد مدة الاحتفاظ، محسوبة من إخفاء بياناته. ويبقى السجل نفسه
| (الرقم المرجعي والحالة والتواريخ) لما يلزم الاحتفاظ به.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->deleted = User::factory()->create(['deletion_requested_at' => now()->subDays(40), 'anonymized_at' => now()->subDays(31)]);
});

/**
 * طلب مرسل بإجابة ومرفق على القرص الخاص.
 */
function requestWithFile(User $user): ServiceRequest
{
    $path = 'service-requests/'.fake()->uuid().'.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');

    return ServiceRequest::factory()->for($user)->create([
        'answers' => ['about' => 'نبذة شخصية'],
        'files' => ['cv' => ['path' => $path, 'name' => 'سيرتي.pdf', 'size' => 8]],
    ]);
}

it('keeps request and registration data while the retention period is not set', function () {
    $request = requestWithFile($this->deleted);
    $registration = ProgramRegistration::factory()->for($this->deleted)->create(['answers' => ['why' => 'سبب شخصي']]);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($request->fresh()->answers)->not->toBeNull()
        ->and($registration->fresh()->answers)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles('service-requests'))->toHaveCount(1);
});

it('purges request answers and files and registration answers of a deleted account after the period', function () {
    Setting::put('retention', ['days' => 30]);
    $request = requestWithFile($this->deleted);
    $removed = requestWithFile($this->deleted);
    $removed->delete();
    $registration = ProgramRegistration::factory()->for($this->deleted)->create(['answers' => ['why' => 'سبب شخصي']]);

    $recent = User::factory()->create(['deletion_requested_at' => now()->subDays(5), 'anonymized_at' => now()->subDays(4)]);
    $waiting = requestWithFile($recent);
    $kept = requestWithFile(User::factory()->create());

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($request->fresh()->answers)->toBeNull()
        ->and($request->fresh()->files)->toBeNull()
        ->and($request->fresh()->reference)->not->toBeNull()
        ->and(ServiceRequest::withTrashed()->find($removed->id)->files)->toBeNull()
        ->and($registration->fresh()->answers)->toBeNull()
        ->and($registration->fresh()->status)->toBe($registration->status)
        ->and(Storage::disk('local')->allFiles('service-requests'))->toHaveCount(2)
        ->and($waiting->fresh()->answers)->not->toBeNull()
        ->and($kept->fresh()->files)->not->toBeNull();

    expect(Activity::where('event', 'personal_data_purged')->where('subject_type', $request->getMorphClass())->count())->toBe(2)
        ->and(Activity::where('event', 'personal_data_purged')->where('subject_type', $registration->getMorphClass())->count())->toBe(1);

    // مرة ثانية لا تمس شيئاً ولا تسجل من جديد
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(Activity::where('event', 'personal_data_purged')->count())->toBe(3);
});

/**
 * قرص الملفات الخاصة يتعذر عليه حذف المسارات المذكورة، ويعيد false بلا استثناء كما تفعل الأقراص
 * المضبوطة بـ throw: false.
 */
function failDeletesOf(string ...$paths): void
{
    $fake = Storage::disk('local');

    Storage::set('local', new class($fake, $paths) extends FilesystemAdapter
    {
        /**
         * @param  list<string>  $failing
         */
        public function __construct(FilesystemAdapter $fake, private array $failing)
        {
            parent::__construct($fake->getDriver(), $fake->getAdapter(), $fake->getConfig());
        }

        public function delete($paths)
        {
            return array_intersect($this->failing, (array) $paths) !== [] ? false : parent::delete($paths);
        }
    });
}

it('clears staff replies and internal notes on a purged request, and keeps its statuses and dates', function () {
    // مراجعة الدورة ٨ (٥.٦): نص حر من الموظفين قد يحوي بيانات المتقدم
    Setting::put('retention', ['days' => 30]);
    $request = requestWithFile($this->deleted);
    $event = $request->events()->create(['to_status' => ServiceRequestStatus::Rejected, 'message' => 'رد على المتقدم', 'internal_note' => 'ملاحظة داخلية']);
    $other = requestWithFile(User::factory()->create());
    $otherEvent = $other->events()->create(['to_status' => ServiceRequestStatus::Rejected, 'message' => 'رد باقٍ']);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    $event->refresh();
    expect($event->message)->toBeNull()
        ->and($event->internal_note)->toBeNull()
        ->and($event->to_status)->toBe(ServiceRequestStatus::Rejected)
        ->and($event->created_at)->not->toBeNull()
        ->and($otherEvent->fresh()->message)->toBe('رد باقٍ');
});

it('keeps a request as it was when its file cannot be deleted, retries tomorrow, and goes on with the others', function () {
    // مراجعة الدورة ٨ (٥.٤): الملفات قبل تفريغ السجل، وقيمة الحذف مفحوصة
    Setting::put('retention', ['days' => 30]);
    $failing = requestWithFile($this->deleted);
    $other = requestWithFile($this->deleted);
    failDeletesOf($failing->files['cv']['path']);

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($failing->fresh()->answers)->not->toBeNull()
        ->and($failing->fresh()->files)->toEqual($failing->files)
        ->and(Storage::disk('local')->exists($failing->files['cv']['path']))->toBeTrue()
        ->and($other->fresh()->files)->toBeNull()
        ->and(Storage::disk('local')->exists($other->files['cv']['path']))->toBeFalse();
});

it('goes on to the requests when a competition entry before them cannot be purged', function () {
    // مراجعة الدورة ٨ (٥.٤): فشل مشاركة لا يمنع حذف طلب بعدها
    Setting::put('retention', ['days' => 30]);
    $competition = Competition::factory()->withQuestions()->create(['opens_at' => now()->subDays(60), 'closes_at' => now()->subDays(31)]);
    $entry = CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id, 'user_id' => $this->deleted->id, 'answers' => ['1' => 'نص المتقدم']]);
    Storage::disk('local')->put($path = 'competition-videos/'.$entry->uuid.'.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->forceFill(['submission_id' => $entry->id, 'question_id' => $competition->questions[2]->id, 'status' => VideoStatus::Ready, 'size' => 11, 'chunk_count' => 1, 'path' => $path])->save();
    $request = requestWithFile($this->deleted);
    failDeletesOf($path);

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($entry->fresh()->answers)->not->toBeNull()
        ->and($request->fresh()->answers)->toBeNull()
        ->and($request->fresh()->files)->toBeNull();
});

it('exports the data of every module for one account, in one copy', function () {
    // القضية #6 (١.١١): الطلبات والتسجيلات والمشاركات والإجابات معاً بعد التكامل
    $user = User::factory()->create();
    ServiceRequest::factory()->for($user)->create();
    ProgramRegistration::factory()->for($user)->create();
    CompetitionSubmission::factory()->create(['user_id' => $user->id]);
    $survey = Survey::factory()->withQuestions()->create();
    SurveyResponse::query()->forceCreate(['survey_id' => $survey->id, 'user_id' => $user->id, 'answers' => [], 'submitted_at' => now()]);

    $copy = app(ExportUserData::class)->handle($user);

    expect($copy)->toHaveKeys(['account', 'service_requests', 'program_registrations', 'competition_submissions', 'survey_responses'])
        ->and($copy['service_requests'])->toHaveCount(1)
        ->and($copy['program_registrations'])->toHaveCount(1)
        ->and($copy['competition_submissions'])->toHaveCount(1)
        ->and($copy['survey_responses'])->toHaveCount(1);
});

it('includes requests and competition entries removed from the panel in the personal data copy', function () {
    // ما زالت بياناتها محفوظة حتى مدة الاحتفاظ، فحق الاطلاع يشملها
    $user = User::factory()->create();
    ServiceRequest::factory()->for($user)->create()->delete();
    CompetitionSubmission::factory()->create(['user_id' => $user->id])->delete();

    $copy = app(ExportUserData::class)->handle($user);

    expect($copy['service_requests'])->toHaveCount(1)->and($copy['competition_submissions'])->toHaveCount(1);
});
