<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحية competition.view_national_id بلا أثر منذ صار كشف رقم الهوية لمن يملك التصدير الكامل وحده (قرار صفوان، 27
 * أكتوبر 2026)، فتُحذف من القاعدة كما حُذفت من config/permissions.php، فلا يظنها قارئ الصلاحيات تكشف الرقم (المراجعة
 * المستقلة على v0.14.0). وربطها بالأدوار والمستخدمين يُحذف معها (القيد بالحذف المتتابع في جداول spatie).
 */
return new class extends Migration
{
    private const NAME = 'competition.view_national_id';

    public function up(): void
    {
        DB::table('permissions')->where('name', self::NAME)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * يعيد الصلاحية بلا ربطها القديم بالأدوار والمستخدمين، لأنها بلا أثر أصلاً. ومديرا النظام ينالانها بـ '*' من
     * PermissionsSeeder إن أُعيدت إلى الإعداد.
     */
    public function down(): void
    {
        DB::table('permissions')->insertOrIgnore(['name' => self::NAME, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
