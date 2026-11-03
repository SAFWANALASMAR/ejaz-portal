<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «منشوراتنا» في المركز الإعلامي (طلب صفوان، 3 نوفمبر 2026): منشورات الجمعية وأحداثها على منصات التواصل، بروابطها الأصلية
 * لا بتضمينها. والرابط لا يتكرر بين المنشورات القائمة (فهرس جزئي بلا المحذوف ناعماً، فيُعاد إدخال منشور حُذف).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('platform', 20);
            $table->string('kind', 20);
            $table->text('body');
            $table->date('posted_on');
            $table->string('url', 500);
            $table->string('image')->nullable();
            // رابط صورة المصدر من الاستيراد: مرجع للمحرر يرفعها بنفسه، لا يُجلب ولا يُعرض للعامة
            $table->string('source_image_url', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'posted_on']);
        });

        DB::statement('CREATE UNIQUE INDEX social_posts_url_unique ON social_posts (url) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
