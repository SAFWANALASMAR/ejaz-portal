<?php

use App\Enums\RoleName;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\MenuItem;
use App\Policies\ActivityPolicy;
use Spatie\Activitylog\Models\Activity;

it('shows the activity log to the super admin only', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(ActivityResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(ActivityResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(ActivityResource::getUrl('index'))->assertForbidden();
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

    expect(ActivityResource::getPages())->not->toHaveKeys(['create', 'edit'])
        ->and((new ActivityPolicy)->update($admin, $log))->toBeFalse()
        ->and((new ActivityPolicy)->delete($admin, $log))->toBeFalse();
});
