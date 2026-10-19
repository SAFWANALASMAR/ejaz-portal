<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الاجتماع بتيمز (docs/plans/teams-meetings.md):
 * - online_sync_error ووقته: سبب التعذر (OnlineMeetingFailure)، فيظهر للمسؤول مع إعادة المحاولة، ولا يُسقط
 *   حفظ الاجتماع. ويُمسحان عند نجاح المزامنة.
 * - online_organizer: المنظِّم الذي أُنشئ الحدث في تقويمه، فيُحدَّث ويُلغى فيه ولو تغيّر المنظِّم في الإعداد.
 * - online_generation: يزيد كلما زال اجتماع تيمز (إلغاء أو حذف من التقويم)، فيتغير معرّف منع التكرار
 *   (transactionId) للإنشاء التالي وحده.
 * - online_create_pending: طُلب إنشاء الجيل الحالي ولم يُعرف معرّفه (مهلة أو توقف العامل). فإن لم يعد الاجتماع
 *   يحتاجه يُسترد الحدث بالمعرّف نفسه ويُحذف، فلا يبقى رابط حي لاجتماع ملغى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('online_sync_error', 40)->nullable();
            $table->timestamp('online_sync_failed_at')->nullable();
            $table->string('online_organizer', 254)->nullable();
            $table->unsignedSmallInteger('online_generation')->default(0);
            $table->boolean('online_create_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['online_sync_error', 'online_sync_failed_at', 'online_organizer', 'online_generation', 'online_create_pending']);
        });
    }
};
