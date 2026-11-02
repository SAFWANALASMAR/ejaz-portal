<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «إنجازاتنا» (docs/decisions/2026-11-02-achievements.md): تضيفها الإدارة التنفيذية، ويراها المجلس والجمعية العمومية في
 * بوابتهم والزائر في صفحة عامة، المنشور منها وحده. والمرفق اختياري في القرص الخاص باسم عشوائي، ويُطلب بمعرّفه uuid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 200);
            $table->date('achieved_on');
            $table->text('details');
            $table->string('status', 20)->default('draft');
            $table->string('attachment', 255)->nullable()->unique();
            $table->string('attachment_name', 200)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // الخط الزمني للمنشور بالأحدث، وعدّ إنجازات السنة
            $table->index(['status', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
