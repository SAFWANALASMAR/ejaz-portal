<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شهادة الحضور (requirements/03): ملف PDF يُولَّد في الخلفية لمن سُجّل حضوره، في القرص الخاص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_registrations', function (Blueprint $table) {
            $table->string('certificate_path')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_registrations', function (Blueprint $table) {
            $table->dropColumn(['certificate_path', 'certificate_issued_at']);
        });
    }
};
