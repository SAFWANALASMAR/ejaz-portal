<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رحلة الاختبار الإلكتروني ورفع الأعمال (docs/plans/competition-exam.md): نوع المسابقة وفيديوها التعريفي
 * وأنواع أعمالها، ووسائط السؤال، وبيانات التسجيل وأوقات المراحل في المشاركة، وجدول الأعمال المرفوعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('exam_mode')->default(false);
            $table->string('intro_video_path')->nullable();
            $table->unsignedInteger('intro_video_seconds')->nullable();
            $table->jsonb('work_types')->nullable();
        });

        Schema::table('competition_questions', function (Blueprint $table) {
            $table->text('context_text')->nullable();
            $table->string('media_type', 10)->nullable();
            $table->string('media_path')->nullable();
        });

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->string('full_name', 150)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('education_stage', 30)->nullable();
            // مشفّر في النموذج، وعدم التكرار ببصمته (HMAC) لأن المشفّر لا يُقارن
            $table->text('national_id')->nullable();
            $table->string('national_id_hash', 64)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('intro_started_at')->nullable();
            $table->timestamp('intro_watched_at')->nullable();
            $table->timestamp('instructions_accepted_at')->nullable();

            // القيم الفارغة لا تتصادم في PostgreSQL، فالقيد على من أدخل رقمه وحده
            $table->unique(['competition_id', 'national_id_hash']);
        });

        Schema::create('submission_works', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->constrained('competition_submissions')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('path')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_works');

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->dropUnique(['competition_id', 'national_id_hash']);
            $table->dropColumn(['full_name', 'contact_email', 'education_stage', 'national_id', 'national_id_hash', 'registered_at', 'intro_started_at', 'intro_watched_at', 'instructions_accepted_at']);
        });

        Schema::table('competition_questions', function (Blueprint $table) {
            $table->dropColumn(['context_text', 'media_type', 'media_path']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['exam_mode', 'intro_video_path', 'intro_video_seconds', 'work_types']);
        });
    }
};
