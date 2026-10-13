<?php

use App\Actions\Programs\RecordProgramAttendance;
use App\Enums\ProgramDelivery;
use App\Enums\RoleName;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\ViewProgram;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Programs\RelationManagers\RegistrationsRelationManager;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * بوابة الموظفين (requirements/12 §12 ج): «رفع الملفات»، و«تسجيل الحضور»، و«إدارة النماذج»، و«متابعة البرامج»،
 * دون تعديل البرامج ولا تصدير مسجليها.
 */
beforeEach(fn () => $this->employee = userWithRole(RoleName::ProjectsManager));

it('follows a program on a read-only page with its registrants, without editing it', function () {
    $program = Program::factory()->create();
    $registration = ProgramRegistration::factory()->for($program)->create();
    $this->actingAs($this->employee);

    $this->get(ProgramResource::getUrl('index'))->assertOk();
    $this->get(ProgramResource::getUrl('view', ['record' => $program]))->assertOk();
    $this->get(ProgramResource::getUrl('edit', ['record' => $program]))->assertForbidden();
    $this->get(ProgramResource::getUrl('create'))->assertForbidden();

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => ViewProgram::class])
        ->assertCanSeeTableRecords([$registration])
        ->assertActionHidden(TestAction::make('export')->table());

    expect($this->employee->can('update', $program))->toBeFalse()
        ->and($this->employee->can('delete', $program))->toBeFalse();
});

it('records attendance without being able to edit the program', function () {
    $program = Program::factory()->create(['starts_at' => now()->subHour()]);
    $registration = ProgramRegistration::factory()->for($program)->create();
    $this->actingAs($this->employee);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => ViewProgram::class])
        ->callTableBulkAction('markAttended', [$registration]);

    expect($registration->fresh()->attended_at)->not->toBeNull();
});

it('refuses attendance to roles without the attendance permission', function (RoleName $role) {
    $program = Program::factory()->create(['starts_at' => now()->subHour()]);
    $registration = ProgramRegistration::factory()->for($program)->create();

    expect(fn () => app(RecordProgramAttendance::class)->handle(userWithRole($role), $program, collect([$registration]), true))
        ->toThrow(AuthorizationException::class);

    expect($registration->fresh()->attended_at)->toBeNull();
})->with([RoleName::BoardMember, RoleName::ContentEditor, RoleName::Reviewer, RoleName::Member]);

it('uploads files only into the archive categories that include the role', function () {
    $own = ArchiveCategory::factory()->roles([RoleName::ProjectsManager->value])->create();
    $other = ArchiveCategory::factory()->roles([RoleName::Secretary->value])->create();
    $mine = ArchiveItem::factory()->for($own, 'category')->create();
    $hidden = ArchiveItem::factory()->for($other, 'category')->create();
    $this->actingAs($this->employee);

    $this->get(ArchiveItemResource::getUrl('index'))->assertOk();
    $this->get(ArchiveItemResource::getUrl('create'))->assertOk();

    expect($this->employee->can('create', ArchiveItem::class))->toBeTrue()
        ->and($this->employee->can('view', $mine))->toBeTrue()
        ->and($this->employee->can('view', $hidden))->toBeFalse()
        ->and($this->employee->can('delete', $mine))->toBeFalse();
});

it('manages the electronic forms', function () {
    $this->actingAs($this->employee);

    $this->get(FormTemplateResource::getUrl('index'))->assertOk();
    $this->get(FormTemplateResource::getUrl('create'))->assertOk();
    expect($this->employee->can('form_entries.approve'))->toBeFalse();
});

it('shows employees the registrant names only, and their contact details to the program managers', function () {
    $program = Program::factory()->create();
    $registration = ProgramRegistration::factory()->for($program)->create();
    $this->actingAs($this->employee);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => ViewProgram::class])
        ->assertTableColumnVisible('user.name')
        ->assertTableColumnHidden('user.email')
        ->assertTableColumnHidden('user.phone')
        ->assertDontSee($registration->user->email);

    $this->actingAs(userWithRole(RoleName::ProgramManager));

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => ViewProgram::class])
        ->assertTableColumnVisible('user.email')
        ->assertTableColumnVisible('user.phone');
});

it('refuses attendance on a deleted program for everyone, and its page to employees', function () {
    $program = Program::factory()->create(['starts_at' => now()->subHour()]);
    $registration = ProgramRegistration::factory()->for($program)->create();
    $program->delete();

    foreach ([$this->employee, userWithRole(RoleName::ProgramManager), userWithRole(RoleName::SuperAdmin)] as $actor) {
        expect(fn () => app(RecordProgramAttendance::class)->handle($actor, $program, collect([$registration]), true))
            ->toThrow(AuthorizationException::class);
    }

    expect($registration->fresh()->attended_at)->toBeNull()
        ->and($this->employee->can('view', $program))->toBeFalse()
        ->and(userWithRole(RoleName::ProgramManager)->can('view', $program))->toBeTrue();

    $this->actingAs($this->employee)->get(ProgramResource::getUrl('view', ['record' => $program]))->assertForbidden();
});

it('still takes the program manager to the edit page after creating a program', function () {
    $type = ProgramType::factory()->create();
    $this->actingAs(userWithRole(RoleName::ProgramManager));

    Livewire::test(CreateProgram::class)
        ->fillForm([
            'title' => 'برنامج', 'slug' => 'new-program', 'program_type_id' => $type->id,
            'starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'delivery' => ProgramDelivery::Remote->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(ProgramResource::getUrl('edit', ['record' => Program::where('slug', 'new-program')->sole()]));
});
