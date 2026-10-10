<?php

use App\Actions\Contact\UpdateInquiry;
use App\Enums\InquiryStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Filament\Resources\Inquiries\Pages\ViewInquiry;
use App\Models\Inquiry;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->secretary = userWithRole(RoleName::Secretary);
});

it('opens the messages to the secretary, and to nobody without the requests permissions', function () {
    $inquiry = Inquiry::factory()->create();

    $this->actingAs($this->secretary)->get(InquiryResource::getUrl('index'))->assertOk();
    $this->actingAs($this->secretary)->get(InquiryResource::getUrl('view', ['record' => $inquiry]))->assertOk()->assertSee($inquiry->message);

    foreach ([RoleName::ContentEditor, RoleName::BoardMember] as $role) {
        $this->actingAs(userWithRole($role))->get(InquiryResource::getUrl('index'))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(InquiryResource::getUrl('view', ['record' => $inquiry]))->assertForbidden();
    }

    // تصل من الموقع، فلا إنشاء من اللوحة
    expect(InquiryResource::hasPage('create'))->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('create', Inquiry::class))->toBeFalse();
});

it('processes a message: status, assignee and note, logged without the personal data', function () {
    $inquiry = Inquiry::factory()->create(['name' => 'اسم المرسل', 'email' => 'sender@example.com']);
    $colleague = userWithRole(RoleName::Secretary);
    $this->actingAs($this->secretary);

    Livewire::test(ViewInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->callAction(TestAction::make('process'), data: [
            'status' => InquiryStatus::UnderReview->value,
            'assigned_to' => $colleague->id,
            'internal_note' => 'يُرد عليه بعد مراجعة الجدول',
        ])
        ->assertHasNoFormErrors();

    expect($inquiry->fresh())
        ->status->toBe(InquiryStatus::UnderReview)
        ->assigned_to->toBe($colleague->id)
        ->internal_note->toBe('يُرد عليه بعد مراجعة الجدول');

    $log = Activity::whereMorphedTo('subject', $inquiry)->where('event', 'updated')->sole();

    expect(json_encode($log->attribute_changes))->not->toContain('sender@example.com')->not->toContain('اسم المرسل');

    // والملاحظة حدث بلا قيمتها، فقد تحوي بيانات شخصية
    $logs = Activity::all()->map(fn (Activity $activity) => json_encode([$activity->attribute_changes, $activity->properties], JSON_UNESCAPED_UNICODE))->implode("\n");

    expect(Activity::whereMorphedTo('subject', $inquiry)->where('event', 'inquiry_note_updated')->exists())->toBeTrue()
        ->and($logs)->not->toContain('مراجعة الجدول');
});

it('hides the process action from a viewer, and the assignee from an updater who may not assign', function () {
    $inquiry = Inquiry::factory()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('requests.view');
    $this->actingAs($viewer);
    Livewire::test(ViewInquiry::class, ['record' => $inquiry->getRouteKey()])->assertActionHidden('process');

    $updater = User::factory()->create();
    $updater->givePermissionTo(['requests.view', 'requests.update']);
    $this->actingAs($updater);
    Livewire::test(ViewInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->mountAction('process')
        ->assertFormFieldDisabled('assigned_to')
        ->assertFormFieldEnabled('status');
});

it('checks the types of the status and the assignee in the action itself', function () {
    $inquiry = Inquiry::factory()->create();

    expect(fn () => app(UpdateInquiry::class)->handle($this->secretary, $inquiry, ['assigned_to' => '5abc']))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateInquiry::class)->handle($this->secretary, $inquiry, ['status' => 'archived']))
        ->toThrow(ValidationException::class)
        ->and($inquiry->fresh())->status->toBe(InquiryStatus::Received)->assigned_to->toBeNull();
});

it('checks the internal note in the action itself', function () {
    $inquiry = Inquiry::factory()->create();

    expect(fn () => app(UpdateInquiry::class)->handle($this->secretary, $inquiry, ['internal_note' => str_repeat('م', 2001)]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateInquiry::class)->handle($this->secretary, $inquiry, ['internal_note' => "ملاحظة\x07"]))
        ->toThrow(ValidationException::class)
        ->and($inquiry->fresh()->internal_note)->toBeNull();
});

it('assigns a message only to active staff who may see the requests', function () {
    $inquiry = Inquiry::factory()->create();
    $update = fn (User $assignee) => app(UpdateInquiry::class)->handle($this->secretary, $inquiry, ['assigned_to' => $assignee->id]);

    expect(fn () => $update(userWithRole(RoleName::Member)))->toThrow(ValidationException::class, __('inquiries.validation.assignee'))
        ->and(fn () => $update(userWithRole(RoleName::ContentEditor)))->toThrow(ValidationException::class)
        ->and(fn () => $update(tap(userWithRole(RoleName::Secretary), fn (User $user) => $user->forceFill(['is_active' => false])->save())))->toThrow(ValidationException::class)
        ->and($inquiry->fresh()->assigned_to)->toBeNull();

    $update($admin = userWithRole(RoleName::SuperAdmin));

    expect($inquiry->fresh()->assigned_to)->toBe($admin->id);
});

it('lets someone who may only see the requests read a message but not process or assign it', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('requests.view');
    $inquiry = Inquiry::factory()->create();

    expect(fn () => app(UpdateInquiry::class)->handle($viewer, $inquiry, ['status' => InquiryStatus::Closed->value]))
        ->toThrow(AuthorizationException::class);

    // ومن يحدّث بلا صلاحية الإسناد لا يغيّر المسؤول
    $updater = User::factory()->create();
    $updater->givePermissionTo(['requests.view', 'requests.update']);

    expect(fn () => app(UpdateInquiry::class)->handle($updater, $inquiry, ['assigned_to' => $this->secretary->id]))
        ->toThrow(AuthorizationException::class)
        ->and($inquiry->fresh())->status->toBe(InquiryStatus::Received)->assigned_to->toBeNull();
});

it('soft deletes and restores a message, and never deletes it for good', function () {
    $inquiry = Inquiry::factory()->create();
    $this->actingAs($this->secretary);

    Livewire::test(ListInquiries::class)->callAction(TestAction::make('delete')->table($inquiry));
    expect($inquiry->fresh()->trashed())->toBeTrue();

    Livewire::test(ListInquiries::class)->filterTable('trashed', true)->callAction(TestAction::make('restore')->table($inquiry));

    expect($inquiry->fresh()->trashed())->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('forceDelete', $inquiry))->toBeFalse();
});

it('lists the messages newest first and filters them by status', function () {
    $older = Inquiry::factory()->create(['created_at' => now()->subDay()]);
    $newer = Inquiry::factory()->create(['status' => InquiryStatus::Closed]);
    $this->actingAs($this->secretary);

    Livewire::test(ListInquiries::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->filterTable('status', InquiryStatus::Closed->value)
        ->assertCanSeeTableRecords([$newer])
        ->assertCanNotSeeTableRecords([$older]);
});
