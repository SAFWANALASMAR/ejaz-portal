<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المركز الإعلامي (البند ٦): الأخبار، والألبومات، والشركاء. الصور على القرص العام، تُنظَّف
 * قبل نشرها وتُحذف مع السجل (SaveContent وDeleteContent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('summary', 300)->nullable();
            $table->jsonb('blocks')->nullable();
            $table->string('image')->nullable();
            $table->jsonb('gallery')->nullable();
            $table->date('published_on');
            $table->string('category', 60)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'published_on']);
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->jsonb('photos')->nullable();
            $table->date('published_on');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'published_on']);
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('logo')->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('news_articles');
    }
};
