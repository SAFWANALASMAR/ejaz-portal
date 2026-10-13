<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إخفاء البيانات يبحث في سجل العمليات ببصمة معرّف الدخول (AnonymizeUser)، فيُفهرس التعبير
 * نفسه الذي يستعمله الاستعلام، لئلا يمر على السجل كله وهو يكبر بدخول المستخدمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX activity_log_identifier_hash_index ON activity_log ((properties->>'identifier_hash'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS activity_log_identifier_hash_index');
    }
};
