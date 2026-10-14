<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إلغاء سحب واحد لكل مسابقة بقرار مشرف عام واحد، وما بعده طلب يعتمده مشرف عام ثانٍ غير الطالب أو يرفضه
 * بسبب، ويسقط بعد سبعة أيام بلا قرار (decisions.md — 14 أكتوبر 2026). والإلغاء يحفظ معتمده.
 * ولا تعديل لترحيل الإلغاء المدموج: عمود جديد، وجدول جديد.
 *
 * الطلب لا يُحذف ولا يُفرَّغ، ولا يتغير إلا مرة واحدة: من «معلّق» إلى قراره، بلا مساس بحقوله الأصلية.
 * والقيود تمنع قراراً ناقصاً، أو قراراً من الطالب نفسه، أو طلبين معلّقين للسحب نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_draw_void_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->constrained('competition_draws');
            $table->string('reason', 500);
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->string('decision_reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
        });

        Schema::table('competition_draw_voids', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users');
        });

        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX competition_draw_void_requests_one_pending
                ON competition_draw_void_requests (draw_id) WHERE status = 'pending';

            ALTER TABLE competition_draw_void_requests ADD CONSTRAINT competition_draw_void_requests_decision CHECK (
                (status = 'pending' AND decided_by IS NULL AND decision_reason IS NULL AND decided_at IS NULL)
                OR (status = 'approved' AND decided_by IS NOT NULL AND decided_by <> requested_by AND decision_reason IS NULL AND decided_at IS NOT NULL)
                OR (status = 'rejected' AND decided_by IS NOT NULL AND decided_by <> requested_by AND decision_reason IS NOT NULL AND decided_at IS NOT NULL)
                OR (status = 'lapsed' AND decided_by IS NULL AND decision_reason IS NULL AND decided_at IS NOT NULL)
            );

            CREATE OR REPLACE FUNCTION competition_draw_void_requests_decide_once() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.status = 'pending' AND NEW.status <> 'pending'
                    AND (NEW.id, NEW.draw_id, NEW.reason, NEW.requested_by, NEW.requested_at)
                        IS NOT DISTINCT FROM (OLD.id, OLD.draw_id, OLD.reason, OLD.requested_by, OLD.requested_at) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'competition draw void requests are decided once and never changed';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER competition_draw_void_requests_immutable
                BEFORE UPDATE OR DELETE ON competition_draw_void_requests
                FOR EACH ROW EXECUTE FUNCTION competition_draw_void_requests_decide_once();

            CREATE TRIGGER competition_draw_void_requests_no_truncate
                BEFORE TRUNCATE ON competition_draw_void_requests
                FOR EACH STATEMENT EXECUTE FUNCTION competition_draw_records_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_draw_void_requests');
        DB::unprepared('DROP FUNCTION IF EXISTS competition_draw_void_requests_decide_once()');

        Schema::table('competition_draw_voids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};
