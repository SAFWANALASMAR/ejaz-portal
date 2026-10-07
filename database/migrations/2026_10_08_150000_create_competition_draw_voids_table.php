<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سحب معتمد واحد لكل مسابقة: إلغاء السحب سجل مستقل ثابت (المشرف العام وحده، بسبب)، لا تعديل في سجل
 * السحب. والجدولان يرفضان التفريغ (TRUNCATE) كما يرفضان التعديل والحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_draw_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->unique()->constrained('competition_draws');
            $table->string('reason', 500);
            $table->foreignId('voided_by')->constrained('users');
            $table->timestamp('voided_at');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION competition_draws_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'competition draw records are append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER competition_draw_voids_immutable
                BEFORE UPDATE OR DELETE ON competition_draw_voids
                FOR EACH ROW EXECUTE FUNCTION competition_draws_immutable();

            CREATE TRIGGER competition_draws_no_truncate
                BEFORE TRUNCATE ON competition_draws
                FOR EACH STATEMENT EXECUTE FUNCTION competition_draws_immutable();

            CREATE TRIGGER competition_draw_voids_no_truncate
                BEFORE TRUNCATE ON competition_draw_voids
                FOR EACH STATEMENT EXECUTE FUNCTION competition_draws_immutable();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS competition_draws_no_truncate ON competition_draws');
        Schema::dropIfExists('competition_draw_voids');
    }
};
