<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التسجيل باسم المستخدم، والبريد والجوال اختياريان (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026). اسم المستخدم
 * فريد بأحرف صغيرة، وفارغ في الحسابات القائمة فتدخل ببريدها أو جوالها كما كانت. والبريد يبقى فريداً، وPostgreSQL يقبل
 * أكثر من NULL في العمود الفريد.
 *
 * ومن لا بريد موثقاً له ولا جوالاً تعيد له الإدارة كلمة المرور من اللوحة (ResetPasswordByAdmin)، فيُطالَب بتغييرها عند
 * دخوله (password_set_by_admin_at)، ولا يرفع المطالبةَ دخولٌ بكلمة مرور قوية كما يرفع password_upgrade_required_at.
 * والتراجع لا يعيد البريد إلزامياً، لأن حسابات قد تُنشأ بلا بريد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->timestamp('password_set_by_admin_at')->nullable()->after('password_upgrade_required_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'password_set_by_admin_at']);
        });
    }
};
