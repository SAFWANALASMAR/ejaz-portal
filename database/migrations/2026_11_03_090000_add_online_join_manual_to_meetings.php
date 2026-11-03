<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رابط الاجتماع اليدوي (docs/decisions/2026-11-03-meeting-manual-link.md): الرابط نفسه في online_join_url
 * المشفّر، وهذا العلم يميّزه عن رابط تيمز الآلي، فلا تنشئ المزامنة اجتماعاً لاجتماع رابطه يدوي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->boolean('online_join_manual')->default(false)->after('online_join_url');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn('online_join_manual');
        });
    }
};
