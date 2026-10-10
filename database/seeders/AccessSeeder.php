<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * الأدوار والصلاحيات معاً. تستعمله الاختبارات وDatabaseSeeder.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
        ]);
    }
}
