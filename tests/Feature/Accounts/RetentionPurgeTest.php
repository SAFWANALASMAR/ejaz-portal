<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Services\PurgeServiceRequestPersonalData;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
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

it('clears staff replies and internal notes on a purged request, and keeps its statuses, actor and dates', function () {
    // مراجعة الدورة ٨ (٥.٦): نص حر من الموظفين قد يحوي بيانات المتقدم
    Setting::put('retention', ['days' => 30]);
    $staff = userWithRole(RoleName::Secretary);
    $request = requestWithFile($this->deleted);
    $event = $request->events()->create(['actor_id' => $staff->id, 'from_status' => ServiceRequestStatus::UnderReview, 'to_status' => ServiceRequestStatus::Rejected, 'message' => 'رد على المتقدم', 'internal_note' => 'ملاحظة داخلية']);
    $other = requestWithFile(User::factory()->create());
    $otherEvent = $other->events()->create(['to_status' => ServiceRequestStatus::Rejected, 'message' => 'رد باقٍ']);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    $event->refresh();
    expect($event->message)->toBeNull()
        ->and($event->internal_note)->toBeNull()
        ->and($event->from_status)->toBe(ServiceRequestStatus::UnderReview)
        ->and($event->to_status)->toBe(ServiceRequestStatus::Rejected)
        ->and($event->actor_id)->toBe($staff->id)
        ->and($event->created_at)->not->toBeNull()
        ->and($otherEvent->fresh()->message)->toBe('رد باقٍ');
});

it('clears a staff reply written after the purge, on the next run, and only once', function () {
    // مراجعة الوكيل على التكامل: الموظف ما زال يغيّر حالة الطلب ويكتب رداً بعد تفريغه، فيلتقطه شرط الأحداث وحده
    Setting::put('retention', ['days' => 30]);
    $request = requestWithFile($this->deleted);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    $late = $request->events()->create(['to_status' => ServiceRequestStatus::Closed, 'message' => 'رد بعد التفريغ']);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($late->fresh()->message)->toBeNull()
        ->and(Activity::where('event', 'personal_data_purged')->where('subject_id', $request->id)->count())->toBe(2);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(Activity::where('event', 'personal_data_purged')->where('subject_id', $request->id)->count())->toBe(2);
});

it('keeps a request as it was when its file cannot be deleted, retries tomorrow, and goes on with the others', function () {
    // مراجعة الدورة ٨ (٥.٤): الملفات قبل تفريغ السجل، وقيمة الحذف مفحوصة
    Setting::put('retention', ['days' => 30]);
    $failing = requestWithFile($this->deleted);
    $other = requestWithFile($this->deleted);
    $original = failDeletesOf($failing->files['cv']['path']);

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($failing->fresh()->answers)->not->toBeNull()
        ->and($failing->fresh()->files)->toEqual($failing->files)
        ->and(Storage::disk('local')->exists($failing->files['cv']['path']))->toBeTrue()
        ->and($other->fresh()->files)->toBeNull()
        ->and(Storage::disk('local')->exists($other->files['cv']['path']))->toBeFalse();

    Storage::set('local', $original);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($failing->fresh()->files)->toBeNull()
        ->and(Storage::disk('local')->exists($failing->files['cv']['path']))->toBeFalse();
});

it('leaves a request for the next run when its files change between the read and the lock', function () {
    // مراجعة الوكيل الأمني على التكامل: المسارات من نسخة قد تكون قديمة، فلا يُفرَّغ مسار ملف لم يُحذف
    Setting::put('retention', ['days' => 30]);
    $request = requestWithFile($this->deleted);
    $stale = ServiceRequest::query()->find($request->id);

    Storage::disk('local')->put($added = 'service-requests/added.pdf', '%PDF-1.4');
    $request->forceFill(['files' => [...$request->files, 'other' => ['path' => $added, 'name' => 'آخر.pdf', 'size' => 8]]])->save();

    expect(fn () => app(PurgeServiceRequestPersonalData::class)->handle($stale))->toThrow(RuntimeException::class);
    expect($request->fresh()->files)->toHaveKey('other')
        ->and(Storage::disk('local')->exists($added))->toBeTrue();

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect($request->fresh()->files)->toBeNull()->and(Storage::disk('local')->exists($added))->toBeFalse();
});

it('goes on to the requests when a competition entry before them cannot be purged', function () {
    // مراجعة الدورة ٨ (٥.٤): فشل مشاركة لا يمنع حذف طلب بعدها
    Setting::put('retention', ['days' => 30]);
    $competition = Competition::factory()->withQuestions()->create(['opens_at' => now()->subDays(60), 'closes_at' => now()->subDays(31)]);
    $entry = entryWithVideo($competition, $this->deleted);
    $request = requestWithFile($this->deleted);
    failDeletesOf((string) $entry->videos()->sole()->path);

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
