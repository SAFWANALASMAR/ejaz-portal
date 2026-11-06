<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Competitions\CompleteVideoUpload;
use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\SaveSubmissionDraft;
use App\Actions\Competitions\StartSubmission;
use App\Actions\Competitions\StartVideoUpload;
use App\Actions\Competitions\StoreVideoChunk;
use App\Enums\RoleName;
use App\Enums\SubmissionStatus;
use App\Enums\VideoStatus;
use App\Jobs\ProcessSubmissionVideo;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->competition = Competition::factory()->withQuestions()->create(['slug' => 'demo']);
    $this->applicant = User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]);
    [$this->text, $this->choice, $this->video] = $this->competition->questions->all();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function entryForm(array $overrides = []): array
{
    $test = test();

    return [
        'category' => 'الفئة الأولى',
        'region' => 'makkah',
        'answers' => [$test->text->id => 'إجابتي النصية', $test->choice->id => 'b'],
        ...$overrides,
    ];
}

it('lists published competitions with their status, and hides drafts', function () {
    Competition::factory()->unpublished()->create(['title' => 'مسابقة غير منشورة']);
    Competition::factory()->upcoming()->create(['title' => 'مسابقة قادمة']);

    $this->get('/competitions')
        ->assertOk()
        ->assertSee($this->competition->title)
        ->assertSee('مسابقة قادمة')
        ->assertSee(__('competitions.statuses.open'))
        ->assertSee(__('competitions.statuses.upcoming'))
        ->assertDontSee('مسابقة غير منشورة');

    $this->get('/competitions/demo')->assertOk()->assertSee(__('competitions.show.login_to_apply'));
    $this->get('/competitions?page=9')->assertNotFound();
});

it('keeps the pages of an applicant out of caches, and the public page cacheable for guests', function () {
    // مراجعة الوكيلين على التكامل: صفحة المسابقة لصاحب الحساب فيها رقم مشاركته، وصفحة المشاركة إجاباته
    $this->get('/competitions/demo')->assertOk()->assertHeader('Cache-Control', 'no-cache, private');

    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->get('/competitions/demo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/competitions/demo/entry')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/account/competitions')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('shows no unpublished competition, and never shows the correct option', function () {
    $draft = Competition::factory()->unpublished()->create();

    $this->get('/competitions/'.$draft->slug)->assertNotFound();
    $this->actingAs($this->applicant)->get('/competitions/'.$draft->slug.'/entry')->assertNotFound();
    $this->post('/competitions/'.$draft->slug.'/entry/start')->assertNotFound();

    $this->post('/competitions/demo/entry/start');
    $page = $this->get('/competitions/demo/entry')->assertOk()->assertSee('سؤال اختيار تجريبي');

    // الخيار الصحيح لا يظهر بعلامة في الصفحة ولا في أي سمة
    expect($page->getContent())->not->toContain('correct_option')->not->toContain('is_correct');
});

// بريد موثق أو جوال موثق (decisions/2026-11-06-email-otp.md): رمز البريد أولاً، ورمز الجوال لمن لا بريد له
it('asks a guest to log in and an account without a verified email or phone to verify before applying', function () {
    $this->get('/competitions/demo/entry')->assertRedirect('/login');
    $this->post('/competitions/demo/entry/start')->assertRedirect('/login');

    $noEmail = User::factory()->unverified()->create(['email' => null, 'phone' => '+966500000003', 'phone_verified_at' => null]);
    $this->actingAs($noEmail)->post('/competitions/demo/entry/start')->assertRedirect(route('account.phone.verify'));

    $unverified = User::factory()->unverified()->create(['phone' => '+966500000002', 'phone_verified_at' => null]);

    $this->actingAs($unverified)->post('/competitions/demo/entry/start')->assertRedirect(route('verification.notice'));
    expect(session('url.intended'))->not->toBe(route('competitions.show', 'demo'));

    expect(CompetitionSubmission::count())->toBe(0)
        ->and(fn () => app(StartSubmission::class)->handle($unverified, $this->competition))->toThrow(AuthorizationException::class);
});

it('starts one draft per applicant, even when called again or concurrently', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start')->assertRedirect('/competitions/demo/entry');
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start')->assertRedirect('/competitions/demo/entry');

    $first = CompetitionSubmission::sole();

    expect(app(StartSubmission::class)->handle($this->applicant, $this->competition)->id)->toBe($first->id);
});

it('creates no draft by opening the form, and limits starting by rate', function () {
    // GET لا ينشئ شيئاً: بلا مسودة يعود إلى صفحة المسابقة وزر «شارك الآن» فيها نموذج POST
    $this->actingAs($this->applicant)->get('/competitions/demo/entry')->assertRedirect('/competitions/demo');
    $this->get('/competitions/demo')->assertOk()->assertSee('action="'.route('competitions.entry.start', 'demo').'"', false);

    expect(CompetitionSubmission::count())->toBe(0);

    foreach (range(1, 10) as $attempt) {
        $this->post('/competitions/demo/entry/start')->assertRedirect('/competitions/demo/entry');
    }

    $this->post('/competitions/demo/entry/start')->assertTooManyRequests();
    expect(CompetitionSubmission::count())->toBe(1);
});

it('counts each entry route on its own, so autosaves do not use up the submit limit', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    // الحد بلا بادئة مفتاحه المستخدم وحده، فتشترك فيه كل المسارات: 15 حفظاً تلقائياً تستهلك حد الإرسال (10)
    foreach (range(1, 15) as $attempt) {
        $this->postJson('/competitions/demo/entry/autosave', entryForm())->assertOk();
    }

    $this->post('/competitions/demo/entry/submit', entryForm())->assertRedirect('/competitions/demo/entry')->assertSessionHasNoErrors();
    expect(CompetitionSubmission::sole()->status)->toBe(SubmissionStatus::Submitted);
});

it('refuses a duplicate submission at the database level', function () {
    CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => $this->applicant->id]);

    // مثل طلبين متزامنين مرّا من فحص الوجود معاً: القيد الفريد يرفض الثاني
    expect(fn () => CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => $this->applicant->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('returns the existing draft when a concurrent request created it first', function () {
    // طلب متزامن أنشأ المسودة بعد فحص الوجود الأول: الفحص الأول لا يجدها، والإنشاء يصطدم بالقيد
    CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'user_id' => $this->applicant->id]);

    $racing = new class extends StartSubmission
    {
        private bool $checked = false;

        protected function existing(User $user, Competition $competition): ?CompetitionSubmission
        {
            if (! $this->checked) {
                $this->checked = true;

                return null;
            }

            return parent::existing($user, $competition);
        }
    };

    $submission = $racing->handle($this->applicant, $this->competition);

    expect(CompetitionSubmission::count())->toBe(1)->and($submission->id)->toBe(CompetitionSubmission::sole()->id);
});

it('saves the draft by hand and in the background, and keeps the answers', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->post('/competitions/demo/entry', entryForm())->assertRedirect('/competitions/demo/entry')->assertSessionHas('status');

    $this->postJson('/competitions/demo/entry/autosave', entryForm(['answers' => [$this->text->id => 'إجابة محدّثة']]))->assertOk();

    $submission = CompetitionSubmission::sole();

    expect($submission->answer($this->text))->toBe('إجابة محدّثة')
        ->and($submission->answer($this->choice))->toBe('b')
        ->and($submission->region?->value)->toBe('makkah')
        ->and($submission->status)->toBe(SubmissionStatus::Draft);
});

it('refuses answers that do not belong to the question', function (array $overrides, string $field) {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->postJson('/competitions/demo/entry/autosave', entryForm($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'option not in the question' => fn () => [['answers' => [test()->choice->id => 'z']], 'answers.'.test()->choice->id],
    'category not in the competition' => [['category' => 'فئة أخرى'], 'category'],
    'unknown region' => [['region' => 'atlantis'], 'region'],
    'text too long' => fn () => [['answers' => [test()->text->id => str_repeat('ا', 5001)]], 'answers.'.test()->text->id],
]);

it('submits with a unique reference and a queued confirmation, then locks the entry', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->post('/competitions/demo/entry/submit', entryForm())->assertRedirect('/competitions/demo/entry');

    $submission = CompetitionSubmission::sole();

    expect($submission->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->reference)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/')
        ->and($submission->submitted_at)->not->toBeNull();

    Notification::assertSentTo($this->applicant, SubmissionReceived::class);

    $this->get('/competitions/demo/entry')->assertOk()->assertSee($submission->reference);
    $this->get('/account/competitions')->assertOk()->assertSee($submission->reference);

    $this->post('/competitions/demo/entry', entryForm(['answers' => [$this->text->id => 'تعديل بعد الإرسال']]))->assertForbidden();
    $this->postJson('/competitions/demo/entry/autosave', entryForm(['answers' => [$this->text->id => 'تعديل بعد الإرسال']]))->assertForbidden();
    $this->post('/competitions/demo/entry/submit', entryForm())->assertForbidden();

    expect($submission->fresh()->answer($this->text))->toBe('إجابتي النصية');
});

it('refuses to submit without the required answers, category and region', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->post('/competitions/demo/entry/submit', ['answers' => []])
        ->assertSessionHasErrors(['category', 'region', 'answers.'.$this->text->id, 'answers.'.$this->choice->id]);

    expect(CompetitionSubmission::sole()->status)->toBe(SubmissionStatus::Draft);
});

it('requires a ready video for a required video question', function () {
    $this->video->update(['is_required' => true]);
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->post('/competitions/demo/entry/submit', entryForm())->assertSessionHasErrors('answers.'.$this->video->id);
});

it('refuses an autosave that reaches a submission submitted meanwhile', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');
    $stale = CompetitionSubmission::sole();

    // الإرسال يسبق والنسخة في الذاكرة ما زالت مسودة
    CompetitionSubmission::query()->whereKey($stale->id)->update(['status' => SubmissionStatus::Submitted, 'reference' => 'ABCDEFGHJK']);

    expect(fn () => app(SaveSubmissionDraft::class)->handle($this->applicant, $stale, entryForm()))->toThrow(AuthorizationException::class)
        ->and($stale->fresh()->answers)->toBeNull();
});

it('includes competition entries in the personal data export', function () {
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $this->competition->id, 'user_id' => $this->applicant->id]);

    expect(app(ExportUserData::class)->handle($this->applicant)['competition_submissions'][0]['competition'])->toBe($this->competition->title);
});

it('keeps every applicant inside their own entry', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');
    $mine = CompetitionSubmission::sole();
    $mine->update(['answers' => [(string) $this->text->id => 'جواب-خاص-بالمتقدم']]);

    $other = User::factory()->create(['phone' => '+966500000003', 'phone_verified_at' => now()]);

    // الآخر يبدأ مسودته هو، ولا يرى إجابات غيره
    $this->actingAs($other)->post('/competitions/demo/entry/start');
    $this->get('/competitions/demo/entry')->assertOk()->assertDontSee('جواب-خاص-بالمتقدم');
    $this->actingAs($other)->post('/competitions/demo/entry', entryForm(['answers' => [$this->text->id => 'محاولة']]));

    expect($mine->fresh()->answer($this->text))->toBe('جواب-خاص-بالمتقدم')
        ->and($other->can('view', $mine))->toBeFalse()
        ->and($other->can('update', $mine))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('update', $mine))->toBeFalse()
        ->and(userWithRole(RoleName::BoardMember)->can('view', $mine))->toBeFalse();
});

it('closes entries automatically after the closing time', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    $this->travelTo($this->competition->closes_at->addMinute());

    $this->post('/competitions/demo/entry', entryForm())->assertForbidden();
    $this->get('/competitions/demo/entry')->assertOk()->assertSee(__('competitions.entry.closed'));

    $late = User::factory()->create(['phone' => '+966500000004', 'phone_verified_at' => now()]);
    $this->actingAs($late)->get('/competitions/demo/entry')->assertRedirect('/competitions/demo');
    $this->post('/competitions/demo/entry/start')->assertRedirect('/competitions/demo')->assertSessionHas('error', __('competitions.entry.closed'));

    expect(CompetitionSubmission::count())->toBe(1);
});

it('does not lock the question bank for an empty draft opened by visiting the form', function () {
    $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');

    expect($this->competition->questionsLocked())->toBeFalse();

    $this->post('/competitions/demo/entry', entryForm());

    expect($this->competition->questionsLocked())->toBeTrue();
});

it('locks the question bank after the first submission, from the panel or any caller', function () {
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $this->competition->id]);
    $manager = userWithRole(RoleName::ProgramManager);

    expect(fn () => app(SaveCompetition::class)->handle($manager, $this->competition, ['title' => 'x', 'slug' => 'demo', 'opens_at' => now(), 'closes_at' => now()->addDay(), 'questions' => []]))
        ->toThrow(ValidationException::class, __('competitions.validation.questions_locked'));

    // بلا الأسئلة يُحفظ غيرها، ويبقى البنك كما هو
    app(SaveCompetition::class)->handle($manager, $this->competition, ['title' => 'عنوان جديد', 'slug' => 'demo', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'is_published' => true]);

    expect($this->competition->fresh()->title)->toBe('عنوان جديد')->and($this->competition->questions()->count())->toBe(3);
});

it('checks the bank lock again after locking the competition, so an entry sent meanwhile is respected', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $injected = false;

    // سباق: أول مشاركة مرسلة تصل بعد الفحص الأول وقبل قفل صف المسابقة. والفحص بعد القفل يراها
    DB::beforeExecuting(function (string $query) use (&$injected): void {
        if (! $injected && str_contains($query, 'from "competitions"') && str_contains($query, 'for update')) {
            $injected = true;
            CompetitionSubmission::factory()->submitted()->create(['competition_id' => $this->competition->id]);
        }
    });

    expect(fn () => app(SaveCompetition::class)->handle($manager, $this->competition, ['title' => 'x', 'slug' => 'demo', 'opens_at' => now(), 'closes_at' => now()->addDay(), 'questions' => []]))
        ->toThrow(ValidationException::class, __('competitions.validation.questions_locked'));

    expect($injected)->toBeTrue()->and($this->competition->questions()->count())->toBe(3);
});

it('refuses saving a competition removed from the panel, before and after the lock', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $this->competition->delete();

    expect(fn () => app(SaveCompetition::class)->handle($manager, $this->competition, ['title' => 'عنوان', 'slug' => 'demo', 'opens_at' => now(), 'closes_at' => now()->addDay()]))
        ->toThrow(AuthorizationException::class)
        ->and(Competition::withTrashed()->find($this->competition->id)->title)->not->toBe('عنوان');
});

describe('video upload', function () {
    beforeEach(function () {
        config(['competitions.video.chunk_mb' => 1]);
        $this->actingAs($this->applicant)->post('/competitions/demo/entry/start');
    });

    function startUpload(string $contents, string $type = 'video/mp4', ?float $duration = 30): TestResponse
    {
        return test()->postJson('/competitions/demo/entry/videos/'.test()->video->id, ['size' => strlen($contents), 'type' => $type, 'duration' => $duration]);
    }

    function putChunk(string $url, string $body): TestResponse
    {
        return test()->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => (string) strlen($body)], $body);
    }

    it('uploads in chunks, resumes the missing ones, and accepts a valid MP4 in the background', function () {
        $contents = mp4Contents(seconds: 30, padding: 1024 * 1024 + 500);
        $start = startUpload($contents)->assertOk()->json();
        $video = SubmissionVideo::sole();

        expect($start['urls'])->toHaveCount(2)->and($video->status)->toBe(VideoStatus::Uploading);

        putChunk($start['urls'][0], substr($contents, 0, 1024 * 1024))->assertNoContent();

        // بعد انقطاع: الحالة تعيد رابط الجزء الناقص وحده
        $status = $this->getJson('/competition-videos/'.$video->uuid)->assertOk()->json();
        expect(array_keys($status['urls']))->toBe([1]);

        $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertUnprocessable();

        putChunk($status['urls'][1], substr($contents, 1024 * 1024))->assertNoContent();
        $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertStatus(202);

        $video->refresh();
        expect($video->status)->toBe(VideoStatus::Ready)
            ->and($video->duration_seconds)->toBe(30)
            ->and($video->path)->toStartWith('competition-videos/')
            ->and(Storage::disk('local')->get($video->path))->toBe($contents)
            ->and(Storage::disk('local')->files($video->chunkDirectory()))->toBe([]);

        $this->video->update(['is_required' => true]);
        $this->post('/competitions/demo/entry/submit', entryForm())->assertSessionHasNoErrors();
    });

    it('refuses before uploading what breaks the question limits', function (string $type, int $size, ?float $duration) {
        $this->postJson('/competitions/demo/entry/videos/'.$this->video->id, ['size' => $size, 'type' => $type, 'duration' => $duration])
            ->assertUnprocessable()->assertJsonValidationErrors('video');

        expect(SubmissionVideo::count())->toBe(0);
    })->with([
        'not mp4' => ['video/quicktime', 1000, 10],
        'too large' => ['video/mp4', 50 * 1024 * 1024 + 1, 10],
        'too long' => ['video/mp4', 1000, 121],
    ]);

    it('rejects in the background a file that lies about its type or length', function (string $contents, string $reason) {
        $start = startUpload($contents)->json();
        putChunk($start['urls'][0], $contents)->assertNoContent();
        $this->postJson('/competition-videos/'.$start['video'].'/complete')->assertStatus(202);

        $video = SubmissionVideo::sole();
        expect($video->status)->toBe(VideoStatus::Rejected)
            ->and($video->rejection_reason)->toBe($reason)
            ->and($video->path)->toBeNull()
            ->and(Storage::disk('local')->allFiles('competition-chunks'))->toBe([]);

        $this->video->update(['is_required' => true]);
        $this->post('/competitions/demo/entry/submit', entryForm())->assertSessionHasErrors('answers.'.$this->video->id);
    })->with([
        'not a video' => ['<?php echo 1; ?>'.str_repeat('x', 100), 'not_mp4'],
        'longer than declared' => [mp4Contents(seconds: 600), 'too_long'],
        'zero duration' => [mp4Contents(seconds: 0), 'unreadable'],
        'fragmented' => [mp4Contents(seconds: 30).pack('N', 8).'moof', 'unreadable'],
    ]);

    it('writes nothing for a chunk whose body is larger than declared or has no length', function () {
        $contents = mp4Contents(padding: 1024 * 1024 + 10);
        $start = startUpload($contents)->json();

        // الرأس يعلن الحجم الصحيح والجسم أكبر: يُقرأ حتى الحد ثم يُرفض
        $this->call('PUT', $start['urls'][0], [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => (string) (1024 * 1024)], str_repeat('x', 3 * 1024 * 1024))
            ->assertUnprocessable();
        $this->call('PUT', $start['urls'][0], [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => (string) (5 * 1024 * 1024)], str_repeat('x', 5 * 1024 * 1024))
            ->assertUnprocessable();

        expect(Storage::disk('local')->allFiles('competition-chunks'))->toBe([]);
    });

    it('stops uploads after closing, and prunes abandoned chunks', function () {
        $contents = mp4Contents(padding: 1024 * 1024 + 10);
        $start = startUpload($contents)->json();
        putChunk($start['urls'][0], substr($contents, 0, 1024 * 1024))->assertNoContent();

        $this->travelTo($this->competition->closes_at->addMinute());

        putChunk($start['urls'][1], substr($contents, 1024 * 1024))->assertForbidden();
        $this->postJson('/competition-videos/'.$start['video'].'/complete')->assertForbidden();
        expect($this->getJson('/competition-videos/'.$start['video'])->json('urls'))->toBe([]);

        Storage::disk('local')->put('competition-chunks/orphan/0', 'x');
        $this->artisan('ejaz:prune-video-uploads')->assertSuccessful();

        $video = SubmissionVideo::sole();
        expect($video->status)->toBe(VideoStatus::Rejected)
            ->and($video->rejection_reason)->toBe('abandoned')
            ->and(Storage::disk('local')->allFiles('competition-chunks'))->toBe([]);
    });

    it('rejects the video when processing fails for good, so it can be uploaded again', function () {
        $contents = mp4Contents();
        $start = startUpload($contents)->json();
        putChunk($start['urls'][0], $contents)->assertNoContent();

        $video = SubmissionVideo::sole();
        SubmissionVideo::query()->whereKey($video->id)->update(['status' => VideoStatus::Processing]);
        (new ProcessSubmissionVideo($video->id))->failed(new RuntimeException('disk'));

        expect($video->fresh()->status)->toBe(VideoStatus::Rejected)
            ->and($video->fresh()->rejection_reason)->toBe('processing_failed');
    });

    it('refuses a chunk of the wrong size, an unsigned or expired link, and another applicant', function () {
        $contents = mp4Contents(padding: 1024 * 1024 + 10);
        $start = startUpload($contents)->json();

        putChunk($start['urls'][0], 'short')->assertUnprocessable();
        putChunk(route('competitions.videos.chunk', ['video' => $start['video'], 'index' => 0]), substr($contents, 0, 1024 * 1024))->assertForbidden();

        $other = User::factory()->create(['phone' => '+966500000005', 'phone_verified_at' => now()]);
        $this->actingAs($other);
        putChunk($start['urls'][0], substr($contents, 0, 1024 * 1024))->assertNotFound();
        $this->getJson('/competition-videos/'.$start['video'])->assertNotFound();
        $this->postJson('/competition-videos/'.$start['video'].'/complete')->assertNotFound();

        $this->actingAs($this->applicant);
        $this->travel(3)->hours();
        putChunk($start['urls'][0], substr($contents, 0, 1024 * 1024))->assertForbidden();

        expect(Storage::disk('local')->allFiles('competition-chunks'))->toBe([]);
    });

    it('replaces the previous video and invalidates its links', function () {
        $first = startUpload(mp4Contents())->json();
        $second = startUpload(mp4Contents())->json();

        expect(SubmissionVideo::count())->toBe(1)->and($second['video'])->not->toBe($first['video']);

        $this->getJson('/competition-videos/'.$first['video'])->assertNotFound();
    });

    it('refuses a question of another competition or a non-video question', function () {
        $other = Competition::factory()->withQuestions()->create();

        $this->postJson('/competitions/demo/entry/videos/'.$other->questions[2]->id, ['size' => 10, 'type' => 'video/mp4'])->assertNotFound();
        $this->postJson('/competitions/demo/entry/videos/'.$this->text->id, ['size' => 10, 'type' => 'video/mp4'])->assertForbidden();
    });

    it('refuses to submit while a video is still uploading, even an optional one', function () {
        // يكمّل قفل المشاركة في الرفع: مقطع قيد الرفع يمنع الإرسال، فلا يُكتب جزء لمشاركة مرسلة
        $start = startUpload(mp4Contents(padding: 1024 * 1024 + 10))->json();
        putChunk($start['urls'][0], str_repeat('x', 1024 * 1024))->assertNoContent();

        $this->post('/competitions/demo/entry/submit', entryForm())->assertSessionHasErrors(['answers.'.$this->video->id => __('competitions.entry.video_pending')]);

        expect(CompetitionSubmission::sole()->isDraft())->toBeTrue()
            ->and(SubmissionVideo::sole()->status)->toBe(VideoStatus::Uploading);
    });

    it('does not replace, receive or complete a video once the entry was submitted meanwhile', function () {
        // سباق: الطلب قرأ المشاركة مسودة، ثم أُرسلت قبل أن يكتب. فيُعاد الفحص على الصف المقفول
        $contents = mp4Contents(padding: 1024 * 1024 + 10);
        $start = startUpload($contents)->json();
        putChunk($start['urls'][0], substr($contents, 0, 1024 * 1024))->assertNoContent();
        putChunk($start['urls'][1], substr($contents, 1024 * 1024))->assertNoContent();
        $submission = CompetitionSubmission::sole();
        $stale = CompetitionSubmission::findOrFail($submission->id);
        $staleVideo = SubmissionVideo::with('submission')->sole();
        $chunks = Storage::disk('local')->allFiles('competition-chunks');

        CompetitionSubmission::query()->whereKey($submission->id)->update(['status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

        expect(fn () => app(StartVideoUpload::class)->handle($this->applicant, $stale, $this->video, strlen($contents), 'video/mp4', 30))
            ->toThrow(AuthorizationException::class);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($contents, 0, 1024 * 1024));
        rewind($stream);

        expect(fn () => app(StoreVideoChunk::class)->handle($this->applicant, $staleVideo, 0, $stream, 1024 * 1024))->toThrow(AuthorizationException::class)
            ->and(fn () => app(CompleteVideoUpload::class)->handle($this->applicant, $staleVideo))->toThrow(AuthorizationException::class)
            ->and(SubmissionVideo::sole()->uuid)->toBe($start['video'])
            ->and(SubmissionVideo::sole()->status)->toBe(VideoStatus::Uploading)
            ->and(Storage::disk('local')->allFiles('competition-chunks'))->toBe($chunks);
    });
});
