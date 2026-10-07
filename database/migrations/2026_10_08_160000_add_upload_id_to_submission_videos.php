<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * معرّف الرفع المجزأ في التخزين السحابي (S3 multipart)، للرفع المباشر من المتصفح.
     */
    public function up(): void
    {
        Schema::table('submission_videos', function (Blueprint $table) {
            $table->string('upload_id', 1024)->nullable()->after('chunk_count');
        });
    }

    public function down(): void
    {
        Schema::table('submission_videos', function (Blueprint $table) {
            $table->dropColumn('upload_id');
        });
    }
};
