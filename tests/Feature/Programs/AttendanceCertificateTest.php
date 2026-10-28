<?php

use App\Actions\Programs\RecordProgramAttendance;
use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\RelationManagers\RegistrationsRelationManager;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $this->user);

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

    expect(fn () => app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $this->user))->toThrow(RuntimeException::class);

    // الإخفاء كله ينتظر إعادة المحاولة، والشهادة ومسارها كما هما
    expect($this->registration->fresh()->certificate_path)->toBe($path)
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and($this->user->fresh()->anonymized_at)->toBeNull();
});

// decisions.md (19 أكتوبر 2026): لا شهادة ناقصة في الإنتاج قبل قالب الجمعية، والمسؤول يرى السبب
it('issues no certificate in production until the association template is set, and tells the manager why', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->registration->update(['attended_at' => now()]);

    (new SyncAttendanceCertificate($this->registration))->handle();

    expect($this->registration->fresh()->certificate_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([]);
    $this->actingAs($this->user)->get('/account/programs')->assertDontSee(__('programs.certificate.download'));

    $this->actingAs(userWithRole(RoleName::ProgramManager));
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->program, 'pageClass' => EditProgram::class])
        ->assertSee(__('programs.certificate.paused'));

    config(['programs.certificate_template_ready' => true]);
    (new SyncAttendanceCertificate($this->registration))->handle();

    expect($this->registration->fresh()->certificate_path)->toStartWith('certificates/');
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->program, 'pageClass' => EditProgram::class])
        ->assertDontSee(__('programs.certificate.paused'));
});

// مراجعة ما بعد v0.12.0 (مهمة ١): الحضور المسجَّل قبل القالب لا يعيد إرسال مهمته شيء، فأمر يصدرها بعد التفعيل
it('issues the certificates held back before the template once it is set, and refuses before', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->registration->update(['attended_at' => now()]);
    (new SyncAttendanceCertificate($this->registration))->handle();
    $issued = ProgramRegistration::factory()->for($this->program)->create(['attended_at' => now(), 'certificate_path' => 'certificates/old.pdf']);
    ProgramRegistration::factory()->for($this->program)->create();
    ProgramRegistration::factory()->waitlisted()->for($this->program)->create(['attended_at' => now()]);
    $anonymized = ProgramRegistration::factory()->for($this->program)->create(['attended_at' => now()]);
    $anonymized->user->forceFill(['anonymized_at' => now()])->save();

    Bus::fake();
    $this->artisan('programs:issue-pending-certificates')->assertFailed();
    Bus::assertNotDispatched(SyncAttendanceCertificate::class);

    config(['programs.certificate_template_ready' => true]);
    $this->artisan('programs:issue-pending-certificates')->expectsOutput('1')->assertSuccessful();

    Bus::assertDispatchedTimes(SyncAttendanceCertificate::class, 1);
    Bus::assertDispatched(SyncAttendanceCertificate::class, fn ($job) => $job->registration->is($this->registration));
    expect($issued->fresh()->certificate_path)->toBe('certificates/old.pdf');
});

it('prints the missing-content mark on the temporary template outside production only', function () {
    $missing = __('programs.certificate.missing');

    expect(view('certificates.attendance', ['registration' => $this->registration])->render())->toContain($missing);

    app()->detectEnvironment(fn () => 'production');

    expect(view('certificates.attendance', ['registration' => $this->registration])->render())->not->toContain($missing)
        ->not->toContain(__('site.missing'));
});
