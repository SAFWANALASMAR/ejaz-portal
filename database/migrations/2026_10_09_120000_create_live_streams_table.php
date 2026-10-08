<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البث المباشر (المرحلة 5): بث بمزوده ورابطه وموعديه، تُحسب حالته من الموعدين. وبعد انتهائه يُربط
 * بعنصر المكتبة المرئية الذي نُقل إليه تسجيله. حذف ناعم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('provider', 20);
            $table->string('url', 500);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_published')->default(false);
            $table->foreignId('library_item_id')->nullable()->constrained();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // صفحة البث: المنشور غير المنقول الذي لم ينتهِ، بترتيب البدء
            $table->index(['is_published', 'archived_at', 'ends_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
