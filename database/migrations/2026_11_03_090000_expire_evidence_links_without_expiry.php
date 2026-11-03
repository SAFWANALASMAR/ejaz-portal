<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * روابط معارض الشواهد الصادرة بلا انتهاء قبل أن يصير الانتهاء إلزامياً (docs/decisions/2026-11-03-security-lows.md):
 * يُضبط انتهاء كل منها بعد سنة من إصداره (أقصى مدة للرابط الجديد)، فلا يُبطل رابط في يد مانح قبل أوانه، وما مضت
 * سنته ينتهي الآن ويُجدَّد من اللوحة. ولا يُحذف شيء: الرمز والسجل وزيارات المعرض باقية.
 * وأمر واحد لا حلقة صفحات، فلا يتخطى صفاً خرج من الشرط بتحديث ما قبله. والرجوع لا يعيد القيمة الفارغة، فلا يُعرف
 * منها بعد الترحيل ما كان فارغاً، ولا يُراد رابط بلا انتهاء.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evidence_galleries')->whereNotNull('token_hash')->whereNull('expires_at')
            ->update(['expires_at' => DB::raw("COALESCE(token_issued_at, created_at, NOW() AT TIME ZONE 'UTC') + INTERVAL '365 days'")]);
    }

    public function down(): void
    {
        //
    }
};
