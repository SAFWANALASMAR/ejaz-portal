<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مكتبة الوسائط: صور الموقع السابق وأيقوناته بأصولها (القرص الخاص) ونسخها المحسّنة للويب (القرص
 * العام). والنص البديل يكمله المحرر من اللوحة؛ والزخرفية نصها البديل فارغ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('original_name', 255);
            $table->string('original_path', 500);
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('web_path', 500)->nullable();
            $table->unsignedInteger('web_size')->nullable();
            $table->string('alt', 300)->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->boolean('from_template')->default(false);
            $table->string('legacy_url', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
