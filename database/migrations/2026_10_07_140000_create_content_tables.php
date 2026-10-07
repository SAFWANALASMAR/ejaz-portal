<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المحتوى المؤسسي (البند ٣): الصفحات بمقاطع مرنة، والمبادرات، وأعضاء مجلس الإدارة.
 * المعرّف النصي في الروابط بحروف لاتينية صغيرة وأرقام وشرطات، فلا يظهر الرقم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->string('summary', 300)->nullable();
            $table->jsonb('blocks')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('summary', 300)->nullable();
            $table->string('image')->nullable();
            $table->jsonb('blocks')->nullable();
            $table->jsonb('goals')->nullable();
            $table->jsonb('gallery')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort']);
        });

        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('position', 160);
            $table->string('photo')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_members');
        Schema::dropIfExists('initiatives');
        Schema::dropIfExists('pages');
    }
};
