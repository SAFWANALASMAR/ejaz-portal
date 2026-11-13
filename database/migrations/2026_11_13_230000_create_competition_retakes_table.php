<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة الاختبار لطالب بعينه (قرار صفوان، 13 نوفمبر 2026 — docs/decisions/2026-11-13-feature-competition-retake.md):
 * المحاولة السابقة تُحفظ هنا كما كانت (archived_attempt) وتُرفع من المحاولات، فلا تدخل في النتائج والترتيب والتصدير،
 * ويبدأ الطالب محاولة جديدة في نافذة المرحلة أو حتى retake_until إن أُغلقت.
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
            $table->json('archived_attempt');
            $table->timestamps();

            $table->index(['round_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_retakes');
    }
};
