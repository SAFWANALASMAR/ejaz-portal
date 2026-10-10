<?php

namespace Database\Seeders;

use App\Models\ProgramType;
use Illuminate\Database\Seeder;

/**
 * نوعا البرامج في محور «الدورات والبرامج» بنص المدير التنفيذي: «الدورات التدريبية — المجالس العلمية»
 * (requirements/12، القسم 4). يُنشآن مرة إن لم يوجدا، ثم يُداران من اللوحة، فلا يعود تعديل المحرر.
 * والمسابقات بطاقة في المحور برابط صفحتها لا نوع برنامج.
 */
class ProgramTypesSeeder extends Seeder
{
    public const TYPES = ['training-courses' => 'الدورات التدريبية', 'scientific-councils' => 'المجالس العلمية'];

    public function run(): void
    {
        foreach (array_keys(self::TYPES) as $sort => $slug) {
            ProgramType::query()->firstOrCreate(['slug' => $slug], ['name' => self::TYPES[$slug], 'sort' => $sort + 1]);
        }
    }
}
