<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الرئيسية بملاحظات المدير التنفيذي: نوع الشريك (شريك أو راعٍ)، والمرئي المختار للرئيسية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('kind', 20)->default('partner')->after('name');
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->boolean('on_home')->default(false)->after('is_published');
            $table->index(['on_home', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropIndex(['on_home', 'is_published']);
            $table->dropColumn('on_home');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
