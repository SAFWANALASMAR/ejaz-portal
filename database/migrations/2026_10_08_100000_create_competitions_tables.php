<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المسابقات (المرحلة 2 — البند ١): المسابقة، ومعايير التقييم بأوزانها، وبنك الأسئلة.
 * الحالة تُحسب من موعدي الفتح والإغلاق ولا تُخزَّن (docs/plans/competitions.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->text('prizes')->nullable();
            $table->jsonb('categories')->nullable();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'opens_at']);
        });

        Schema::create('competition_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('weight');
            $table->unsignedSmallInteger('max_score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['competition_id', 'sort_order']);
        });

        Schema::create('competition_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('prompt');
            $table->jsonb('options')->nullable();
            $table->string('correct_option', 4)->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('max_video_seconds')->nullable();
            $table->unsignedInteger('max_video_mb')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['competition_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_questions');
        Schema::dropIfExists('competition_criteria');
        Schema::dropIfExists('competitions');
    }
};
