<?php

use App\Actions\Competitions\ApproveResults;
use App\Actions\Competitions\AssignReviewers;
use App\Actions\Competitions\DistributeSubmissions;
use App\Actions\Competitions\GradeMultipleChoice;
use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\ScoreSubmission;
use App\Enums\RoleName;
use App\Enums\VideoStatus;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\SubmissionsRelationManager;
use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Http\Middleware\AdminIdleTimeout;
use App\Jobs\GradeSubmission;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionReview;
use App\Models\SubmissionVideo;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->competition = Competition::factory()->withQuestions()->create();
    [$this->text, $this->choice, $this->video] = $this->competition->questions->all();
    [$this->first, $this->second] = $this->competition->criteria->all();
    $this->manager = userWithRole(RoleName::ProgramManager);
    $this->reviewerA = userWithRole(RoleName::Reviewer, ['name' => 'محكّم أ']);
    $this->reviewerB = userWithRole(RoleName::Reviewer, ['name' => 'محكّم ب']);
    $this->competition->reviewers()->attach([$this->reviewerA->id, $this->reviewerB->id]);
});

/**
 * مشاركة مرسلة بإجابة اختيار.
 */
function submittedEntry(string $choice = 'b', array $attributes = []): CompetitionSubmission
{
    $test = test();

    return CompetitionSubmission::factory()->submitted()->create([
        'competition_id' => $test->competition->id,
        'answers' => [(string) $test->text->id => 'نص المتقدم', (string) $test->choice->id => $choice],
        ...$attributes,
    ]);
}

it('grades multiple choice answers in the background after submission', function () {
    Queue::fake();
    $applicant = User::factory()->create(['phone' => '+966500000010', 'phone_verified_at' => now()]);

    $this->actingAs($applicant)->post('/competitions/'.$this->competition->slug.'/entry/start');
    $this->post('/competitions/'.$this->competition->slug.'/entry/submit', [
        'category' => 'الفئة الأولى', 'region' => 'riyadh',
        'answers' => [$this->text->id => 'نص', $this->choice->id => 'b'],
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(GradeSubmission::class);
});

it('scores the correct choices only, and leaves drafts ungraded', function () {
    $right = submittedEntry('b');
    $wrong = submittedEntry('a');
    $draft = CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id, 'answers' => [(string) $this->choice->id => 'b']]);

    (new GradeSubmission($right->id))->handle(app(GradeMultipleChoice::class));
    (new GradeSubmission($wrong->id))->handle(app(GradeMultipleChoice::class));
    (new GradeSubmission($draft->id))->handle(app(GradeMultipleChoice::class));

    expect($right->fresh()->auto_score)->toBe('2.00')
        ->and($wrong->fresh()->auto_score)->toBe('0.00')
        ->and($draft->fresh()->auto_score)->toBeNull();

    $this->artisan('ejaz:grade-competition', ['slug' => $this->competition->slug])->assertSuccessful();
});

it('assigns by hand only submitted entries to the competition reviewers', function () {
    $entry = submittedEntry();
    $draft = CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id]);
    $outsider = userWithRole(RoleName::Reviewer);

    expect(app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]))->toBe(1)
        ->and(app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]))->toBe(0)
        ->and(fn () => app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$outsider->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(AssignReviewers::class)->handle($this->manager, $this->competition, [$draft->id], [$this->reviewerA->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(AssignReviewers::class)->handle($this->reviewerA, $this->competition, [$entry->id], [$this->reviewerA->id]))->toThrow(AuthorizationException::class);
});

it('distributes entries evenly without repeating a reviewer on one entry', function () {
    $entries = collect(range(1, 5))->map(fn () => submittedEntry());

    expect(app(DistributeSubmissions::class)->handle($this->manager, $this->competition, 1))->toBe(5);

    $loads = SubmissionReview::query()->get()->countBy('reviewer_id')->sort()->values()->all();
    expect($loads)->toBe([2, 3]);

    // الثاني لكل مشاركة: المحكّم الآخر، ولا تكرار
    expect(app(DistributeSubmissions::class)->handle($this->manager, $this->competition, 2))->toBe(5)
        ->and(SubmissionReview::query()->count())->toBe(10)
        ->and($entries->every(fn ($entry) => $entry->reviews()->distinct('reviewer_id')->count() === 2))->toBeTrue()
        ->and(fn () => app(DistributeSubmissions::class)->handle($this->manager, $this->competition, 3))->toThrow(ValidationException::class);
});

it('lets the manager assign and distribute from the competition screen', function () {
    $entry = submittedEntry();
    $this->actingAs($this->manager);

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertCanSeeTableRecords([$entry])
        ->selectTableRecords([$entry->id])
        ->callAction(TestAction::make('assign')->table()->bulk(), ['reviewers' => [$this->reviewerB->id]])
        ->assertNotified()
        ->callAction(TestAction::make('distribute')->table(), ['per_submission' => 2])
        ->assertNotified();

    expect($entry->reviews()->pluck('reviewer_id')->sort()->values()->all())->toBe([$this->reviewerA->id, $this->reviewerB->id]);
});

it('shows the submissions to the manager but not to the board or a reviewer', function () {
    submittedEntry();

    $this->actingAs($this->manager)->get(CompetitionResource::getUrl('edit', ['record' => $this->competition]))->assertOk()->assertSee(__('competitions.submissions.plural'));

    expect(SubmissionsRelationManager::canViewForRecord($this->competition, EditCompetition::class))->toBeTrue();

    $this->actingAs(userWithRole(RoleName::BoardMember));
    expect(SubmissionsRelationManager::canViewForRecord($this->competition, EditCompetition::class))->toBeFalse();

    $this->actingAs($this->reviewerA);
    expect(SubmissionsRelationManager::canViewForRecord($this->competition, EditCompetition::class))->toBeFalse();
});

it('shows a reviewer only their own assignments, without the applicant personal data', function () {
    $applicant = User::factory()->create(['name' => 'اسم المتقدم السري', 'email' => 'secret-applicant@example.test']);
    $mine = submittedEntry(attributes: ['user_id' => $applicant->id]);
    $theirs = submittedEntry();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$mine->id], [$this->reviewerA->id]);
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$theirs->id], [$this->reviewerB->id]);

    $myReview = SubmissionReview::where('reviewer_id', $this->reviewerA->id)->sole();
    $otherReview = SubmissionReview::where('reviewer_id', $this->reviewerB->id)->sole();

    $this->actingAs($this->reviewerA)->get(ReviewResource::getUrl('index'))->assertOk()
        ->assertSee($mine->reference)->assertDontSee($theirs->reference);

    $this->get(ReviewResource::getUrl('edit', ['record' => $myReview]))->assertOk()
        ->assertSee($mine->reference)->assertSee('نص المتقدم')->assertSee('الخيار الثاني')
        ->assertDontSee('اسم المتقدم السري')->assertDontSee('secret-applicant@example.test');

    // الوصول لتحكيم غيره بتغيير الرقم في الرابط
    $this->get(ReviewResource::getUrl('edit', ['record' => $otherReview]))->assertNotFound();

    expect($this->reviewerA->can('view', $otherReview))->toBeFalse()
        ->and($this->reviewerA->can('update', $otherReview))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('update', $myReview))->toBeFalse();

    $this->actingAs(userWithRole(RoleName::Member))->get(ReviewResource::getUrl('index'))->assertForbidden();
});

it('scores by the weighted criteria, within each maximum', function () {
    $entry = submittedEntry();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $review = SubmissionReview::sole();
    $this->actingAs($this->reviewerA);

    Livewire::test(EditReview::class, ['record' => $review->getRouteKey()])
        ->fillForm(['scores' => [$this->first->id => 11, $this->second->id => 5], 'complete' => true])
        ->call('save')
        ->assertHasFormErrors(['scores.'.$this->first->id]);

    Livewire::test(EditReview::class, ['record' => $review->getRouteKey()])
        ->fillForm(['scores' => [$this->first->id => 8, $this->second->id => 5], 'complete' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    // 8/10×60 + 5/5×40 = 88
    expect($review->fresh()->total)->toBe('88.00')->and($review->fresh()->completed_at)->not->toBeNull();

    expect(fn () => app(ScoreSubmission::class)->handle($this->reviewerA, $review->fresh(), [$this->first->id => 5], true))->toThrow(ValidationException::class);
});

it('streams a video only to whoever may see the review', function () {
    $entry = submittedEntry();
    Storage::disk('local')->put('competition-videos/clip.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->submission_id = $entry->id;
    $video->question_id = $this->video->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->path = 'competition-videos/clip.mp4';
    $video->save();

    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $review = SubmissionReview::sole();
    $url = route('competitions.reviews.video', [$review, $video]);

    $this->actingAs($this->reviewerA)->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
    $this->actingAs($this->manager)->get($url)->assertOk();
    $this->actingAs($this->reviewerB)->get($url)->assertNotFound();
    $this->actingAs($entry->user)->get($url)->assertNotFound();
});

// على S3 كان المقطع (حتى 2 جيجابايت) يمر عبر PHP بلا Range: يحبس عاملاً طوال المشاهدة، ولا تقديم ولا تأخير، ولا
// يعمل في Safari. الآن رابط تخزين موقّع بعد الفحص كأعمال المشاركة، ومن لا يرى التحكيم لا يصله رابط (تدقيق 4 نوفمبر 2026)
it('sends the reviewer to a short signed storage link for the video on S3, after the same checks', function () {
    config(['filesystems.disks.content-s3' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/content-s3')]]);
    $disk = Storage::fake('content-s3');
    config(['filesystems.disks.content-s3.driver' => 's3', 'filesystems.private_disk' => 'content-s3']);
    $disk->buildTemporaryUrlsUsing(fn (string $path) => 'https://storage.example.test/'.$path.'?signature=x');

    $entry = submittedEntry();
    $disk->put('competition-videos/clip.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->submission_id = $entry->id;
    $video->question_id = $this->video->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->path = 'competition-videos/clip.mp4';
    $video->save();

    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $url = route('competitions.reviews.video', [SubmissionReview::sole(), $video]);

    $this->actingAs($this->reviewerA)->get($url)
        ->assertRedirect('https://storage.example.test/competition-videos/clip.mp4?signature=x')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->reviewerB)->get($url)->assertNotFound();
});

it('ends an idle reviewer session on the review video, like the panel', function () {
    $entry = submittedEntry();
    Storage::disk('local')->put('competition-videos/clip.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->submission_id = $entry->id;
    $video->question_id = $this->video->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->path = 'competition-videos/clip.mp4';
    $video->save();

    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);

    $this->actingAs($this->reviewerA)
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes((int) config('security.admin_idle_minutes') + 1)->getTimestamp()])
        ->get(route('competitions.reviews.video', [SubmissionReview::sole(), $video]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('refuses the review video to a reviewer or manager without two-factor, since the route is outside the panel', function () {
    $entry = submittedEntry();
    Storage::disk('local')->put('competition-videos/clip.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->submission_id = $entry->id;
    $video->question_id = $this->video->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->path = 'competition-videos/clip.mp4';
    $video->save();

    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $url = route('competitions.reviews.video', [SubmissionReview::sole(), $video]);

    foreach ([$this->reviewerA, $this->manager] as $user) {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        $this->actingAs($user)->get($url)->assertForbidden();
    }
});

it('approves results only after closing and complete reviews, then locks the scores', function () {
    $entry = submittedEntry();
    app(DistributeSubmissions::class)->handle($this->manager, $this->competition, 2);
    [$a, $b] = SubmissionReview::orderBy('reviewer_id')->get()->all();

    expect(fn () => app(ApproveResults::class)->handle($this->manager, $this->competition))->toThrow(ValidationException::class, __('competitions.reviews.still_open'));

    $this->travelTo($this->competition->closes_at->addMinute());

    expect(fn () => app(ApproveResults::class)->handle($this->manager, $this->competition))->toThrow(ValidationException::class, __('competitions.reviews.incomplete'));

    $byReviewer = [$this->reviewerA->id => $this->reviewerA, $this->reviewerB->id => $this->reviewerB];
    app(ScoreSubmission::class)->handle($byReviewer[$a->reviewer_id], $a, [$this->first->id => 10, $this->second->id => 5], true);
    app(ScoreSubmission::class)->handle($byReviewer[$b->reviewer_id], $b, [$this->first->id => 5, $this->second->id => 0], true);

    expect(fn () => app(ApproveResults::class)->handle($this->reviewerA, $this->competition))->toThrow(AuthorizationException::class);

    app(ApproveResults::class)->handle($this->manager, $this->competition);

    // (100 + 30) ÷ 2
    expect($entry->fresh()->final_score)->toBe('65.00')
        ->and($this->competition->fresh()->resultsApproved())->toBeTrue()
        ->and(fn () => app(ScoreSubmission::class)->handle($byReviewer[$a->reviewer_id], $a->fresh(), [$this->first->id => 1, $this->second->id => 1], true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DistributeSubmissions::class)->handle($this->manager, $this->competition->fresh(), 1))->toThrow(AuthorizationException::class);

    $this->actingAs($byReviewer[$a->reviewer_id])->get(ReviewResource::getUrl('edit', ['record' => $a]))->assertForbidden();
});

it('locks the criteria after the first assignment and keeps assigned reviewers', function () {
    $entry = submittedEntry();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $base = ['title' => 'x', 'slug' => $this->competition->slug, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()];

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, $this->competition, [...$base, 'criteria' => []]))
        ->toThrow(ValidationException::class, __('competitions.validation.criteria_locked'))
        ->and(fn () => app(SaveCompetition::class)->handle($this->manager, $this->competition, [...$base, 'reviewers' => [$this->reviewerB->id]]))
        ->toThrow(ValidationException::class, __('competitions.validation.reviewer_assigned'))
        ->and(fn () => app(SaveCompetition::class)->handle($this->manager, $this->competition, [...$base, 'reviewers' => [$this->reviewerA->id, $this->manager->id]]))
        ->toThrow(ValidationException::class, __('competitions.validation.reviewers'));

    app(SaveCompetition::class)->handle($this->manager, $this->competition, [...$base, 'reviewers' => [$this->reviewerA->id]]);
    expect($this->competition->reviewers()->pluck('users.id')->all())->toBe([$this->reviewerA->id]);
});

it('refuses a score that reaches the competition after it was approved meanwhile', function () {
    $entry = submittedEntry();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $stale = SubmissionReview::with('submission.competition')->sole();

    // الاعتماد يسبق والنسخة في الذاكرة ما زالت غير معتمدة، فيمر فحص السياسة عليها
    Competition::query()->whereKey($this->competition->id)->update(['results_approved_at' => now()]);

    expect(fn () => app(ScoreSubmission::class)->handle($this->reviewerA, $stale, [$this->first->id => 1, $this->second->id => 1], true))
        ->toThrow(AuthorizationException::class)
        ->and($stale->fresh()->total)->toBeNull();
});

it('refuses a second approval, keeps grading deterministic after it, and refuses assignment without criteria', function () {
    $entry = submittedEntry();
    $this->travelTo($this->competition->closes_at->addMinute());
    app(ApproveResults::class)->handle($this->manager, $this->competition);

    expect(fn () => app(ApproveResults::class)->handle($this->manager, $this->competition->fresh()))->toThrow(AuthorizationException::class);

    app(GradeMultipleChoice::class)->handle($entry->fresh());
    expect($entry->fresh()->auto_score)->toBe('2.00');

    $bare = Competition::factory()->create();
    $bare->reviewers()->attach($this->reviewerA->id);
    $bareEntry = CompetitionSubmission::factory()->submitted()->create(['competition_id' => $bare->id]);

    expect(fn () => app(AssignReviewers::class)->handle($this->manager, $bare, [$bareEntry->id], [$this->reviewerA->id]))->toThrow(ValidationException::class, __('competitions.reviews.no_criteria'));
});

it('keeps saving a competition whose assigned reviewer was deactivated', function () {
    $entry = submittedEntry();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);
    $this->reviewerA->forceFill(['is_active' => false])->save();

    app(SaveCompetition::class)->handle($this->manager, $this->competition, [
        'title' => 'عنوان معدّل', 'slug' => $this->competition->slug, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(),
        'reviewers' => [$this->reviewerA->id, $this->reviewerB->id],
    ]);

    expect($this->competition->fresh()->title)->toBe('عنوان معدّل');
});

it('logs an automatic distribution as one admin operation', function () {
    submittedEntry();
    app(DistributeSubmissions::class)->handle($this->manager, $this->competition, 1);

    expect(Activity::where('event', 'submissions_distributed')->where('causer_id', $this->manager->id)->exists())->toBeTrue();
});

it('grades every ungraded entry during approval without lazy loading', function () {
    $entries = collect([submittedEntry('b'), submittedEntry('a'), submittedEntry('b')]);
    $this->travelTo($this->competition->closes_at->addMinute());

    app(ApproveResults::class)->handle($this->manager, $this->competition);

    expect($entries->map(fn ($entry) => $entry->fresh()->auto_score)->all())->toBe(['2.00', '0.00', '2.00']);
});

// البلاغ 185، I2: رابط المقطع لخمس دقائق بمتغير مستقل، والمشغّل يعيد طلب مسار البوابة عند الخطأ أو التوقف
it('signs the reviewer video link for five minutes, apart from the chunk upload links', function () {
    config(['filesystems.disks.content-s3' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/content-s3')]]);
    $disk = Storage::fake('content-s3');
    config(['filesystems.disks.content-s3.driver' => 's3', 'filesystems.private_disk' => 'content-s3', 'competitions.video.url_minutes' => 120]);
    $expires = null;
    $disk->buildTemporaryUrlsUsing(function (string $path, DateTimeInterface $expiration) use (&$expires): string {
        $expires = $expiration;

        return 'https://storage.example.test/'.$path.'?signature=x';
    });

    $entry = submittedEntry();
    $disk->put('competition-videos/clip.mp4', 'video-bytes');
    $video = new SubmissionVideo;
    $video->submission_id = $entry->id;
    $video->question_id = $this->video->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->path = 'competition-videos/clip.mp4';
    $video->save();
    app(AssignReviewers::class)->handle($this->manager, $this->competition, [$entry->id], [$this->reviewerA->id]);

    expect(config('competitions.video.review_url_minutes'))->toBe(5);
    $this->actingAs($this->reviewerA)->get(route('competitions.reviews.video', [SubmissionReview::sole(), $video]))->assertRedirect();
    expect(now()->diffInSeconds($expires))->toBeGreaterThan(4 * 60)->toBeLessThanOrEqual(5 * 60);

    // والمشغّل يعيد طلب مسار البوابة نفسه عند الخطأ أو التوقف، بحد للمحاولات
    $player = view('filament.reviews.video', ['label' => 'س', 'video' => $video, 'review' => SubmissionReview::sole()])->render();
    expect($player)->toContain('x-on:error="reload()"')->toContain('x-on:stalled="reload()"')
        ->toContain('this.tries++ >= 5')
        ->toContain('src="'.e(route('competitions.reviews.video', [SubmissionReview::sole(), $video])).'"');
});
