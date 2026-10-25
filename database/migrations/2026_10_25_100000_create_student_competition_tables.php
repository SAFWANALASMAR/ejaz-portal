<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مسابقة الطلاب (docs/plans/student-competition.md): الفئات والمناطق قائمتان من اللوحة، والمراحل بنوافذها،
 * والحقول المخصصة، والنصوص والمقاطع، ومحاولة الطالب في كل مرحلة. وإضافات إلى جداول المسابقات القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->jsonb('grades')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('competition_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('student_mode')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_background')->nullable();
        });

        // الفئات المفتوحة في هذا الإطلاق
        Schema::create('competition_category_competition', function (Blueprint $table) {
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('competition_categories')->restrictOnDelete();
            $table->primary(['competition_id', 'category_id']);
        });

        Schema::create('competition_rounds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('qualified_only')->default(false);
            $table->timestamp('results_published_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'sort_order']);
        });

        Schema::create('competition_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('label', 150);
            $table->string('type', 10);
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['competition_id', 'sort_order']);
        });

        // النص المقروء أو المقطع الذي تجتمع تحته أسئلته
        Schema::create('competition_passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('competition_rounds')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('competition_categories')->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->string('video_url', 255)->nullable();
            $table->timestamps();

            $table->index('round_id');
        });

        Schema::table('competition_questions', function (Blueprint $table) {
            $table->foreignId('round_id')->nullable()->constrained('competition_rounds')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('competition_categories')->restrictOnDelete();
            $table->foreignId('passage_id')->nullable()->constrained('competition_passages')->cascadeOnDelete();

            $table->index(['round_id', 'category_id']);
        });

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('competition_categories')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('competition_regions')->restrictOnDelete();
            $table->string('grade', 60)->nullable();
            // مشفّر في النموذج كرقم الهوية
            $table->text('guardian_phone')->nullable();
            $table->jsonb('extra')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
        });

        Schema::create('competition_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('round_id')->constrained('competition_rounds');
            $table->foreignId('submission_id')->constrained('competition_submissions');
            $table->foreignId('user_id')->constrained();
            $table->string('status', 12)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            // الوحدات بترتيب الطالب: [{passage: id|null, questions: [{id, options: [مفاتيح]}]}]
            $table->jsonb('layout');
            $table->jsonb('answers')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('essay_score', 8, 2)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            // محاولة واحدة لكل تسجيل في كل مرحلة، ولو تزامن طلبان
            $table->unique(['round_id', 'submission_id']);
            $table->index(['round_id', 'status']);
            $table->index(['status', 'deadline_at']);
            $table->index('user_id');
            $table->index('submission_id');
        });

        Schema::table('competition_draws', function (Blueprint $table) {
            $table->foreignId('round_id')->nullable()->constrained('competition_rounds');
            $table->foreignId('category_id')->nullable()->constrained('competition_categories');
            $table->unsignedSmallInteger('places')->nullable();
            $table->decimal('tie_score', 8, 2)->nullable();
        });

        // سحب المتساوين واحد لكل مرحلة وفئة («كل الفئات» صفر)، فلا تغيّره إعادة الضغط
        DB::statement('CREATE UNIQUE INDEX competition_draws_round_category_unique ON competition_draws (round_id, COALESCE(category_id, 0)) WHERE round_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS competition_draws_round_category_unique');

        Schema::table('competition_draws', function (Blueprint $table) {
            $table->dropConstrainedForeignId('round_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['places', 'tie_score']);
        });

        Schema::dropIfExists('competition_attempts');

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['grade', 'guardian_phone', 'extra', 'certificate_path', 'certificate_issued_at']);
        });

        Schema::table('competition_questions', function (Blueprint $table) {
            $table->dropIndex(['round_id', 'category_id']);
            $table->dropConstrainedForeignId('passage_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('round_id');
        });

        Schema::dropIfExists('competition_passages');
        Schema::dropIfExists('competition_fields');
        Schema::dropIfExists('competition_rounds');
        Schema::dropIfExists('competition_category_competition');

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['student_mode', 'certificate_background']);
        });

        Schema::dropIfExists('competition_regions');
        Schema::dropIfExists('competition_categories');
    }
};
