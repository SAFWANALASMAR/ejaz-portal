<?php

use App\Actions\Programs\CompleteLibraryProgram;
use App\Actions\Programs\SaveProgram;
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
| البرنامج عنصره من المكتبة، والتقدّم من سجل «قراءاتي»، و«أتممت» بعد بلوغ النهاية بقواعد الخادم، ثم شهادة الإتمام.
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
    return ProgramRegistration::factory()->create(['program_id' => $program->id, 'user_id' => $user->id, 'status' => RegistrationStatus::Confirmed]);
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

    progressFor($this->member, $book, ['pages_seen' => '1-2', 'pages_count' => 2, 'total_pages' => 3]);
    expect(LibraryProgramProgress::for($program, $this->member)->percent())->toBe(66);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);

    LibraryProgress::query()->update(['pages_seen' => '1-3', 'pages_count' => 3, 'completed_at' => now()]);
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

    progressFor($this->member, $video, []);
    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);

    $this->travel(301)->seconds();
    expect(app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh())->completed_at)->not->toBeNull();
});

it('refuses completion to someone not registered, another user, an ended or unpublished program', function () {
    $book = LibraryItem::factory()->create(['page_count' => 1]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    $registration = confirmedIn($program, $this->member);
    progressFor($this->member, $book, ['pages_seen' => '1', 'pages_count' => 1, 'total_pages' => 1, 'completed_at' => now()]);

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
    progressFor($this->member, $book, ['pages_seen' => '1', 'pages_count' => 1, 'total_pages' => 2]);

    $this->actingAs($this->member)->get(route('programs.show', $program->slug))->assertOk()
        ->assertSee('data-library-program-progress', false)
        ->assertSee(route('programs.read', $program->slug), false)
        ->assertSee(__('programs.library.start.book-reading'));

    LibraryProgress::query()->update(['pages_seen' => '1-2', 'pages_count' => 2, 'completed_at' => now()]);
    $this->post(route('account.programs.complete', $registration))->assertRedirect();

    $this->get(route('programs.show', $program->slug))->assertSee('data-library-program-done', false);
});

it('opens a reading program book in the page reader and counts its pages even when the book is downloadable', function () {
    $book = LibraryItem::factory()->create(['page_count' => 3, 'is_downloadable' => true]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    confirmedIn($program, $this->member);

    $this->actingAs($this->member)->get(route('programs.read', $program->slug))->assertOk()->assertSee('data-pdf-reader', false);
    $this->postJson(route('library.progress', [$book->section->value, $book->slug]), ['pages' => [1], 'page' => 1])->assertOk();

    expect(LibraryProgress::query()->where('user_id', $this->member->id)->value('pages_count'))->toBe(1)
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
    confirmedIn($reading, $this->member);
    progressFor($this->member, $book, ['pages_seen' => '1-2', 'pages_count' => 2, 'total_pages' => 4]);

    $done = libraryProgram(LibraryProgramKind::Reading, LibraryItem::factory()->create(['page_count' => 1]), ['title' => 'برنامج أتممته']);
    confirmedIn($done, $this->member)->forceFill(['completed_at' => now()])->save();

    $this->actingAs($this->member)->get(route('account.programs.index'))->assertOk()
        ->assertSee('data-my-program-progress', false)
        ->assertSeeInOrder(['برنامج القراءة الجاري', __('programs.mine.completed_title'), 'برنامج أتممته']);
});

it('aggregates registrations, completions, pages and minutes per program type, with competitions as a type', function () {
    $book = LibraryItem::factory()->create(['page_count' => 4]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    confirmedIn($program, $this->member)->forceFill(['completed_at' => now()])->save();
    confirmedIn($program, User::factory()->create());
    progressFor($this->member, $book, ['pages_seen' => '1-4', 'pages_count' => 4, 'total_pages' => 4]);

    $audio = LibraryItem::factory()->audio()->create(['duration_seconds' => 600]);
    $listening = libraryProgram(LibraryProgramKind::Listening, $audio);
    confirmedIn($listening, $this->member);
    progressFor($this->member, $audio, ['seconds_consumed' => 300]);

    $rows = collect(ProgramTypeStats::compute())->keyBy('key');

    expect($rows['book-reading'])->toMatchArray(['registered' => 2, 'completed' => 1, 'rate' => 50.0, 'pages' => 4])
        ->and($rows['audio-listening'])->toMatchArray(['registered' => 1, 'minutes' => 5])
        ->and($rows)->toHaveKey('competitions');
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

    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration))->toThrow(ValidationException::class);

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
    progressFor($this->member, $book, ['pages_seen' => '1', 'pages_count' => 1, 'total_pages' => 1, 'completed_at' => now()]);

    $book->update(['is_published' => false]);

    expect(fn () => app(CompleteLibraryProgram::class)->handle($this->member, $registration->fresh()))->toThrow(AuthorizationException::class);
});

it('does not keep an old book completion after the book got more pages', function () {
    $book = LibraryItem::factory()->create(['page_count' => 2]);
    $program = libraryProgram(LibraryProgramKind::Reading, $book);
    confirmedIn($program, $this->member);
    progressFor($this->member, $book, ['pages_seen' => '1-2', 'pages_count' => 2, 'total_pages' => 2, 'completed_at' => now()]);

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
