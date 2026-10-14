<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نص رسالة «تواصل معنا» (الموضوع والرسالة) يُحذف عند إخفاء هوية المرسل (قرار صفوان، 14 أكتوبر 2026):
 * النص الحر كثيراً ما يحوي ما يعرّف صاحبه. ويبقى للإحصاء الرقم والنوع والحالة والتاريخ.
 * فارغاً بـ null كبقية ما يُحذف من البيانات الشخصية (ejaz:purge-anonymized-data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('subject', 200)->nullable()->change();
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('inquiries')->whereNull('subject')->update(['subject' => '']);
        DB::table('inquiries')->whereNull('message')->update(['message' => '']);

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('subject', 200)->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
