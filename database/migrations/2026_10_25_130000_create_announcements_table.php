<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعلانات الرئيسية والمركز الإعلامي (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): صورة وعنوان ورابط ومدة عرض
 * بيومي البداية والنهاية، ونوع للتصفية (AnnouncementKind). وإعلان المتجر بلا رابط يفتح المتجر برابطه من الإعدادات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('image');
            $table->string('url', 500)->nullable();
            $table->string('kind', 20)->default('general');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
