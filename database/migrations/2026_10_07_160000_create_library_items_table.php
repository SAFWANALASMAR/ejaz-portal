<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عناصر المكتبة (البند ٥): مقطع مرئي برابطه، أو ملف صوتي، أو كتاب أو بحث بملف PDF. الملفات في
 * القرص الخاص وتُقدَّم عبر مسار يفحص النشر، والصورة المصغرة على القرص العام. حذف ناعم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('section', 20);
            $table->text('description')->nullable();
            $table->string('author', 150)->nullable();
            $table->date('published_on')->nullable();
            $table->jsonb('tags')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('file')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'section', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_items');
    }
};
