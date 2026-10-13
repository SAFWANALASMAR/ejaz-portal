<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثائق الحوكمة (البند ٤): الملف PDF في القرص الخاص، ويُقدَّم عبر مسار يفحص النشر.
 * حذف ناعم، لأنها من السجلات المهمة (docs/architecture.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('category', 40);
            $table->date('issued_at')->nullable();
            $table->string('version', 40)->nullable();
            $table->string('file');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'category', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
