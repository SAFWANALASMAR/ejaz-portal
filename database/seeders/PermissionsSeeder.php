<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ينشئ الصلاحيات من config/permissions.php ويزامن صلاحيات كل دور معها.
 * آمن للتشغيل أكثر من مرة، والمزامنة تحذف من الدور ما لم يعد في الإعداد.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        /** @var array<string, list<string>> $modules */
        $modules = config('permissions.modules');

        $all = collect($modules)
            ->flatMap(fn (array $actions, string $module) => array_map(fn (string $action) => "{$module}.{$action}", $actions))
            ->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        /** @var array<string, list<string>> $roles */
        $roles = config('permissions.roles');

        foreach ($roles as $roleName => $patterns) {
            $granted = $all->filter(fn (string $permission) => collect($patterns)->contains(fn (string $pattern) => Str::is($pattern, $permission)));

            Role::findOrCreate($roleName, 'web')->syncPermissions($granted->all());
        }

        $registrar->forgetCachedPermissions();
    }
}
