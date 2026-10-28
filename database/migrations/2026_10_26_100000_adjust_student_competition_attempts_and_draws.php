<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مراجعة المشرف على مسابقة الطلاب (docs/reviews/2026-10-25-المسابقة.md): عدد أسئلة كل محاولة عند بدئها، وفهرس
 * المحاولات بالتسجيل («مشاركاتي» والشهادات)، وسحب المتساوين يُعاد بعد إلغاء مسجّل بسببه، فيحل قفل صف المرحلة في
 * RunTieDraw محل الفهرس الفريد الذي كان يشمل السحب الملغى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->index('submission_id');
        });

        DB::statement('DROP INDEX IF EXISTS competition_draws_round_category_unique');
        DB::statement('CREATE INDEX competition_draws_round_category_index ON competition_draws (round_id, category_id) WHERE round_id IS NOT NULL');
    }

    public function down(): void
    {
        // الفهرس الفريد لا يقبل سحباً ملغى وإعادته للمرحلة والفئة نفسيهما، فالتراجع يتوقف قبل أي تغيير إن وُجدا
        // (مراجعة المشرف)، ولا يُحذف سجل سحب لأجله: يُعاد يدوياً بعد قرار في السحوب المكررة
        $repeated = DB::table('competition_draws')->select('round_id')->whereNotNull('round_id')
            ->groupBy('round_id', DB::raw('COALESCE(category_id, 0)'))->havingRaw('count(*) > 1')->exists();

        if ($repeated) {
            throw new RuntimeException('competition_draws has a voided and a repeated round draw; the unique index cannot be restored.');
        }

        DB::statement('DROP INDEX IF EXISTS competition_draws_round_category_index');
        DB::statement('CREATE UNIQUE INDEX competition_draws_round_category_unique ON competition_draws (round_id, COALESCE(category_id, 0)) WHERE round_id IS NOT NULL');

        Schema::table('competition_attempts', function (Blueprint $table) {
            $table->dropIndex(['submission_id']);
            $table->dropColumn('question_count');
        });
    }
};
