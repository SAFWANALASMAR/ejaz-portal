<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مطوية «مسابقة البراهين العلمية» (docs/plans/student-competition.md، البند 9): نصوص صفحة المسابقة ودليل الدخول من
 * اللوحة، وجدول الجوائز لكل جنس، ونطاق الترتيب، والرابط المختصر. والفرع وصورته لكل مرحلة، والجنس في التسجيل،
 * والرقم المرجعي لكل محاولة، والجنس في سحب الترتيب التراكمي (المرحلة فيه فارغة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->jsonb('page_content')->nullable();
            $table->jsonb('prize_table')->nullable();
            $table->string('ranking_scope', 20)->default('all');
            $table->string('short_code', 12)->nullable()->unique();
        });

        Schema::table('competition_rounds', function (Blueprint $table) {
            $table->string('branch', 200)->nullable();
            $table->string('image_path')->nullable();
        });

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->string('gender', 10)->nullable();
        });

        Schema::table('competition_attempts', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique();
        });

        Schema::table('competition_draws', function (Blueprint $table) {
            $table->string('gender', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competition_draws', fn (Blueprint $table) => $table->dropColumn('gender'));
        Schema::table('competition_attempts', fn (Blueprint $table) => $table->dropColumn('reference'));
        Schema::table('competition_submissions', fn (Blueprint $table) => $table->dropColumn('gender'));
        Schema::table('competition_rounds', fn (Blueprint $table) => $table->dropColumn(['branch', 'image_path']));
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn(['page_content', 'prize_table', 'ranking_scope', 'short_code']));
    }
};
