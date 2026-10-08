<?php

use App\Actions\Programs\RecordProgramAttendance;
use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->program = Program::factory()->create(['slug' => 'seminar', 'title' => 'ندوة', 'starts_at' => now()->subHour()]);
    $this->registration = ProgramRegistration::factory()->for($this->program)->for($this->user)->create();
});

it('queues the certificate when attendance is recorded or removed', function () {
    Bus::fake();
    $manager = userWithRole(RoleName::ProgramManager);

    app(RecordProgramAttendance::class)->handle($manager, $this->program, collect([$this->registration]), true);
    Bus::assertDispatched(SyncAttendanceCertificate::class, fn ($job) => $job->registration->is($this->registration));

    // لا تغيير، فلا مهمة جديدة
    app(RecordProgramAttendance::class)->handle($manager, $this->program, collect([$this->registration->fresh()]), true);
    Bus::assertDispatchedTimes(SyncAttendanceCertificate::class, 1);
});

it('generates a real PDF for an attendee, lets only them download it, and removes it with the attendance', function () {
    $this->registration->update(['attended_at' => now()]);
    (new SyncAttendanceCertificate($this->registration))->handle();

    $registration = $this->registration->fresh();
    expect($registration->certificate_path)->toStartWith('certificates/')
        ->and($registration->certificate_issued_at)->not->toBeNull()
        ->and(Storage::disk('local')->get($registration->certificate_path))->toStartWith('%PDF-');

    $this->actingAs($this->user)->get('/account/programs')->assertSee(__('programs.certificate.download'));
    $this->get(route('account.programs.certificate', $registration))->assertOk()->assertDownload('certificate-seminar.pdf');

    $this->actingAs(User::factory()->create())->get(route('account.programs.certificate', $registration))->assertNotFound();

    $registration->update(['attended_at' => null]);
    (new SyncAttendanceCertificate($registration))->handle();

    expect($registration->fresh()->certificate_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([]);
    $this->actingAs($this->user)->get(route('account.programs.certificate', $registration))->assertNotFound();
});

it('issues no certificate without attendance or to a waitlisted registrant', function () {
    (new SyncAttendanceCertificate($this->registration))->handle();
    $waiting = ProgramRegistration::factory()->waitlisted()->for($this->program)->create(['attended_at' => now()]);
    (new SyncAttendanceCertificate($waiting))->handle();

    expect($this->registration->fresh()->certificate_path)->toBeNull()
        ->and($waiting->fresh()->certificate_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});

it('sends guests to log in, keeps placeholders in names literal, and drops certificates of anonymized accounts', function () {
    $this->user->forceFill(['name' => 'اسم {PAGENO} {DATE Y}'])->save();
    $this->registration->update(['attended_at' => now()]);
    (new SyncAttendanceCertificate($this->registration))->handle();
    $registration = $this->registration->fresh();

    $this->get(route('account.programs.certificate', $registration))->assertRedirect(route('login'));

    $this->user->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $this->user);

    expect($registration->fresh()->certificate_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});

it('keeps a certificate and its path when the disk cannot delete it, so no file with the name is left unreferenced', function () {
    // مراجعة الوكيل على التكامل: نمط ٥.٢ في الشهادات. والأقراص بـ throw: false تعيد false بلا استثناء
    $this->registration->update(['attended_at' => now()]);
    (new SyncAttendanceCertificate($this->registration))->handle();
    $path = $this->registration->fresh()->certificate_path;
    failDeletesOf((string) $path);

    $this->user->forceFill(['deletion_requested_at' => now()])->save();

    expect(fn () => app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $this->user))->toThrow(RuntimeException::class);

    // الإخفاء كله ينتظر إعادة المحاولة، والشهادة ومسارها كما هما
    expect($this->registration->fresh()->certificate_path)->toBe($path)
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and($this->user->fresh()->anonymized_at)->toBeNull();
});
