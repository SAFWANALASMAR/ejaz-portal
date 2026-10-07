<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Settings\UpdateSiteSettings;
use App\Contracts\VideoUploads;
use App\Enums\RoleName;
use App\Enums\SaudiRegion;
use App\Enums\VideoStatus;
use App\Filament\Pages\SiteSettings;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Setting;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| قرار صفوان (مراجعة #1): مقاطع من حُذف حسابه تبقى إلى انتهاء المسابقة وفترة الاعتراض، ثم تُحذف آلياً
| بعد مدة الاحتفاظ. ويبقى الرقم المرجعي والدرجات لسلامة النتائج والسحب.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->competition = Competition::factory()->withQuestions()->create(['opens_at' => now()->subDays(60), 'closes_at' => now()->subDays(31)]);
    $this->videoQuestion = $this->competition->questions[2];
    $this->deleted = User::factory()->create(['deletion_requested_at' => now()->subDays(40), 'anonymized_at' => now()->subDays(39)]);
});

/**
 * مشاركة مرسلة بإجابة ومنطقة ومقطع محفوظ على القرص.
 */
function entryWithVideo(Competition $competition, User $user): CompetitionSubmission
{
    $submission = CompetitionSubmission::factory()->submitted()->create([
        'competition_id' => $competition->id,
        'user_id' => $user->id,
        'category' => 'الفئة الأولى',
        'region' => SaudiRegion::Makkah,
        'answers' => [(string) $competition->questions[0]->id => 'نص المتقدم'],
        'auto_score' => 7,
        'final_score' => 8.5,
    ]);

    $path = 'competition-videos/'.$submission->uuid.'.mp4';
    Storage::disk('local')->put($path, 'video-bytes');

    $video = new SubmissionVideo;
    $video->submission_id = $submission->id;
    $video->question_id = $competition->questions[2]->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->duration_seconds = 30;
    $video->path = $path;
    $video->save();

    return $submission;
}

it('deletes nothing while the retention period is not set by the association', function () {
    $entry = entryWithVideo($this->competition, $this->deleted);

    expect(Retention::days())->toBeNull();

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($entry->videos()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('competition-videos'))->toHaveCount(1)
        ->and($entry->fresh()->answers)->not->toBeNull();
});

it('purges the videos and answers of deleted accounts after the period, and keeps the reference and scores', function () {
    Setting::put('retention', ['days' => 30]);
    $entry = entryWithVideo($this->competition, $this->deleted);
    $kept = entryWithVideo($this->competition, User::factory()->create());

    $recent = Competition::factory()->withQuestions()->create(['opens_at' => now()->subDays(20), 'closes_at' => now()->subDays(10)]);
    $waiting = entryWithVideo($recent, $this->deleted);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    $entry->refresh();
    expect($entry->videos()->count())->toBe(0)
        ->and($entry->answers)->toBeNull()
        ->and($entry->region)->toBeNull()
        ->and($entry->reference)->not->toBeNull()
        ->and((float) $entry->auto_score)->toBe(7.0)
        ->and((float) $entry->final_score)->toBe(8.5)
        ->and($entry->category)->toBe('الفئة الأولى')
        ->and(Storage::disk('local')->exists('competition-videos/'.$entry->uuid.'.mp4'))->toBeFalse();

    // من لم يحذف حسابه، ومسابقة لم تمض مدتها بعد إغلاقها: كما هما
    expect($kept->videos()->count())->toBe(1)->and($kept->fresh()->answers)->not->toBeNull()
        ->and(Storage::disk('local')->exists('competition-videos/'.$kept->uuid.'.mp4'))->toBeTrue()
        ->and($waiting->videos()->count())->toBe(1)->and($waiting->fresh()->answers)->not->toBeNull();

    expect(Activity::where('event', 'personal_data_purged')->where('subject_id', $entry->id)->sole()->properties->all())
        ->toBe(['competition_id' => $this->competition->id, 'videos' => 1]);

    // مرة ثانية لا تمس شيئاً ولا تسجل من جديد
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(Activity::where('event', 'personal_data_purged')->count())->toBe(1);
});

it('purges a removed entry and a draft of a deleted account too', function () {
    Setting::put('retention', ['days' => 30]);
    $removed = entryWithVideo($this->competition, $this->deleted);
    $removed->delete();

    $draft = CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => User::factory()->create(['anonymized_at' => now()])->id, 'answers' => ['1' => 'مسودة']]);

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect(SubmissionVideo::count())->toBe(0)
        ->and(CompetitionSubmission::withTrashed()->find($removed->id)->answers)->toBeNull()
        ->and($draft->fresh()->answers)->toBeNull();
});

it('purges entries of a deleted account in a competition removed from the panel too', function () {
    Setting::put('retention', ['days' => 30]);
    $entry = entryWithVideo($this->competition, $this->deleted);
    $this->competition->delete();

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($entry->videos()->count())->toBe(0)->and($entry->fresh()->answers)->toBeNull();
});

it('lets only the super admin set the retention period, within limits', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(UpdateSiteSettings::class)->handle($editor, ['retention' => ['days' => 1]]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateSiteSettings::class)->handle($admin, ['retention' => ['days' => 0]]))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateSiteSettings::class)->handle($admin, ['retention' => ['days' => 5000]]))->toThrow(ValidationException::class)
        ->and(Retention::days())->toBeNull();

    $this->actingAs($admin);
    Livewire::test(SiteSettings::class)->fillForm(['retention.days' => 90])->call('save')->assertHasNoFormErrors();

    expect(Retention::days())->toBe(90)
        ->and(Activity::where('subject_type', Setting::class)->where('event', 'created')->exists())->toBeTrue();

    // المحرر يحفظ بقية الإعدادات، ولا يرى المدة ولا يغيّرها
    $this->actingAs($editor);
    Livewire::test(SiteSettings::class)->assertFormFieldDoesNotExist('retention.days')->fillForm(['contact.email' => 'office@example.test'])->call('save')->assertHasNoFormErrors();

    expect(Retention::days())->toBe(90);
});

it('mentions the videos in the personal data export, without the file', function () {
    $entry = entryWithVideo($this->competition, $user = User::factory()->create());

    $videos = app(ExportUserData::class)->handle($user)['competition_submissions'][0]['videos'];

    expect($videos)->toBe([[
        'question' => $this->videoQuestion->prompt,
        'status' => 'ready',
        'duration_seconds' => 30,
        'size_bytes' => 11,
        'uploaded_at' => $entry->videos()->sole()->created_at->toIso8601String(),
    ]]);
});

it('keeps an entry as it was when its files cannot be deleted, retries tomorrow, and goes on with the others', function () {
    Setting::put('retention', ['days' => 30]);
    $failing = entryWithVideo($this->competition, $this->deleted);
    $other = entryWithVideo($this->competition, User::factory()->create(['anonymized_at' => now()->subDays(40)]));

    // تعطّل التخزين عند حذف بقايا رفع المشاركة الأولى وحدها
    $real = app(VideoUploads::class);
    app()->instance(VideoUploads::class, new class($real, $failing->id) implements VideoUploads
    {
        public function __construct(private VideoUploads $real, private int $failingId) {}

        public function begin(SubmissionVideo $video): void {}

        public function urls(SubmissionVideo $video, array $indexes): array
        {
            return [];
        }

        public function missing(SubmissionVideo $video): array
        {
            return [];
        }

        public function open(SubmissionVideo $video)
        {
            return $this->real->open($video);
        }

        public function keep(SubmissionVideo $video, $file): string
        {
            return '';
        }

        public function discard(SubmissionVideo $video): void
        {
            if ($video->submission_id === $this->failingId) {
                throw new RuntimeException('storage down');
            }
        }

        public function pruneOrphans(): int
        {
            return 0;
        }
    });

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($failing->videos()->count())->toBe(1)
        ->and($failing->fresh()->answers)->not->toBeNull()
        ->and(Storage::disk('local')->exists('competition-videos/'.$failing->uuid.'.mp4'))->toBeTrue()
        ->and($other->videos()->count())->toBe(0)
        ->and($other->fresh()->answers)->toBeNull();
});
