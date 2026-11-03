<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * لا حسابات ولا كلمات مرور هنا. المشرف الأول يُنشأ بالأمر ejaz:create-admin.
     */
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            MenuSeeder::class,
            AboutMenuAnchorsSeeder::class,
            ContentSeeder::class,
            LegacyRedirectsSeeder::class,
            ProgramTypesSeeder::class,
            CompetitionListsSeeder::class,
            JobFilesSeeder::class,
            AssistantGuideSeeder::class,
        ]);
    }
}
