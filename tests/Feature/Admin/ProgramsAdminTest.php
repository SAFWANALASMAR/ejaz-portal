<?php

use App\Actions\Programs\RecordProgramAttendance;
use App\Actions\Programs\SaveProgram;
use App\Enums\FormFieldType;
use App\Enums\ProgramDelivery;
use App\Enums\RoleName;
use App\Filament\Exports\ProgramRegistrationExporter;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Programs\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\ProgramTypes\ProgramTypeResource;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
    $this->manager = userWithRole(RoleName::ProgramManager);
    $this->type = ProgramType::factory()->create();
});

it('opens the programs screens to the program manager only', function () {
    $program = Program::factory()->create();

    $this->actingAs($this->manager)->get(ProgramResource::getUrl('index'))->assertOk();
    $this->actingAs($this->manager)->get(ProgramResource::getUrl('edit', ['record' => $program]))->assertOk();
    $this->actingAs($this->manager)->get(ProgramTypeResource::getUrl('index'))->assertOk();

    foreach ([RoleName::ContentEditor, RoleName::Secretary, RoleName::BoardMember, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(ProgramResource::getUrl('index'))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(ProgramTypeResource::getUrl('index'))->assertForbidden();
    }
});

it('creates a program with extra registration fields and logs it', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateProgram::class)
        ->fillForm([
            'title' => 'ندوة علمية', 'slug' => 'scientific-seminar', 'program_type_id' => $this->type->id,
            'starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'delivery' => ProgramDelivery::Hybrid->value,
            'seats' => 50, 'has_waitlist' => true, 'is_published' => true,
            'form_fields' => [['label' => 'التخصص', 'key' => 'major', 'type' => FormFieldType::Text->value, 'required' => true]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $program = Program::where('slug', 'scientific-seminar')->sole();
    expect($program->seats)->toBe(50)
        ->and($program->fields()[0]['key'])->toBe('major')
        ->and(Activity::whereMorphedTo('subject', $program)->where('event', 'created')->exists())->toBeTrue();
});

it('refuses an end before the start and an unsafe meeting link', function (array $overrides, string $field) {
    $this->actingAs($this->manager);

    Livewire::test(CreateProgram::class)
        ->fillForm([
            'title' => 'برنامج', 'slug' => 'bad-program', 'program_type_id' => $this->type->id,
            'starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'delivery' => ProgramDelivery::Remote->value,
            ...$overrides,
        ])
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(Program::count())->toBe(0);
})->with([
    'end before start' => [['ends_at' => now()->addDay()->format('Y-m-d H:i')], 'ends_at'],
    'javascript link' => [['meeting_url' => 'javascript:alert(1)'], 'meeting_url'],
]);

it('refuses file fields in program registration forms, even outside the panel', function () {
    expect(fn () => app(SaveProgram::class)->handle($this->manager, new Program, [
        ...Program::factory()->raw(['program_type_id' => $this->type->id]),
        'form_fields' => [['label' => 'ملف', 'key' => 'cv', 'type' => FormFieldType::Pdf->value]],
    ]))->toThrow(ValidationException::class);

    expect(Program::count())->toBe(0);

    expect(fn () => app(SaveProgram::class)->handle($this->manager, new Program, [
        ...Program::factory()->raw(['program_type_id' => $this->type->id]),
        'meeting_url' => 'javascript:alert(1)',
    ]))->toThrow(ValidationException::class);
});

it('records attendance for confirmed registrants once the program started, and refuses before', function () {
    $program = Program::factory()->create(['starts_at' => now()->subHour()]);
    $confirmed = ProgramRegistration::factory()->for($program)->create();
    $waiting = ProgramRegistration::factory()->waitlisted()->for($program)->create();
    $this->actingAs($this->manager);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => EditProgram::class])
        ->assertCanSeeTableRecords([$confirmed, $waiting])
        ->callTableBulkAction('markAttended', [$confirmed, $waiting]);

    expect($confirmed->fresh()->attended_at)->not->toBeNull()
        ->and($waiting->fresh()->attended_at)->toBeNull();

    $future = Program::factory()->create();
    expect(fn () => app(RecordProgramAttendance::class)->handle($this->manager, $future, collect(), true))->toThrow(ValidationException::class)
        ->and(fn () => app(RecordProgramAttendance::class)->handle(userWithRole(RoleName::ContentEditor), $program, collect([$confirmed]), true))->toThrow(AuthorizationException::class);
});

it('exports the registrants of a program to Excel for the manager', function () {
    $program = Program::factory()->create();
    ProgramRegistration::factory()->count(3)->for($program)->create();
    ProgramRegistration::factory()->create();
    $this->actingAs($this->manager);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => EditProgram::class])
        ->callAction(TestAction::make('export')->table());

    $export = Export::sole();
    expect($export->exporter)->toBe(ProgramRegistrationExporter::class)->and($export->total_rows)->toBe(3);

    $this->actingAs($this->manager)->get(route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']))->assertOk();
});

it('hides the registrants export from staff who may view programs but not export them', function () {
    $program = Program::factory()->create();
    $viewer = userWithRole(RoleName::ProgramManager);
    $viewer->syncRoles([]);
    $viewer->assignRole(RoleName::BoardMember->value);
    $viewer->givePermissionTo('programs.view');

    $this->actingAs($viewer);
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => EditProgram::class])
        ->assertActionHidden(TestAction::make('export')->table());
});

it('keeps a program type with programs from being deleted', function () {
    Program::factory()->for($this->type, 'type')->create();

    expect(fn () => $this->type->delete())->toThrow(QueryException::class);
});
