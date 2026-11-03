<?php

namespace Database\Seeders;

use App\Models\ProgramType;
use Illuminate\Database\Seeder;

/**
 * نوعا البرامج في محور «الدورات والبرامج» بنص المدير التنفيذي: «الدورات التدريبية — المجالس العلمية»
 * (requirements/12، القسم 4). يُنشآن إن لم يوجدا، ثم يُداران من اللوحة، فلا يعود تعديل الاسم.
 * والنوع المحذوف يعود عند إعادة البذر عمداً (خطوة النشر الأولى وحدها).
 * والمسابقات بطاقة في المحور برابط صفحتها لا نوع برنامج.
 */
class ProgramTypesSeeder extends Seeder
{
    // وأنواع برامج المكتبة الثلاثة (قرار صفوان، 31 أكتوبر 19:33 — LibraryProgramKind)، فتصير تبويبات «الدورات والبرامج» ستة من البيانات
    public const TYPES = [
        'training-courses' => 'الدورات التدريبية',
        'scientific-councils' => 'المجالس العلمية',
        'book-reading' => 'قراءة الكتب',
        'audio-listening' => 'الاستماع',
        'video-watching' => 'المشاهدة',
    ];

    public function run(): void
    {
        foreach (array_keys(self::TYPES) as $sort => $slug) {
            ProgramType::query()->firstOrCreate(['slug' => $slug], ['name' => self::TYPES[$slug], 'sort' => $sort + 1]);
        }
    }
}
