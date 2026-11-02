<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وصف قصير للإعلان يظهر بسطرين في شريحته بالرئيسية (ملاحظة صفوان ٣٠، 3 نوفمبر 2026). اختياري: بلا وصف لا سطر له.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('summary', 300)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
