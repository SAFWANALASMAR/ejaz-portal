<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف عنصر قائمة تحته عناصر يُرفض في قاعدة البيانات أيضاً، فلا تُحذف الفروع بصمت
 * من خارج لوحة الإدارة، ولا في سباق مع إضافة فرع أثناء الحذف (MenuItemPolicy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('menu_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }
};
