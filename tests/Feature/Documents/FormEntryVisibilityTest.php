<?php

use App\Actions\Documents\ApproveFormEntry;
use App\Actions\Documents\ExportFormEntries;
use App\Actions\Documents\PrintFormEntry;
use App\Actions\Documents\SaveFormEntry;
use App\Enums\RoleName;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Filament\Resources\FormEntries\Pages\ListFormEntries;
use App\Filament\Resources\FormEntries\Pages\ViewFormEntry;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\FormEntriesRelationManager;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| من يرى النماذج المعبأة في اللوحة (قرار صفوان، 14 أكتوبر 2026): من عبّأ المعبأ (created_by) وحده، ومن يملك
| form_entries.view_all (عضو المجلس والمدير التنفيذي)، والمشرف العام. ولا هيكل أقسام.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->owner = userWithRole(RoleName::Accountant);
    $this->colleague = userWithRole(RoleName::FinancialManager);
    $this->template = FormTemplate::factory()->create();
    $this->mine = app(SaveFormEntry::class)->handle($this->owner, new FormEntry, ['answers' => ['name' => 'معبأ المحاسب']], $this->template);
    $this->theirs = app(SaveFormEntry::class)->handle($this->colleague, new FormEntry, ['answers' => ['name' => 'معبأ المدير المالي']], $this->template);
});

it('shows the owner their own entry in the list, and lets them open, edit, print and download it', function () {
    $this->actingAs($this->owner);

    Livewire::test(ListFormEntries::class)
        ->assertCanSeeTableRecords([$this->mine])
        ->assertCanNotSeeTableRecords([$this->theirs])
        ->assertCountTableRecords(1);

    $this->get(FormEntryResource::getUrl('view', ['record' => $this->mine]))->assertOk()->assertSee('معبأ المحاسب');
    $this->get(FormEntryResource::getUrl('edit', ['record' => $this->mine]))->assertOk();
    $this->get(route('documents.entries.print', $this->mine))->assertOk();
    $this->get(route('documents.entries.pdf', $this->mine))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($this->owner->can('view', $this->mine))->toBeTrue()
        ->and($this->owner->can('update', $this->mine))->toBeTrue()
        ->and($this->owner->can('delete', $this->mine))->toBeTrue();
});

it('keeps a staff role with the view permission out of an entry someone else filled, on every path', function () {
    $this->actingAs($this->owner);

    expect($this->owner->can('form_entries.view'))->toBeTrue()
        ->and($this->owner->can('view', $this->theirs))->toBeFalse()
        ->and($this->owner->can('update', $this->theirs))->toBeFalse()
        ->and($this->owner->can('delete', $this->theirs))->toBeFalse()
        ->and($this->owner->can('restore', $this->theirs))->toBeFalse();

    // الصفحتان تحلّان السجل من الاستعلام المحصور، فلا يُكشف وجوده
    $this->get(FormEntryResource::getUrl('view', ['record' => $this->theirs]))->assertNotFound();
    $this->get(FormEntryResource::getUrl('edit', ['record' => $this->theirs]))->assertNotFound();
    $this->get(route('documents.entries.print', $this->theirs))->assertForbidden();
    $this->get(route('documents.entries.pdf', $this->theirs))->assertForbidden();

    expect(fn () => app(PrintFormEntry::class)->handle($this->owner, $this->theirs))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveFormEntry::class)->handle($this->owner, $this->theirs, ['answers' => ['name' => 'تغيير']]))->toThrow(AuthorizationException::class)
        ->and($this->theirs->fresh()->answers['name'])->toBe('معبأ المدير المالي');

    // ملف الشخص في شاشة المستخدم: ما يطّلع عليه وحده
    $person = userWithRole(RoleName::MarketingManager);
    $this->mine->forceFill(['person_id' => $person->id])->save();
    $this->theirs->forceFill(['person_id' => $person->id])->save();

    Livewire::test(FormEntriesRelationManager::class, ['ownerRecord' => $person, 'pageClass' => EditUser::class])
        ->assertCanSeeTableRecords([$this->mine])
        ->assertCanNotSeeTableRecords([$this->theirs]);
});

it('lets the supervising roles and the super admin see and open every entry', function (RoleName $role) {
    $supervisor = userWithRole($role);
    $this->actingAs($supervisor);

    Livewire::test(ListFormEntries::class)
        ->assertCanSeeTableRecords([$this->mine, $this->theirs])
        ->assertCountTableRecords(2);

    Livewire::test(ViewFormEntry::class, ['record' => $this->theirs->uuid])->assertOk()->assertSee('معبأ المدير المالي');
    $this->get(route('documents.entries.print', $this->mine))->assertOk();

    expect($supervisor->can('view', $this->theirs))->toBeTrue();
})->with([
    'board member' => [RoleName::BoardMember],
    'executive director' => [RoleName::ExecutiveDirector],
    'super admin' => [RoleName::TechnicalAdmin],
]);

it('refuses the list and the entries to a role without the permission and to a guest', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $this->actingAs($editor);

    $this->get(FormEntryResource::getUrl('index'))->assertForbidden();
    $this->get(route('documents.entries.print', $this->mine))->assertForbidden();
    expect($editor->can('view', $this->mine))->toBeFalse()
        ->and(FormEntry::query()->visibleInPanel($editor)->count())->toBe(0);

    auth()->logout();
    $this->get(FormEntryResource::getUrl('index'))->assertRedirect();
    $this->get(route('documents.entries.print', $this->mine))->assertRedirect(route('login'));
});

it('exports only the entries the exporter may see', function () {
    // السكرتير يملك التصدير ولا يملك view_all: ما عبّأه وحده
    $secretary = userWithRole(RoleName::Secretary);
    app(SaveFormEntry::class)->handle($secretary, new FormEntry, ['answers' => ['name' => 'معبأ السكرتير']], $this->template);

    $csv = function (User $actor): string {
        $response = app(ExportFormEntries::class)->handle($actor, $this->template);
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    };

    expect($csv($secretary))->toContain('معبأ السكرتير')
        ->not->toContain('معبأ المحاسب')
        ->not->toContain('معبأ المدير المالي');

    expect($csv(userWithRole(RoleName::TechnicalAdmin)))->toContain('معبأ السكرتير')
        ->toContain('معبأ المحاسب')
        ->toContain('معبأ المدير المالي');
});

it('refuses approval to a holder of the approve permission who may not see the entry', function () {
    $this->owner->givePermissionTo('form_entries.approve');

    expect($this->owner->can('approve', $this->theirs))->toBeFalse()
        ->and(fn () => app(ApproveFormEntry::class)->handle($this->owner, $this->theirs))->toThrow(AuthorizationException::class);
});
