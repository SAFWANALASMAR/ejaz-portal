<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Settings\UpdateSiteSettings;
use App\Actions\Users\AnonymizeUser;
use App\Contracts\VideoUploads;
use App\Enums\RoleName;
use App\Enums\VideoStatus;
use App\Enums\WorkType;
use App\Filament\Pages\SiteSettings;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Setting;
use App\Models\SubmissionVideo;
use App\Models\SubmissionWork;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
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
 * رفع المقاطع الحقيقي، إلا أن حذف بقايا الرفع (discard) يستدعي ما يُعطى قبل أن يمضي، فيحاكي ما يحدث
 * بين قراءة المقاطع وقفلها: تعطل التخزين، أو مهمة معالجة تنهي مقطعاً.
 */
function uploadsOnDiscard(Closure $before): VideoUploads
{
    return new class(app(VideoUploads::class), $before) implements VideoUploads
    {
        public function __construct(private VideoUploads $real, private Closure $before) {}

        public function begin(SubmissionVideo $video): void
        {
            $this->real->begin($video);
        }

        public function urls(SubmissionVideo $video, array $indexes): array
        {
            return $this->real->urls($video, $indexes);
        }

        public function missing(SubmissionVideo $video): array
        {
            return $this->real->missing($video);
        }

        public function open(SubmissionVideo $video)
        {
            return $this->real->open($video);
        }

        public function keep(SubmissionVideo $video, $file): string
        {
            return $this->real->keep($video, $file);
        }

        public function discard(SubmissionVideo $video): void
        {
            ($this->before)($video);
            $this->real->discard($video);
        }

        public function pruneOrphans(): int
        {
            return $this->real->pruneOrphans();
        }
    };
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
        ->toBe(['competition_id' => $this->competition->id, 'videos' => 1, 'works' => 0]);

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
    app()->instance(VideoUploads::class, uploadsOnDiscard(function (SubmissionVideo $video) use ($failing): void {
        if ($video->submission_id === $failing->id) {
            throw new RuntimeException('storage down');
        }
    }));

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($failing->videos()->count())->toBe(1)
        ->and($failing->fresh()->answers)->not->toBeNull()
        ->and(Storage::disk('local')->exists('competition-videos/'.$failing->uuid.'.mp4'))->toBeTrue()
        ->and($other->videos()->count())->toBe(0)
        ->and($other->fresh()->answers)->toBeNull();

    // وفي الدورة التالية بعد عودة التخزين يُحذف
    app()->instance(VideoUploads::class, $real);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($failing->videos()->count())->toBe(0)
        ->and($failing->fresh()->answers)->toBeNull()
        ->and(Storage::disk('local')->exists('competition-videos/'.$failing->uuid.'.mp4'))->toBeFalse();
});

it('keeps an entry as it was when the disk reports a failed delete without throwing, and retries tomorrow', function () {
    Setting::put('retention', ['days' => 30]);
    $failing = entryWithVideo($this->competition, $this->deleted);
    $path = 'competition-videos/'.$failing->uuid.'.mp4';

    // الأقراص مضبوطة بلا رمي استثناء (throw: false)، فتعذر الحذف يظهر قيمةً لا استثناءً
    $original = failDeletesOf($path);

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($failing->videos()->count())->toBe(1)
        ->and($failing->fresh()->answers)->not->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    Storage::set('local', $original);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($failing->videos()->count())->toBe(0)->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('leaves an entry for the next run when a video finishes processing during the purge', function () {
    // مراجعة الوكيل الأمني على التكامل: مقطع يُحفظ بين قراءة المقاطع وقفلها لا يُحذف صفه وملفه باقٍ
    Setting::put('retention', ['days' => 30]);
    $entry = entryWithVideo($this->competition, $this->deleted);
    $video = $entry->videos()->sole();
    Storage::disk('local')->delete((string) $video->path);
    $video->forceFill(['status' => VideoStatus::Processing, 'path' => null])->save();

    // مهمة المعالجة تنهي المقطع في تلك اللحظة: ملف جديد ومساره في الصف
    $late = 'competition-videos/late.mp4';
    $real = app(VideoUploads::class);
    app()->instance(VideoUploads::class, uploadsOnDiscard(function () use ($video, $late): void {
        Storage::disk('local')->put($late, 'video-bytes');
        SubmissionVideo::query()->whereKey($video->id)->update(['status' => VideoStatus::Ready, 'path' => $late]);
    }));

    $this->artisan('ejaz:purge-anonymized-data')->assertFailed();

    expect($entry->videos()->sole()->path)->toBe($late)
        ->and(Storage::disk('local')->exists($late))->toBeTrue()
        ->and($entry->fresh()->answers)->not->toBeNull();

    // وفي الدورة التالية يُعرف المسار، فيُحذف الملف ثم الصف
    app()->instance(VideoUploads::class, $real);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($entry->videos()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($late))->toBeFalse()
        ->and($entry->fresh()->answers)->toBeNull();
});

it('does not let two purge runs overlap', function () {
    $this->artisan('schedule:list')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'ejaz:purge-anonymized-data'));

    expect($event?->withoutOverlapping)->toBeTrue();
});

it('purges an exam registration without answers and a works draft without answers, through the command', function () {
    Setting::put('retention', ['days' => 30]);
    $registered = CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => $this->deleted->id, 'answers' => null, 'region' => null]);
    $registered->forceFill(['full_name' => 'اسم تجريبي', 'contact_email' => 'test@example.test'])->save();

    $works = CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => User::factory()->create(['anonymized_at' => now()])->id, 'answers' => null, 'region' => null]);
    (new SubmissionWork)->forceFill(['submission_id' => $works->id, 'type' => WorkType::Link, 'url' => 'https://example.test/work'])->save();

    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    expect($registered->fresh()->full_name)->toBeNull()
        ->and($registered->fresh()->contact_email)->toBeNull()
        ->and(SubmissionWork::count())->toBe(0);
});

it('clears the exam registration details at once when the account is anonymized, and keeps the reference', function () {
    $user = User::factory()->create(['deletion_requested_at' => now()]);
    $entry = CompetitionSubmission::factory()->submitted()->create(['competition_id' => $this->competition->id, 'user_id' => $user->id]);
    $entry->forceFill(['full_name' => 'اسم تجريبي', 'contact_email' => 'test@example.test'])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user);

    $entry->refresh();
    expect($entry->full_name)->toBeNull()
        ->and($entry->contact_email)->toBeNull()
        ->and($entry->national_id_hash)->toBeNull()
        ->and($entry->reference)->not->toBeNull();
});
