<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إلغاء سحب واحد لكل مسابقة بقرار مشرف عام واحد، وما بعده طلب يعتمده مشرف عام ثانٍ غير الطالب
 * (decisions.md — 14 أكتوبر 2026). الطلب سجل ثابت كسجلي السحب والإلغاء، والإلغاء يحفظ معتمده.
 * ولا تعديل لترحيل الإلغاء المدموج: عمود جديد، وجدول جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_draw_void_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->unique()->constrained('competition_draws');
            $table->string('reason', 500);
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
        });

        Schema::table('competition_draw_voids', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER competition_draw_void_requests_immutable
                BEFORE UPDATE OR DELETE ON competition_draw_void_requests
                FOR EACH ROW EXECUTE FUNCTION competition_draw_records_append_only();

            CREATE TRIGGER competition_draw_void_requests_no_truncate
                BEFORE TRUNCATE ON competition_draw_void_requests
                FOR EACH STATEMENT EXECUTE FUNCTION competition_draw_records_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_draw_void_requests');

        Schema::table('competition_draw_voids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};
