<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رابط مباشر بديلاً عن ملف الوثيقة (ملاحظة المدير التنفيذي، 10 أكتوبر): للوثيقة ملف أو رابط،
 * أحدهما وحده، والقيد في القاعدة يمنع الاثنين معاً وغيابهما معاً
 * (docs/decisions/2026-10-30-document-url.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('file')->nullable()->change();
            $table->string('url', 2048)->nullable()->after('file');
        });

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_file_or_url CHECK ((NULLIF(file, '') IS NULL) <> (NULLIF(url, '') IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_file_or_url');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('url');
            $table->string('file')->nullable(false)->change();
        });
    }
};
