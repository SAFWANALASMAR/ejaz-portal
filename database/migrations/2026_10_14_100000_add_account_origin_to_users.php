<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ما يحتاجه حذف الحسابات غير الموثقة (PruneUnverifiedAccounts) ليحذف المتروك وحده (مراجعة التكامل، 14 أكتوبر 2026):
 * - self_registered_at: الحساب أنشأه صاحبه بالتسجيل الذاتي، لا مشرف ولا أمر.
 * - first_verified_at: أول توثيق لبريده أو جواله، ولا يُمسح بتغيير أحدهما، فلا يُعد الموثق قديماً غير موثق.
 *
 * والقائم: من له حدث «registered» في سجل العمليات مسجّل ذاتياً، وأول توثيق أقدم التاريخين الحاليين.
 * وما لا يُعرف أصله يبقى بلا تسجيل ذاتي، فلا يُحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('self_registered_at')->nullable();
            $table->timestamp('first_verified_at')->nullable();
        });

        DB::table('users')
            ->whereNotNull('email_verified_at')->orWhereNotNull('phone_verified_at')
            ->update(['first_verified_at' => DB::raw('LEAST(email_verified_at, phone_verified_at)')]);

        DB::table('users')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('activity_log')
                ->where('event', 'registered')
                ->where('subject_type', User::class)
                ->whereColumn('activity_log.subject_id', 'users.id'))
            ->update(['self_registered_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['self_registered_at', 'first_verified_at']);
        });
    }
};
