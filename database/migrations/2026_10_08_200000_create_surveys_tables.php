<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قياس الرضا (المرحلة 2): الاستبيان برابط رمز عشوائي، وأسئلته، وإجاباته. إجابة واحدة لكل صاحب حساب
 * في كل استبيان بقيد فريد، وإجابات غير المسجلين بلا مستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 40)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('audience', 20);
            $table->nullableMorphs('subject');
            $table->boolean('allow_guests')->default(false);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('prompt');
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'sort_order']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('survey_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->jsonb('answers');
            $table->timestamp('submitted_at');

            // إجابة واحدة لكل صاحب حساب، والإجابات بلا حساب لا تتقيد (null مختلفة في PostgreSQL)
            $table->unique(['survey_id', 'user_id']);
            $table->index(['survey_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
