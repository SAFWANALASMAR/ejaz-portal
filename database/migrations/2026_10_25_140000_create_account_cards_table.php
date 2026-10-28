<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقات لوحة المستفيد تُدار من اللوحة (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): إضافة بطاقة (عنوان، وأيقونة،
 * ورابط، ولمن تظهر)، وتعديلها، وإخفاؤها، وحذفها، وترتيبها. وتُنشأ هنا بطاقات الأقسام المبنية بأسمائها الحالية وترتيبها
 * (route)، وتبقى كل منها تتبع صلاحية قسمها (AccountNavigation).
 */
return new class extends Migration
{
    /**
     * [المسار، العنوان، الوصف، الأيقونة] بترتيب الرئيسية الحالي (config/account.php — cards).
     */
    private const BUILT_IN = [
        ['account.programs.index', 'programs.nav.programs', 'programs.nav.programs_description', 'academic-cap'],
        ['account.profile', 'account.nav.profile', 'account.nav.profile_description', 'user'],
        ['assembly.index', 'assembly.nav', 'assembly.nav_description', 'user-group'],
        ['account.requests.index', 'services.nav.requests', 'services.nav.requests_description', 'inbox-stack'],
        ['account.competitions', 'competitions.account.nav', 'competitions.account.nav_description', 'trophy'],
        ['account.payments', 'payments.account.nav', 'payments.account.nav_description', 'banknotes'],
    ];

    public function up(): void
    {
        Schema::create('account_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->string('description', 200)->nullable();
            $table->string('icon', 40);
            // قسم مبني باسم مساره، أو رابط تضيفه الإدارة
            $table->string('route', 120)->nullable()->unique();
            $table->string('url', 500)->nullable();
            $table->string('audience', 20)->default('everyone');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [];

        // النص العربي صراحة لا لغة البيئة وقت الترحيل، فلا تُخزَّن بطاقات بلغة أخرى أو بمفاتيحها (مراجعة المشرف المبكرة، ١٣)
        foreach (self::BUILT_IN as $sort => [$route, $title, $description, $icon]) {
            $rows[] = ['title' => __($title, [], 'ar'), 'description' => __($description, [], 'ar'), 'icon' => $icon, 'route' => $route, 'url' => null,
                'audience' => 'everyone', 'sort' => $sort, 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now];
        }

        // لوحة الإدارة لمن يدخلها (صفحة المشرف العام)
        $rows[] = ['title' => __('account.nav.admin', [], 'ar'), 'description' => __('account.nav.admin_description', [], 'ar'), 'icon' => 'cog-6-tooth', 'route' => null,
            'url' => '/admin', 'audience' => 'panel', 'sort' => count($rows), 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now];

        DB::table('account_cards')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_cards');
    }
};
