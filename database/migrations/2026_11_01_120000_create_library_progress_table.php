<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): تقدّم كل مستخدم في عنصر من المكتبة، صف واحد لكل مستخدم في كل عنصر.
 * الكتاب بالصفحات التي مر عليها في القارئ (نطاقات مضغوطة «1-12,15») وعددها، والصوت والمقطع بالثواني وآخر موضع. ويبني عليه
 * فرع برامج المكتبة (feature/reading-program) الإتمام والشهادات. بيانات شخصية: لصاحبها وحده، وتُحذف بحذف حسابه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained()->cascadeOnDelete();
            // الصفحات التي رُسمت له في القارئ، نطاقات مرتبة بلا تكرار
            $table->text('pages_seen')->nullable();
            $table->unsignedInteger('pages_count')->default(0);
            $table->unsignedInteger('last_page')->nullable();
            // عدد صفحات الكتاب من ملفه في الخادم (LibraryPageCount)، لا من المتصفح
            $table->unsignedInteger('total_pages')->nullable();
            // ثواني التشغيل الفعلي بالساعة (لا بموضع المقطع)، محدودة بالوقت المنقضي بين بلاغين
            $table->unsignedInteger('seconds_consumed')->default(0);
            $table->unsignedInteger('last_position')->nullable();
            // مدة المقطع كما أبلغ عنها المشغّل، للعرض وحده
            $table->unsignedInteger('duration_seconds')->nullable();
            // الكتاب وحده في هذا الفرع: مر على كل صفحاته بعددها من الخادم
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_reported_at');
            $table->timestamps();

            $table->unique(['user_id', 'library_item_id']);
            $table->index(['user_id', 'last_reported_at']);
            // حذف العنصر نهائياً يحذف تقدّمه، والمجاميع بالعنصر
            $table->index('library_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_progress');
    }
};
