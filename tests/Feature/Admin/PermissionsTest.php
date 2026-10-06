<?php

use App\Enums\RoleName;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates every module permission once, even when run twice', function () {
    $this->seed(PermissionsSeeder::class);

    $expected = collect(config('permissions.modules'))->sum(fn (array $actions) => count($actions));

    expect(Permission::count())->toBe($expected)
        ->and(Permission::where('name', 'menu.update')->exists())->toBeTrue()
        ->and(Permission::where('name', 'users.assign-roles')->exists())->toBeTrue();
});

it('gives each role only the permissions in the configuration', function () {
    $editor = Role::findByName(RoleName::ContentEditor->value);
    $board = Role::findByName(RoleName::BoardMember->value);
    $member = Role::findByName(RoleName::Member->value);

    expect($editor->hasPermissionTo('menu.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('settings.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('users.view'))->toBeFalse()
        ->and($editor->hasPermissionTo('activity.view'))->toBeFalse()
        ->and($board->hasPermissionTo('menu.view'))->toBeTrue()
        ->and($board->hasPermissionTo('menu.update'))->toBeFalse()
        ->and($board->hasPermissionTo('users.view'))->toBeFalse()
        ->and($member->permissions)->toBeEmpty();
});

it('removes a permission from a role when it leaves the configuration', function () {
    config(['permissions.roles.content_editor' => ['news.*']]);

    $this->seed(PermissionsSeeder::class);

    $editor = Role::findByName(RoleName::ContentEditor->value);
    expect($editor->hasPermissionTo('news.update'))->toBeTrue()
        ->and($editor->hasPermissionTo('menu.update'))->toBeFalse();
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

it('lets the super admin do everything through the gate', function () {
    $admin = userWithRole(RoleName::SuperAdmin);

    expect($admin->can('update', MenuItem::factory()->create()))->toBeTrue()
        ->and($admin->can('users.assign-roles'))->toBeTrue();
});
