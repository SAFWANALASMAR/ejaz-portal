<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وثائق الحوكمة «للاطلاع فقط» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢): خيار لكل وثيقة، افتراضه للاطلاع فقط (true)،
 * فتُعرض في قارئ الصفحة وحده بلا تنزيل، حتى تقرر الجمعية فتح التنزيل لوثيقة بعينها من اللوحة. والوثائق القائمة كلها للاطلاع
 * فقط بعد الترحيل (العمود بقيمته الافتراضية، والتحديث صريح لمن عدّل القيمة الافتراضية لاحقاً). ووثيقة الرابط المباشر لا ملف
 * لها، فالخيار لا يمسها (Document::isViewOnly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('is_view_only')->default(true)->after('file_size');
        });

        DB::table('documents')->update(['is_view_only' => true]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('is_view_only');
        });
    }
};
