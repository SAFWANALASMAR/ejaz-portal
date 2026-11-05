<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عناصر صفحة «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026 — docs/decisions/2026-11-04-store-page.md): بطاقات في الموقع
 * يُدفع ثمنها في متجر الجمعية على سلة. وsalla_product_id معرّف المنتج في سلة للربط الآلي لاحقاً (سلة الضيف ورابط الدفع)، اختياري.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('description', 500)->nullable();
            $table->string('image')->nullable();
            $table->string('category', 20);
            $table->string('salla_url', 500);
            $table->string('salla_product_id', 50)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_items');
    }
};
