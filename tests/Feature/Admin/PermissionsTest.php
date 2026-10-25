<?php

use App\Enums\RoleName;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * الصلاحيات المتوقعة لكل دور من الإعداد نفسه، لمقارنة المجموعة كاملة لا عينات منها.
 *
 * @return list<string>
 */
function expectedPermissions(string $role): array
{
    $all = collect(config('permissions.modules'))
        ->flatMap(fn (array $actions, string $module) => array_map(fn (string $action) => "{$module}.{$action}", $actions));

    return $all->filter(fn (string $permission) => collect(config('permissions.roles.'.$role))
        ->contains(fn (string $pattern) => Str::is($pattern, $permission)))
        ->sort()->values()->all();
}

it('creates every module permission once, even when run twice', function () {
    $this->seed(PermissionsSeeder::class);

    $expected = collect(config('permissions.modules'))->sum(fn (array $actions) => count($actions));

    expect(Permission::count())->toBe($expected)
        ->and(Permission::where('name', 'menu.update')->exists())->toBeTrue();
});

it('gives each role exactly the permissions in the configuration', function (RoleName $role) {
    $granted = Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();

    expect($granted)->toBe(expectedPermissions($role->value));
})->with(collect(RoleName::cases())->reject(fn (RoleName $role) => $role === RoleName::TechnicalAdmin)->values()->all());

it('keeps personal data and the activity log away from the content editor and the board', function () {
    $editor = Role::findByName(RoleName::ContentEditor->value);
    $board = Role::findByName(RoleName::BoardMember->value);

    expect($editor->hasPermissionTo('users.view'))->toBeFalse()
        ->and($editor->hasPermissionTo('activity.view'))->toBeFalse()
        ->and($board->hasPermissionTo('users.view'))->toBeFalse()
        ->and($board->hasPermissionTo('menu.update'))->toBeFalse();
});

it('does not make role assignment a grantable permission', function () {
    expect(Permission::where('name', 'like', '%assign%role%')->exists())->toBeFalse();
});

it('removes a permission from a role when it leaves the configuration, and drops stale permissions', function () {
    Permission::findOrCreate('legacy.thing', 'web');
    config(['permissions.roles.content_editor' => ['news.*']]);

    $this->seed(PermissionsSeeder::class);

    $editor = Role::findByName(RoleName::ContentEditor->value);
    expect($editor->hasPermissionTo('news.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('menu.update'))->toBeFalse()
        ->and(Permission::where('name', 'legacy.thing')->exists())->toBeFalse();
});

it('rejects an unknown role or a pattern that matches nothing', function (array $roles) {
    config(['permissions.roles' => $roles]);

    expect(fn () => $this->seed(PermissionsSeeder::class))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown role' => [['content_editr' => ['news.*']]],
    'typo in pattern' => [['content_editor' => ['newz.*']]],
]);

it('applies nothing when any part of the configuration is wrong', function () {
    config(['permissions.roles' => [
        'content_editor' => ['news.*'],
        'board_member' => ['newz.*'],
    ]]);

    expect(fn () => $this->seed(PermissionsSeeder::class))->toThrow(InvalidArgumentException::class)
        ->and(Role::findByName(RoleName::ContentEditor->value)->hasPermissionTo('menu.update'))->toBeTrue();
});

it('grants each role what its work needs, by name', function () {
    $editor = Role::findByName(RoleName::ContentEditor->value);
    $board = Role::findByName(RoleName::BoardMember->value);
    $secretary = Role::findByName(RoleName::Secretary->value);

    expect($editor->hasPermissionTo('menu.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('settings.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('redirects.delete'))->toBeTrue()
        ->and($editor->hasPermissionTo('requests.view'))->toBeFalse()
        ->and($board->hasPermissionTo('news.view'))->toBeTrue()
        ->and($board->hasPermissionTo('news.update'))->toBeFalse()
        ->and($board->hasPermissionTo('settings.update'))->toBeFalse()
        ->and($secretary->hasPermissionTo('requests.assign'))->toBeTrue()
        ->and($secretary->hasPermissionTo('menu.update'))->toBeFalse();
});

it('keeps a denial with a message from a policy, for the super admin too', function () {
    Gate::define('deny-with-message', fn () => Response::deny('ممنوع'));

    expect(Gate::forUser(userWithRole(RoleName::TechnicalAdmin))->inspect('deny-with-message')->denied())->toBeTrue();
});

it('applies the module policy to each action', function () {
    $item = MenuItem::factory()->create();

    $editor = userWithRole(RoleName::ContentEditor);
    $board = userWithRole(RoleName::BoardMember);
    $member = User::factory()->create();

    expect($editor->can('update', $item))->toBeTrue()
        ->and($editor->can('forceDelete', $item))->toBeFalse()
        ->and($board->can('view', $item))->toBeTrue()
        ->and($board->can('update', $item))->toBeFalse()
        ->and($board->can('delete', $item))->toBeFalse()
        ->and($member->can('viewAny', MenuItem::class))->toBeFalse();
});

it('lets the super admin do everything a policy does not explicitly forbid', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $log = Activity::create(['description' => 'test', 'log_name' => 'default']);

    expect($admin->can('update', MenuItem::factory()->create()))->toBeTrue()
        ->and($admin->can('settings.update'))->toBeTrue()
        ->and($admin->can('assignRoles', User::class))->toBeTrue()
        // منع صريح في السياسات يسري على المشرف العام أيضاً
        ->and($admin->can('update', $log))->toBeFalse()
        ->and($admin->can('delete', $log))->toBeFalse()
        ->and($admin->can('deleteAny', Activity::class))->toBeFalse()
        ->and($admin->can('forceDelete', MenuItem::factory()->create()))->toBeFalse()
        ->and($admin->can('delete', $admin))->toBeFalse();
});
