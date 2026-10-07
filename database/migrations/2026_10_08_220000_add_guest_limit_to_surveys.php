<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سقف إجمالي لإجابات غير المسجلين على كل استبيان يُضبط من اللوحة، وعدّادها يُزاد مع كل إجابة ضيف
 * على صف الاستبيان المقفول، فلا يُحسب بعدّ الإجابات (مراجعة #5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->unsignedInteger('guest_limit')->nullable()->after('allow_guests');
            $table->unsignedInteger('guest_responses')->default(0)->after('guest_limit');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['guest_limit', 'guest_responses']);
        });
    }
};
