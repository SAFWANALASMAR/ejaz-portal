<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برامج المكتبة (docs/decisions/2026-11-02-reading-program.md): عنصر المكتبة الذي يختاره البرنامج عند إنشائه فلا يُرفع مرتين،
 * ومدة الصوت والمرئي من الخادم (تُقاس من الملف، أو يُدخلها المسؤول للرابط) لا من المتصفح، ووقت إتمام المشارك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('library_item_id')->nullable()->after('initiative_id')->constrained()->restrictOnDelete();
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('page_count_checked_at');
        });

        Schema::table('program_registrations', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('attended_at');
        });
    }

    public function down(): void
    {
        Schema::table('program_registrations', fn (Blueprint $table) => $table->dropColumn('completed_at'));
        Schema::table('library_items', fn (Blueprint $table) => $table->dropColumn('duration_seconds'));
        Schema::table('programs', fn (Blueprint $table) => $table->dropConstrainedForeignId('library_item_id'));
    }
};
