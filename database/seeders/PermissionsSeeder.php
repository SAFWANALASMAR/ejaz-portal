<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ينشئ الصلاحيات من config/permissions.php ويزامن صلاحيات كل دور معها.
 * آمن للتشغيل أكثر من مرة: يحذف الصلاحيات التي خرجت من الإعداد، ويسحب من كل دور
 * ما لم يعد له. ويرفض الإعداد الخاطئ صراحة بدل أن يمر بصمت:
 * دور غير معروف، أو نمط لا يطابق أي صلاحية. والفحص كله قبل التطبيق، والتطبيق
 * في معاملة واحدة، فلا يُطبَّق إعداد خاطئ تطبيقاً جزئياً.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        /** @var array<string, list<string>> $modules */
        $modules = config('permissions.modules');

        /** @var Collection<int, string> $all */
        $all = collect($modules)
            ->flatMap(fn (array $actions, string $module) => array_map(fn (string $action) => "{$module}.{$action}", $actions))
            ->values();

        /** @var array<string, list<string>> $roles */
        $roles = config('permissions.roles');

        $grants = self::grants($all, $roles);

        DB::transaction(function () use ($all, $grants): void {
            foreach ($all as $name) {
                Permission::findOrCreate($name, 'web');
            }

            Permission::query()->whereNotIn('name', $all->all())->delete();

            foreach ($grants as $roleName => $granted) {
                Role::findOrCreate($roleName, 'web')->syncPermissions($granted);
            }
        });

        $registrar->forgetCachedPermissions();
    }

    /**
     * صلاحيات كل دور من أنماطه، بعد فحص الإعداد كله.
     *
     * @param  Collection<int, string>  $all
     * @param  array<string, list<string>>  $roles
     * @return array<string, list<string>>
     */
    private static function grants(Collection $all, array $roles): array
    {
        $grants = [];

        foreach ($roles as $roleName => $patterns) {
            if (RoleName::tryFrom($roleName) === null) {
                throw new InvalidArgumentException("Unknown role in config/permissions.php: {$roleName}");
            }

            foreach ($patterns as $pattern) {
                if (! $all->contains(fn (string $permission) => Str::is($pattern, $permission))) {
                    throw new InvalidArgumentException("Pattern '{$pattern}' for role {$roleName} matches no permission.");
                }
            }

            $grants[$roleName] = $all
                ->filter(fn (string $permission) => collect($patterns)->contains(fn (string $pattern) => Str::is($pattern, $permission)))
                ->values()
                ->all();
        }

        return $grants;
    }
}
