<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحليل الإحصائيات بالذكاء الاصطناعي (إضافة صفوان، 19 أكتوبر 2026، docs/plans/ai-analysis.md): مفتاح تشغيله المستقل في
 * إعدادات المساعد، وتقاريره بفترتها والأرقام المجمّعة التي بناها الخادم ونص التقرير. ولا شيء فيها عن الأشخاص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_settings', function (Blueprint $table) {
            $table->boolean('analysis_enabled')->default(false)->after('generation_enabled');
        });

        Schema::create('stats_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20);
            $table->jsonb('figures');
            $table->text('report')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_analyses');

        Schema::table('assistant_settings', function (Blueprint $table) {
            $table->dropColumn('analysis_enabled');
        });
    }
};
