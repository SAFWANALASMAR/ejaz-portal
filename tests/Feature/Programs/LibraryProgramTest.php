<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Library\ClearMyReading;
use App\Actions\Programs\CancelProgramRegistration;
use App\Actions\Programs\CompleteLibraryProgram;
use App\Actions\Programs\RegisterForProgram;
use App\Actions\Programs\SaveProgram;
use App\Actions\Users\AnonymizeUser;
use App\Enums\LibraryProgramKind;
use App\Enums\LibrarySection;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RoleName;
use App\Jobs\MeasureLibraryDuration;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\User;
use App\Support\LibraryProgramProgress;
use App\Support\ProgramTypeStats;
use Database\Seeders\ProgramTypesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| برامج المكتبة (قرار صفوان، 31 أكتوبر 19:33 — docs/decisions/2026-11-02-reading-program.md): قراءة كتاب، واستماع، ومشاهدة. يختار
| البرنامج عنصره من المكتبة، والتقدّم من سجل «قراءاتي»، و«أتممت» بعد بلوغ النهاية بقواعد الخادم، ثم شهادة إتمام الاطلاع.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->seed(ProgramTypesSeeder::class);
    $this->member = User::factory()->create();
});

function libraryProgram(LibraryProgramKind $kind, LibraryItem $item, array $attributes = []): Program
{
    return Program::factory()->create([
        'program_type_id' => ProgramType::query()->where('slug', $kind->value)->value('id'),
        'library_item_id' => $item->id,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'seats' => null,
        ...$attributes,
    ]);
}

function confirmedIn(Program $program, User $user): ProgramRegistration
{
    $registration = ProgramRegistration::factory()->create(['program_id' => $program->id, 'user_id' => $user->id, 'status' => RegistrationStatus::Confirmed]);
    // كما يبدأه RegisterForProgram: البداية، وثواني الاستماع قبلها
    $registration->forceFill(['started_at' => now(), 'seconds_baseline' => (int) LibraryProgress::query()->where('user_id', $user->id)->where('library_item_id', $program->library_item_id)->value('seconds_consumed')])->save();

    return $registration;
}

/**
 * صفحات الكتاب المقروءة بعد التسجيل في البرنامج.
 */
function readInProgram(ProgramRegistration $registration, string $pages, int $count): void
{
    $registration->forceFill(['pages_seen' => $pages, 'pages_count' => $count, 'opened_at' => now()])->save();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function progressFor(User $user, LibraryItem $item, array $attributes): LibraryProgress
{
    return LibraryProgress::query()->forceCreate(['user_id' => $user->id, 'library_item_id' => $item->id, 'last_reported_at' => now(), ...$attributes]);
}

it('seeds the three library program types, so the programs page has six tabs from data', function () {
    $this->get(route('programs.index'))->assertOk()
        ->assertSeeInOrder(['الدورات التدريبية', 'المجالس العلمية', 'قراءة الكتب', 'الاستماع', 'المشاهدة']);

    expect(ProgramType::query()->pluck('slug')->all())->toContain('book-reading', 'audio-listening', 'video-watching');
});

it('keeps a library program open by default after it starts, and closes it at its optional end date', function () {
    $program = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 3]));

    expect($program->status())->toBe(ProgramStatus::Open)->and($program->registrationIsOpen())->toBeTrue();

    $program->update(['ends_at' => now()->subMinute()]);
    expect($program->fresh()->status())->toBe(ProgramStatus::Ended);
});

it('completes a reading program after every page is read, then issues the certificate and logs it', function () {
    Queue::fake();
    $book = LibraryItem::factory()->create(['page_count' => 3]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);

    readInProgram($registration, '1-2', 2);
    expect(LibraryProgramProgress::for($program, $this->member)->percent())->toBe(66);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(ValidationException::class);

    readInProgram($registration->fresh(), '1-3', 3);
    app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh());

    expect($registration->fresh()->completed_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'program_completed')->where('causer_id', $this->member->id)->exists())->toBeTrue();
    Queue::assertPushed(SyncAttendanceCertificate::class);
});

it('completes a listening program from server-side seconds against the server-side duration', function () {
    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    $program = libraryProgram(LibraryProgramKind::Listening, $audio);
    $registration = confirmedIn($program, $this->member);

    // مدة يرسلها المتصفح أقصر لا تُعتمد
    progressFor($this->member, $audio, ['seconds_consumed' => 100, 'duration_seconds' => 100, 'last_position' => 100]);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);

    LibraryProgress::query()->update(['seconds_consumed' => 571]);
    expect(app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh())->completed_at)->not->toBeNull();
});

it('completes a YouTube watching program only after its duration has passed since it was first opened', function () {
    $video = LibraryItem::factory()->video()->create(['duration_seconds' => 300]);
    $program = libraryProgram(LibraryProgramKind::Watching, $video);
    $registration = confirmedIn($program, $this->member);

    // أول فتح بعد التسجيل
    $registration->forceFill(['opened_at' => now()])->save();
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(ValidationException::class);

    $this->travel(301)->seconds();
    expect(app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh())->completed_at)->not->toBeNull();
});

it('refuses completion to someone not registered, another user, an ended or unpublished program', function () {
    $book = LibraryItem::factory()->create(['page_count' => 1]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);
    readInProgram($registration, '1', 1);

    $other = User::factory()->create();
    expect(fn () => app(CompleteLibraryProgram::class)->handle($other, $registration))->toThrow(AuthorizationException::class);

    $this->actingAs($other)->post(route('account.programs.complete', $registration))->assertNotFound();

    $program->update(['ends_at' => now()->subMinute()]);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(ValidationException::class);

    $program->update(['ends_at' => null, 'is_published' => false]);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(AuthorizationException::class);

    $waitlisted = confirmedIn(libraryProgram(LibraryProgramKind::Reading, $book), User::factory()->create());
    $waitlisted->update(['status' => RegistrationStatus::Waitlisted]);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($waitlisted->user, $waitlisted->fresh()))->toThrow(AuthorizationException::class);
});

it('completes from the program page and shows the progress, the start button and the done state', function () {
    $book = LibraryItem::factory()->create(['page_count' => 2, 'is_downloadable' => true]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);
    readInProgram($registration, '1', 1);

    $this->actingAs($this->member)->get(route('programs.show', $program->slug))->assertOk()
        ->assertSee('data-library-program-progress', false)
        ->assertSee(route('programs.read', $program->slug), false)
        ->assertSee(__('programs.library.start.book-reading'));

    readInProgram($registration->fresh(), '1-2', 2);
    $this->post(route('account.programs.complete', $registration))->assertRedirect();

    $this->get(route('programs.show', $program->slug))->assertSee('data-library-program-done', false);
});

it('opens a reading program book in the page reader and counts its pages even when the book is downloadable', function () {
    $book = LibraryItem::factory()->create(['page_count' => 3, 'is_downloadable' => true]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);

    $this->actingAs($this->member)->get(route('programs.read', $program->slug))->assertOk()->assertSee('data-pdf-reader', false);
    $this->postJson(route('library.progress', [$book->section->value, $book->slug]), ['pages' => [1], 'page' => 1])->assertOk();

    // والصفحة في «قراءاتي» وفي البرنامج معاً
    expect(LibraryProgress::query()->where('user_id', $this->member->id)->value('pages_count'))->toBe(1)
        ->and($registration->fresh()->pages_count)->toBe(1)
        ->and($registration->fresh()->opened_at)->not->toBeNull()
        ->and($book->fresh()->is_downloadable)->toBeTrue();

    // وغير المسجل لا يفتح القارئ، ولا تُحصى صفحات الكتاب القابل للتنزيل له
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('programs.read', $program->slug))->assertForbidden();
    $this->postJson(route('library.progress', [$book->section->value, $book->slug]), ['pages' => [1], 'page' => 1])->assertUnprocessable();
    expect(LibraryProgress::query()->where('user_id', $stranger->id)->exists())->toBeFalse();
});

it('tells a participant who stopped saving their reading that the program needs it', function () {
    $program = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 2]));
    confirmedIn($program, $this->member);
    $this->member->forceFill(['reading_tracking_disabled_at' => now()])->save();

    $this->actingAs($this->member)->get(route('programs.show', $program->slug))->assertSee('data-library-program-tracking-off', false);
});

it('requires a matching published library item when saving a library program, with a duration for media', function () {
    $admin = userWithRole(RoleName::ProgramManager);
    $type = fn (LibraryProgramKind $kind): int => (int) ProgramType::query()->where('slug', $kind->value)->value('id');
    $base = ['title' => 'برنامج', 'slug' => 'library-program', 'starts_at' => now(), 'delivery' => 'remote', 'is_published' => true];

    $audio = LibraryItem::factory()->audio()->create();
    $unpublished = LibraryItem::factory()->unpublished()->create(['page_count' => 2]);
    $book = LibraryItem::factory()->create(['page_count' => 2]);

    expect(fn () => app(SaveProgram::class)->handle($admin, new Program, [...$base, 'program_type_id' => $type(LibraryProgramKind::Reading), 'library_item_id' => null]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveProgram::class)->handle($admin, new Program, [...$base, 'program_type_id' => $type(LibraryProgramKind::Reading), 'library_item_id' => $audio->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveProgram::class)->handle($admin, new Program, [...$base, 'program_type_id' => $type(LibraryProgramKind::Reading), 'library_item_id' => $unpublished->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveProgram::class)->handle($admin, new Program, [...$base, 'program_type_id' => $type(LibraryProgramKind::Listening), 'library_item_id' => $audio->id]))->toThrow(ValidationException::class);

    $program = app(SaveProgram::class)->handle($admin, new Program, [...$base, 'program_type_id' => $type(LibraryProgramKind::Reading), 'library_item_id' => $book->id]);
    expect($program->library_item_id)->toBe($book->id)->and($program->libraryKind())->toBe(LibraryProgramKind::Reading);

    // ونوع غير مكتبي لا يحمل عنصراً
    $course = app(SaveProgram::class)->handle($admin, new Program, [...$base, 'slug' => 'course', 'program_type_id' => ProgramType::query()->where('slug', 'training-courses')->value('id'), 'library_item_id' => $book->id]);
    expect($course->library_item_id)->toBeNull();
});

it('measures the duration of an audio file on the server', function () {
    config(['competitions.video.ffprobe' => 'ffprobe']);
    Process::fake(['*' => Process::result(output: json_encode(['streams' => [['codec_type' => 'audio']], 'format' => ['duration' => '125.4']]))]);
    $audio = LibraryItem::factory()->audio()->create();
    Storage::disk(LibraryItem::disk())->put((string) $audio->file, 'ID3');

    (new MeasureLibraryDuration($audio, (string) $audio->file))->handle();

    expect($audio->fresh()->duration_seconds)->toBe(126);
});

it('lists my programs with a progress bar and the completed ones apart', function () {
    $book = LibraryItem::factory()->create(['page_count' => 4, 'title' => 'كتاب البرنامج']);
    $reading = libraryProgram(LibraryProgramKind::Reading, $book, ['title' => 'برنامج القراءة الجاري']);
    readInProgram(confirmedIn($reading, $this->member), '1-2', 2);

    $done = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 1]), ['title' => 'برنامج أتممته']);
    confirmedIn($done, $this->member)->forceFill(['completed_at' => now()])->save();

    $this->actingAs($this->member)->get(route('account.programs.index'))->assertOk()
        ->assertSee('data-my-program-progress', false)
        ->assertSeeInOrder(['برنامج القراءة الجاري', __('programs.mine.completed_title'), 'برنامج أتممته']);
});

it('aggregates registrations, completions, pages and minutes per program type, without competitions (counted in #127)', function () {
    $book = LibraryItem::factory()->create(['page_count' => 4]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $member = confirmedIn($program, $this->member);
    readInProgram($member, '1-4', 4);
    $member->forceFill(['completed_at' => now()])->save();
    confirmedIn($program, User::factory()->create());

    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    $listening = libraryProgram(LibraryProgramKind::Listening, $audio);
    confirmedIn($listening, $this->member);
    progressFor($this->member, $audio, ['seconds_consumed' => 300]);

    $rows = collect(ProgramTypeStats::compute())->keyBy('key');

    expect($rows['book-reading'])->toMatchArray(['registered' => 2, 'completed' => 1, 'rate' => 50.0, 'pages' => 4])
        ->and($rows['audio-listening'])->toMatchArray(['registered' => 1, 'minutes' => 5])
        ->and($rows)->not->toHaveKey('competitions');
});

it('leaves progress from before the registration out of the program type stats', function () {
    $book = LibraryItem::factory()->create(['page_count' => 10]);
    progressFor($this->member, $book, ['pages_seen' => '1-10', 'pages_count' => 10, 'total_pages' => 10]);
    $reading = confirmedIn(libraryProgram(LibraryProgramKind::Reading, $book), $this->member);
    readInProgram($reading, '1-2', 2);

    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 1200]);
    progressFor($this->member, $audio, ['seconds_consumed' => 600]);
    confirmedIn(libraryProgram(LibraryProgramKind::Listening, $audio), $this->member);
    LibraryProgress::query()->where('library_item_id', $audio->id)->update(['seconds_consumed' => 720]);

    $rows = collect(ProgramTypeStats::compute())->keyBy('key');

    // صفحتان بعد التسجيل لا عشر، ودقيقتان بعده لا اثنتا عشرة
    expect($rows['book-reading']['pages'])->toBe(2)
        ->and($rows['audio-listening']['minutes'])->toBe(2);
});

it('keeps the library section consistent for each kind', function () {
    expect(LibraryProgramKind::Reading->sections())->toBe([LibrarySection::Books, LibrarySection::Research])
        ->and(LibraryProgramKind::Listening->sections())->toBe([LibrarySection::Audio]);
});

it('counts YouTube watching time from the registration, not from an older first opening', function () {
    $video = LibraryItem::factory()->video()->create(['duration_seconds' => 300]);
    progressFor($this->member, $video, []);

    $this->travel(2)->days();
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Watching, $video), $this->member);

    // فتح قبل التسجيل لا يُحسب، ولا إتمام بلا فتح بعده ولو مرت المدة
    $this->travel(301)->seconds();
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(ValidationException::class);

    $this->actingAs($this->member)->postJson(route('library.progress', [$video->section->value, $video->slug]))->assertOk();
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(ValidationException::class);

    $this->travel(301)->seconds();
    expect(app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh())->completed_at)->not->toBeNull();
});

it('lets a participant cancel an open library program before completing it, and frees the seat', function () {
    $program = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 2]));
    $registration = confirmedIn($program, $this->member);

    expect($this->member->can('cancel', $registration))->toBeTrue();

    $registration->forceFill(['completed_at' => now()])->save();
    expect($this->member->can('cancel', $registration->fresh()))->toBeFalse();
});

it('refuses completion when the library item is unpublished', function () {
    $book = LibraryItem::factory()->create(['page_count' => 1]);
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Reading, $book), $this->member);
    readInProgram($registration, '1', 1);

    $book->update(['is_published' => false]);

    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(AuthorizationException::class);
});

it('does not keep an old book completion after the book got more pages', function () {
    $book = LibraryItem::factory()->create(['page_count' => 2]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    readInProgram(confirmedIn($program, $this->member), '1-2', 2);

    $book->forceFill(['page_count' => 5])->save();

    expect(LibraryProgramProgress::for($program->fresh(), $this->member)->complete())->toBeFalse();
});

it('issues pending completion certificates once the template is ready', function () {
    Queue::fake();
    $program = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 1]));
    confirmedIn($program, $this->member)->forceFill(['completed_at' => now()])->save();

    $this->artisan('programs:issue-pending-certificates')->assertSuccessful();

    Queue::assertPushed(SyncAttendanceCertificate::class);
});

// قرار صفوان (3 نوفمبر 2026): الشهادة «إتمام اطلاع» لا «إتمام قراءة»
it('names the completion certificate «إتمام اطلاع»', function () {
    $program = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 1]));
    $registration = confirmedIn($program, $this->member);
    $registration->forceFill(['completed_at' => now()])->save();

    $html = view('certificates.attendance', ['registration' => $registration->fresh()])->render();

    expect($html)->toContain('شهادة إتمام اطلاع')->not->toContain('إتمام قراءة')
        ->and(__('programs.library.certificate'))->toBe('تنزيل شهادة إتمام الاطلاع');
});

// مراجعة المشرف على #126 (م٢): التقدّم بعد التسجيل وحده لكل نوع، وإعادة التسجيل، و«لا تحفظ قراءاتي»

it('does not let a book read before the registration complete the program', function () {
    $book = LibraryItem::factory()->create(['page_count' => 3]);
    progressFor($this->member, $book, ['pages_seen' => '1-3', 'pages_count' => 3, 'total_pages' => 3, 'completed_at' => now()]);
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Reading, $book), $this->member);

    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);
});

it('counts book pages after the registration at a human pace, not in one scripted report', function () {
    $book = LibraryItem::factory()->create(['page_count' => 40]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);

    LibraryProgramProgress::record($this->member, $book, range(1, 40), 40);
    expect($registration->fresh()->pages_count)->toBe(3);

    $this->travel(80)->seconds();
    LibraryProgramProgress::record($this->member, $book, range(1, 40), 40);

    expect($registration->fresh()->pages_count)->toBe(40)
        ->and(LibraryProgramProgress::for($program, $this->member)->complete())->toBeTrue();
});

it('does not let listening before the registration complete the program', function () {
    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    progressFor($this->member, $audio, ['seconds_consumed' => 600]);
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Listening, $audio), $this->member);

    expect($registration->seconds_baseline)->toBe(600);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);

    LibraryProgress::query()->update(['seconds_consumed' => 1171]);
    expect(app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh())->completed_at)->not->toBeNull();
});

it('resets the completion and its certificate when a participant registers again', function () {
    Queue::fake();
    $member = User::factory()->create();
    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    $program = libraryProgram(LibraryProgramKind::Listening, $audio);
    $registration = confirmedIn($program, $member);
    $registration->forceFill(['status' => RegistrationStatus::Cancelled, 'completed_at' => now(), 'certificate_path' => 'certificates/old.pdf'])->save();
    progressFor($member, $audio, ['seconds_consumed' => 900]);

    $again = app(RegisterForProgram::class)->handle($member, $program->fresh(), []);

    expect($again->id)->toBe($registration->id)
        ->and($again->completed_at)->toBeNull()
        ->and($again->started_at)->not->toBeNull()
        ->and($again->seconds_baseline)->toBe(900);
    Queue::assertPushed(SyncAttendanceCertificate::class);
});

it('refuses completion while reading tracking is off, with the message to turn it on', function () {
    $book = LibraryItem::factory()->create(['page_count' => 1]);
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Reading, $book), $this->member);
    readInProgram($registration, '1', 1);
    $this->member->forceFill(['reading_tracking_disabled_at' => now()])->save();

    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))
        ->toThrow(ValidationException::class, __('programs.library.tracking_off'));

    $this->actingAs($this->member)->get(route('programs.show', $registration->program->slug))
        ->assertSee(__('programs.library.tracking_off'))
        ->assertSee(route('account.profile').'#reading-tracking', false);
});

// حقوق صاحب البيانات في تقدّم برامج المكتبة (مراجعة المشرف على #126)

it('exports the library program progress with the user data', function () {
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 3])), $this->member);
    readInProgram($registration, '1-2', 2);

    $exported = collect(app(ExportUserData::class)->handle($this->member)['program_registrations'])->sole();

    expect($exported)->toMatchArray(['pages_seen' => '1-2', 'pages_count' => 2, 'seconds_baseline' => 0])
        ->and($exported['started_at'])->not->toBeNull()
        ->and($exported['opened_at'])->not->toBeNull();
});

it('resets the unfinished program progress when the user clears their reading, and warns them first', function () {
    $ongoing = confirmedIn(libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 3])), $this->member);
    readInProgram($ongoing, '1-2', 2);
    $done = confirmedIn(libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 1])), $this->member);
    readInProgram($done, '1', 1);
    $done->forceFill(['completed_at' => now()])->save();

    progressFor($this->member, LibraryItem::factory()->create(), ['pages_seen' => '1', 'pages_count' => 1]);
    $this->actingAs($this->member)->get(route('account.my-reading'))->assertSee(__('reading.clear_programs_warning'));

    app(ClearMyReading::class)->handle($this->member);

    expect($ongoing->fresh())->pages_seen->toBeNull()->pages_count->toBe(0)->opened_at->toBeNull()
        ->and($done->fresh())->pages_seen->toBe('1')->completed_at->not->toBeNull();
});

it('clears the program pages when the account is anonymized', function () {
    $registration = confirmedIn(libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 3])), $this->member);
    readInProgram($registration, '1-2', 2);
    $this->member->forceFill(['deletion_requested_at' => now()])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $this->member);

    expect($registration->fresh())->pages_seen->toBeNull()->pages_count->toBe(0);
});

it('starts a library program for a waitlisted participant at the promotion', function () {
    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    $program = libraryProgram(LibraryProgramKind::Listening, $audio, ['seats' => 1, 'has_waitlist' => true]);
    $first = confirmedIn($program, User::factory()->create());

    $waiting = User::factory()->create();
    progressFor($waiting, $audio, ['seconds_consumed' => 400]);
    $waitlisted = ProgramRegistration::factory()->create(['program_id' => $program->id, 'user_id' => $waiting->id, 'status' => RegistrationStatus::Waitlisted, 'waitlisted_at' => now()]);

    $this->travel(1)->hours();
    app(CancelProgramRegistration::class)->handle($first->user, $first);

    expect($waitlisted->fresh())->status->toBe(RegistrationStatus::Confirmed)
        ->seconds_baseline->toBe(400)
        ->and($waitlisted->fresh()->started_at->isSameMinute(now()))->toBeTrue();
});
