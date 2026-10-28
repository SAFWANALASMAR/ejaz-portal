<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أسماء المستخدمين للحسابات المجهّلة لا تُحرَّر، فلا ينتحل أحد صاحبها السابق (مراجعة المشرف المبكرة لملاحظات المدير
 * التنفيذي، ١٧). ويُحفظ بصمتها (HMAC بمفتاح التطبيق) لا نصها، فلا يبقى بعد الإخفاء ما يدل عليه (Username::retire).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_usernames', function (Blueprint $table) {
            $table->string('hash', 64)->primary();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_usernames');
    }
};
