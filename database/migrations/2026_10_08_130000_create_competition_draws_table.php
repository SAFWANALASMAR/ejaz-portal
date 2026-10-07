<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * السحب الإلكتروني (المرحلة 2 — البند ٥): سجل ثابت للبذرة وقائمة المشمولين وبصمتها والفائزين
 * والمنفّذ والوقت. وقاعدة البيانات نفسها ترفض تعديله وحذفه بمشغّل، فلا يتجاوزه أي مسار في التطبيق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_draws', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('competition_id')->constrained();
            $table->char('seed', 64);
            $table->jsonb('entrants');
            $table->char('entrants_hash', 64);
            $table->unsignedInteger('entrants_count');
            $table->unsignedInteger('winners_count');
            $table->jsonb('winners');
            $table->foreignId('executed_by')->constrained('users');
            $table->timestamp('executed_at');

            $table->index(['competition_id', 'executed_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION competition_draws_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'competition_draws is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER competition_draws_immutable
                BEFORE UPDATE OR DELETE ON competition_draws
                FOR EACH ROW EXECUTE FUNCTION competition_draws_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_draws');
        DB::unprepared('DROP FUNCTION IF EXISTS competition_draws_immutable()');
    }
};
