<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة الاختبار لطالب بعينه (قرار صفوان، 13 نوفمبر 2026 — docs/decisions/2026-11-13-feature-competition-retake.md):
 * المحاولة السابقة تُحفظ هنا كما كانت (archived_attempt) وتُرفع من المحاولات عند بدء الإعادة، فلا تدخل في النتائج والترتيب
 * والتصدير، ويبدأ الطالب محاولة جديدة في نافذة المرحلة أو حتى retake_until إن أُغلقت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_retakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained('competition_rounds');
            $table->foreignId('submission_id')->constrained('competition_submissions');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('granted_by')->constrained('users');
            $table->text('reason');
            $table->timestamp('retake_until')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->json('archived_attempt')->nullable();
            $table->timestamps();

            // البطاقات وقائمة المراحل تبحث بالتسجيل وحده
            $table->index(['submission_id', 'round_id']);
        });
    }

    public function down(): void
    {
        // فيه النسخة الوحيدة من المحاولات المؤرشفة، فلا رجوع وفيه صفوف
        if (Schema::hasTable('competition_retakes') && DB::table('competition_retakes')->exists()) {
            throw new RuntimeException('competition_retakes has rows: archived attempts would be lost.');
        }

        Schema::dropIfExists('competition_retakes');
    }
};
