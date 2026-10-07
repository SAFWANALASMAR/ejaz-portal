<?php

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\MenuItem;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('shows the activity log to the super admin only', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(ActivityResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(ActivityResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(ActivityResource::getUrl('index'))->assertForbidden();

    $log = Activity::create(['description' => 'test', 'log_name' => 'default']);
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(ActivityResource::getUrl('view', ['record' => $log]))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(ActivityResource::getUrl('view', ['record' => $log]))->assertForbidden();
});

it('records who changed what with the values before and after', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $this->actingAs($editor);

    $item = MenuItem::factory()->create(['label' => 'قبل']);
    $item->update(['label' => 'بعد']);

    $log = Activity::where('subject_type', MenuItem::class)->where('event', 'updated')->firstOrFail();

    expect($log->causer_id)->toBe($editor->id)
        ->and($log->attribute_changes['old']['label'])->toBe('قبل')
        ->and($log->attribute_changes['attributes']['label'])->toBe('بعد');

    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get(ActivityResource::getUrl('view', ['record' => $log]))
        ->assertOk()
        ->assertSee('بعد');
});

it('offers no way to change or delete the log', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $log = Activity::create(['description' => 'test', 'log_name' => 'default']);

    // عبر البوابة نفسها لا السياسة مباشرة، حتى يُختبر منح المشرف العام فعلاً
    expect(ActivityResource::getPages())->not->toHaveKeys(['create', 'edit'])
        ->and($admin->can('update', $log))->toBeFalse()
        ->and($admin->can('delete', $log))->toBeFalse()
        ->and($admin->can('deleteAny', Activity::class))->toBeFalse()
        ->and($admin->can('view', $log))->toBeTrue();
});

it('names events and affected records in Arabic', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $member = User::factory()->create();
    activity('users')->causedBy($admin)->performedOn($member)
        ->event(ActivityEvent::RolesChanged->value)->log(ActivityEvent::RolesChanged->value);
    $log = Activity::where('event', ActivityEvent::RolesChanged->value)->sole();

    $this->actingAs($admin);

    Livewire::test(ListActivities::class)
        ->assertTableColumnFormattedStateSet('event', __('activity.events.roles_changed'), $log)
        ->assertTableColumnFormattedStateSet('subject_type', __('activity.subjects.User').' #'.$member->id, $log)
        ->filterTable('event', ActivityEvent::RolesChanged->value)
        ->assertCanSeeTableRecords([$log]);
});

it('has an Arabic label for every event', function (ActivityEvent $event) {
    expect(__('activity.events.'.$event->value))->not->toBe('activity.events.'.$event->value);
})->with(ActivityEvent::cases());
