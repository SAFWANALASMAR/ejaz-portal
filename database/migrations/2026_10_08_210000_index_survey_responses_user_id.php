<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إجابات صاحب الحساب تُقرأ بمعرّفه وحده (تصدير بياناته، وإخفاؤها)، والقيد الفريد يبدأ بالاستبيان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
