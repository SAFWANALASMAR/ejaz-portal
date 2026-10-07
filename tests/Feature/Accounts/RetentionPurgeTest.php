<?php

use App\Actions\Accounts\ExportUserData;
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
