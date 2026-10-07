<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التحكيم (المرحلة 2 — البند ٤): محكّمو كل مسابقة، وإسناد المشاركات إليهم بدرجات كل معيار،
 * واعتماد النتائج الذي يقفل الدرجات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_reviewer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('competition_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->jsonb('scores')->nullable();
            $table->decimal('total', 6, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'completed_at']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->timestamp('results_approved_at')->nullable();
            $table->foreignId('results_approved_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('results_approved_by');
            $table->dropColumn('results_approved_at');
        });

        Schema::dropIfExists('submission_reviews');
        Schema::dropIfExists('competition_reviewer');
    }
};
