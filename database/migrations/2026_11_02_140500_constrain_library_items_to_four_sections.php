<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مكتبة إعجاز بأقسامها الأربعة (التصور النهائي — البند R9، docs/decisions/2026-11-02-library-four-sections.md): المرئية، والصوتية،
 * و«الكتب والمؤلفات» (books)، و«الأبحاث والدراسات» (research). القيمتان منفصلتان منذ إنشاء الجدول، فلا يُنقل بحث إلى الكتب.
 * وما خرج عن القيم الأربع بعد تسوية المسافات والحروف (قسم جامع «كتب وأبحاث» أو قيمة كُتبت في القاعدة مباشرة) يصير «الكتب والمؤلفات»، ثم قيد في القاعدة
 * يحصر القسم في الأربع، فلا يقرأ التطبيق قيمة لا يعرفها (LibrarySection). والقيم مكتوبة هنا لا من الـ Enum، فلا يتغير الترحيل
 * بتغيّره، وقسم خامس يلزمه ترحيل جديد.
 *
 * والرجوع يرفع القيد وحده: ما حُوِّل كان قيمة لا يقرأها التطبيق، فلا تُعاد.
 */
return new class extends Migration
{
    private const SECTIONS = ['videos', 'audio', 'books', 'research'];

    private const CONSTRAINT = 'library_items_section_check';

    public function up(): void
    {
        // الصفوف المحذوفة حذفاً ناعماً معها، فالقيد على كل الجدول. وقيمة من الأربع بمسافة أو حرف كبير («Research ») تعود
        // إلى قسمها فلا يضيع تصنيفها (مراجعة code-reviewer)، وما بقي خارجها يصير «الكتب والمؤلفات»
        foreach (self::SECTIONS as $section) {
            DB::table('library_items')->where('section', '!=', $section)->whereRaw('lower(trim(section)) = ?', [$section])->update(['section' => $section]);
        }

        DB::table('library_items')->whereNotIn('section', self::SECTIONS)->update(['section' => 'books']);

        $values = implode(', ', array_map(fn (string $value): string => "'{$value}'", self::SECTIONS));

        DB::statement('ALTER TABLE library_items ADD CONSTRAINT '.self::CONSTRAINT." CHECK (section IN ({$values}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE library_items DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
    }
};
