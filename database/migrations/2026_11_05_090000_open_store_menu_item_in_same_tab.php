<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * عنصر القائمة المخفي «المتجر» (/store) صار صفحة في الموقع لا تحويلاً إلى سلة (docs/decisions/2026-11-04-store-page.md)، فيفتح
 * في التبويب نفسه (قرار المشرف على #179، البند 2). عنصر القائمة الرئيسية وحده (whereNull parent_id — المشرف على طلب الدمج 183)، وما ضبطه المحرر غيره لا يُمس.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->whereNull('parent_id')->where('url', '/store')->update(['opens_in_new_tab' => false]);
    }

    public function down(): void
    {
        DB::table('menu_items')->whereNull('parent_id')->where('url', '/store')->update(['opens_in_new_tab' => true]);
    }
};
