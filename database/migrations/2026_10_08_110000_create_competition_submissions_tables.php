<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التقديم في المسابقات (المرحلة 2 — البند ٢): مشاركة واحدة لكل متقدم في كل مسابقة بقيد فريد،
 * ومقاطع الفيديو المرفوعة مجزأة لأسئلتها. ودرجتا التصحيح الآلي والنهائية للبندين ٣ و٤.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('competition_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 20)->default('draft');
            $table->string('category', 60)->nullable();
            $table->string('region', 30)->nullable();
            $table->jsonb('answers')->nullable();
            $table->string('reference', 12)->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('auto_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // تقديم واحد لكل متقدم في كل مسابقة، ولو تزامن طلبان (requirements/06)
            $table->unique(['competition_id', 'user_id']);
            $table->index(['competition_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('submission_videos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->constrained('competition_submissions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('competition_questions')->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunk_count');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('path')->nullable();
            $table->string('rejection_reason', 30)->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_videos');
        Schema::dropIfExists('competition_submissions');
    }
};
