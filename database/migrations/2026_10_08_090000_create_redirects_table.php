<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحويلات الروابط القديمة (البند ٨): مسار من الموقع السابق أو رابط تغيّر، إلى مسار في البوابة.
 * المصدر محفوظ بصيغته الموحدة (مفكوك الترميز، بلا شرطة في طرفيه، بحروف صغيرة)، فالبحث مطابقة تامة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source', 500)->unique();
            $table->string('target', 500);
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
