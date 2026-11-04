<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Programs\CancelProgramRegistration;
use App\Actions\Programs\RegisterForProgram;
use App\Actions\Users\AnonymizeUser;
use App\Enums\FormFieldType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RoleName;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\User;
use App\Notifications\ProgramRegistrationChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
    $this->type = ProgramType::factory()->create(['name' => 'تدريبية', 'slug' => 'training']);
    $this->program = Program::factory()->for($this->type, 'type')->create(['slug' => 'research-skills', 'title' => 'مهارات البحث']);
});

it('lists published programs with type tabs, status badges and the register button', function () {
    $other = ProgramType::factory()->create(['name' => 'بحثية', 'slug' => 'research']);
    Program::factory()->for($other, 'type')->create(['title' => 'برنامج بحثي']);
    Program::factory()->unpublished()->create(['title' => 'برنامج مخفي']);

    $this->get('/programs')->assertOk()
        ->assertSee('تدريبية')->assertSee('بحثية')
        ->assertSee('مهارات البحث')->assertSee('برنامج بحثي')->assertDontSee('برنامج مخفي')
        ->assertSee('مفتوح')->assertSee('سجّل الآن');

    $this->get('/programs?type=research')->assertOk()->assertSee('برنامج بحثي')->assertDontSee('مهارات البحث');
    $this->get('/programs?type=unknown')->assertRedirect(route('programs.index'));
    $this->get('/programs?page=5')->assertNotFound();
    $this->get('/programs/research-skills')->assertOk()->assertSee('مهارات البحث');
    $this->get('/programs/'.Program::where('title', 'برنامج مخفي')->value('slug'))->assertNotFound();
});

it('computes the shown status from dates and seats', function () {
    expect($this->program->status())->toBe(ProgramStatus::Open);

    $this->program->update(['registration_opens_at' => now()->addDay()]);
    expect($this->program->fresh()->status())->toBe(ProgramStatus::Soon);

    $this->program->update(['registration_opens_at' => null, 'seats' => 1]);
    ProgramRegistration::factory()->for($this->program)->create();
    expect($this->program->fresh()->status())->toBe(ProgramStatus::Full);

    $this->program->update(['has_waitlist' => true]);
    expect($this->program->fresh()->status())->toBe(ProgramStatus::Open);

    $this->program->update(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
    expect($this->program->fresh()->status())->toBe(ProgramStatus::Ended);
});

it('registers a verified user with the extra fields, confirms by email, and refuses a second registration', function () {
    $this->program->update(['form_fields' => [['key' => 'job', 'label' => 'جهة العمل', 'type' => FormFieldType::Text->value, 'required' => true]]]);

    $this->actingAs($this->user)->get('/programs/research-skills/register')->assertOk()->assertSee('جهة العمل');
    $this->post('/programs/research-skills/register', ['answers' => ['job' => '']])->assertSessionHasErrors('answers.job');
    $this->post('/programs/research-skills/register', ['answers' => ['job' => 'جامعة']])->assertRedirect('/programs/research-skills');

    $registration = ProgramRegistration::sole();
    expect($registration->status)->toBe(RegistrationStatus::Confirmed)
        ->and($registration->answers)->toBe(['job' => 'جامعة']);
    Notification::assertSentTo($this->user, ProgramRegistrationChanged::class, fn ($n) => $n->kind === ProgramRegistrationChanged::CONFIRMED);

    $this->post('/programs/research-skills/register', ['answers' => ['job' => 'أخرى']])->assertSessionHas('error', __('programs.errors.already_registered'));
    expect(ProgramRegistration::count())->toBe(1);
    $this->get('/programs/research-skills/register')->assertRedirect('/programs/research-skills');
});

it('asks guests to log in and unverified users to verify before registering', function () {
    enableVerification();
    $this->get('/programs/research-skills/register')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get('/programs/research-skills/register')->assertRedirect(route('verification.notice'));
});

it('refuses registration outside the window, on unpublished programs, and when full without a waitlist', function () {
    $register = fn (Program $program) => app(RegisterForProgram::class)->handle(User::factory()->create(), $program, []);

    expect(fn () => $register(Program::factory()->create(['registration_opens_at' => now()->addDay()])))->toThrow(ValidationException::class)
        ->and(fn () => $register(Program::factory()->create(['registration_closes_at' => now()->subMinute()])))->toThrow(ValidationException::class)
        ->and(fn () => $register(Program::factory()->create(['starts_at' => now()->subMinute()])))->toThrow(ValidationException::class)
        ->and(fn () => $register(Program::factory()->unpublished()->create()))->toThrow(ValidationException::class);

    $full = Program::factory()->create(['seats' => 1]);
    $register($full);
    expect(fn () => $register($full))->toThrow(ValidationException::class);

    $this->actingAs($this->user)->post('/programs/'.Program::factory()->unpublished()->create()->slug.'/register')->assertNotFound();
});

it('puts late registrants on the waitlist and promotes the first one when a seat is freed', function () {
    $this->program->update(['seats' => 1, 'has_waitlist' => true]);
    $register = app(RegisterForProgram::class);

    $first = $register->handle($this->user, $this->program, []);
    $second = $register->handle($waiting = User::factory()->create(), $this->program, []);
    $third = $register->handle(User::factory()->create(), $this->program, []);

    expect($first->status)->toBe(RegistrationStatus::Confirmed)
        ->and($second->status)->toBe(RegistrationStatus::Waitlisted)
        ->and($third->status)->toBe(RegistrationStatus::Waitlisted);

    $this->actingAs($this->user)->post(route('account.programs.cancel', $first))->assertRedirect(route('account.programs.index'));

    expect($first->fresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($second->fresh()->status)->toBe(RegistrationStatus::Confirmed)
        ->and($third->fresh()->status)->toBe(RegistrationStatus::Waitlisted);
    Notification::assertSentTo($waiting, ProgramRegistrationChanged::class, fn ($n) => $n->kind === ProgramRegistrationChanged::PROMOTED);

    // التسجيل الملغى يُعاد تفعيله، ويأخذ مكانه في آخر الانتظار
    $again = $register->handle($this->user, $this->program, []);
    expect($again->id)->toBe($first->id)->and($again->status)->toBe(RegistrationStatus::Waitlisted);
});

it('shows the meeting link to confirmed registrants only', function () {
    $online = Program::factory()->remote('https://teams.microsoft.com/l/meetup-join/secret-link')->create(['slug' => 'online']);
    $waitlisted = User::factory()->create();
    ProgramRegistration::factory()->for($online)->for($this->user)->create();
    ProgramRegistration::factory()->waitlisted()->for($online)->for($waitlisted)->create();

    $this->get('/programs/online')->assertOk()->assertDontSee('secret-link');
    $this->actingAs($waitlisted)->get('/programs/online')->assertOk()->assertDontSee('secret-link');
    $this->actingAs(User::factory()->create())->get('/programs/online')->assertDontSee('secret-link');
    $this->actingAs($this->user)->get('/programs/online')->assertOk()->assertSee('secret-link');
});

it('lists my programs only and never lets anyone cancel another registration', function () {
    $mine = ProgramRegistration::factory()->for($this->program)->for($this->user)->create();
    $theirs = ProgramRegistration::factory()->for(Program::factory()->create(['title' => 'برنامج غيري']))->create();

    $this->actingAs($this->user)->get('/account/programs')->assertOk()->assertSee('مهارات البحث')->assertDontSee('برنامج غيري');
    $this->post(route('account.programs.cancel', $theirs))->assertNotFound();
    expect($theirs->fresh()->status)->toBe(RegistrationStatus::Confirmed);

    // ولا بعد بدء البرنامج
    $this->program->update(['starts_at' => now()->subHour()]);
    expect(fn () => app(CancelProgramRegistration::class)->handle($this->user, $mine->fresh()))->toThrow(AuthorizationException::class);

    auth()->logout();
    $this->get('/account/programs')->assertRedirect(route('login'));
});

it('reminds confirmed registrants a day before, once', function () {
    $soon = Program::factory()->create(['starts_at' => now()->addHours(20)]);
    $confirmed = ProgramRegistration::factory()->for($soon)->create();
    ProgramRegistration::factory()->waitlisted()->for($soon)->create();
    ProgramRegistration::factory()->for($this->program)->create();

    $this->artisan('programs:send-reminders')->expectsOutput('1')->assertSuccessful();
    $this->artisan('programs:send-reminders')->expectsOutput('0')->assertSuccessful();

    Notification::assertSentTo($confirmed->user, ProgramRegistrationChanged::class, fn ($n) => $n->kind === ProgramRegistrationChanged::REMINDER);
    Notification::assertSentTimes(ProgramRegistrationChanged::class, 1);
});

it('adds my programs to the account navigation', function () {
    $this->actingAs($this->user)->get('/account')->assertOk()->assertSee(route('account.programs.index'));
});

it('includes my program registrations in my personal data copy', function () {
    ProgramRegistration::factory()->for($this->program)->for($this->user)->create(['answers' => ['job' => 'جامعة']]);
    ProgramRegistration::factory()->create();

    $data = app(ExportUserData::class)->handle($this->user);

    expect($data['program_registrations'])->toHaveCount(1)
        ->and($data['program_registrations'][0]['answers'])->toBe(['job' => 'جامعة']);
});

it('releases the seats of an anonymized account to the waitlist and stops its reminders', function () {
    $this->program->update(['seats' => 1, 'has_waitlist' => true, 'starts_at' => now()->addHours(12)]);
    $gone = ProgramRegistration::factory()->for($this->program)->create();
    $next = ProgramRegistration::factory()->waitlisted()->for($this->program)->create();
    $gone->user->forceFill(['deletion_requested_at' => now()])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $gone->user);

    expect($gone->fresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($next->fresh()->status)->toBe(RegistrationStatus::Confirmed);

    $inactive = ProgramRegistration::factory()->for($this->program)->create(['status' => RegistrationStatus::Waitlisted]);
    $inactive->user->forceFill(['is_active' => false])->save();
    $this->artisan('programs:send-reminders')->expectsOutput('1')->assertSuccessful();
    Notification::assertNotSentTo($inactive->user, ProgramRegistrationChanged::class, fn ($n) => $n->kind === ProgramRegistrationChanged::REMINDER);
});

it('refuses registering an unverified or inactive account even outside the route', function () {
    enableVerification();
    expect(fn () => app(RegisterForProgram::class)->handle(User::factory()->unverified()->create(), $this->program, []))->toThrow(AuthorizationException::class);

    $inactive = User::factory()->create();
    $inactive->forceFill(['is_active' => false])->save();
    expect(fn () => app(RegisterForProgram::class)->handle($inactive, $this->program, []))->toThrow(AuthorizationException::class);
});

it('keeps the signed-in program page out of shared caches', function () {
    $this->actingAs($this->user)->get('/programs/research-skills')->assertHeader('Cache-Control', 'no-store, private');
});

it('closes the register page of a full program without a waitlist and explains why after a late submit', function () {
    $this->program->update(['seats' => 1]);
    ProgramRegistration::factory()->for($this->program)->create();

    $this->actingAs($this->user)->get('/programs/research-skills/register')->assertRedirect('/programs/research-skills');
    $this->post('/programs/research-skills/register')->assertRedirect('/programs/research-skills')->assertSessionHas('error', __('programs.errors.full'));
    $this->get('/programs/research-skills')->assertSee(__('programs.errors.full'));
});

it('promotes nobody when a waitlisted registrant cancels, or when the program was withdrawn', function () {
    $this->program->update(['seats' => 1, 'has_waitlist' => true]);
    $confirmed = ProgramRegistration::factory()->for($this->program)->for($this->user)->create();
    $first = ProgramRegistration::factory()->waitlisted()->for($this->program)->create();
    $second = ProgramRegistration::factory()->waitlisted()->for($this->program)->create();

    app(CancelProgramRegistration::class)->handle($first->user, $first);
    expect($second->fresh()->status)->toBe(RegistrationStatus::Waitlisted);

    $this->program->delete();
    app(CancelProgramRegistration::class)->handle($this->user, $confirmed->fresh());
    expect($second->fresh()->status)->toBe(RegistrationStatus::Waitlisted);
});
