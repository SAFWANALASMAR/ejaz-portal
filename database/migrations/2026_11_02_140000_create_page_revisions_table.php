<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نسخ صفحات الموقع الثابتة في «محرر الصفحات» (docs/decisions/2026-11-02-page-editor.md): المحتوى كاملاً في كل نسخة (العنوان،
 * والوصف، وصورة الرأس، والمقاطع بإظهارها وترتيبها)، ومن كتبها ومن نشرها ومتى. والمسودة والمنشورة واحدة لكل صفحة بفهرس جزئي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->jsonb('content');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('reverted_from_id')->nullable()->constrained('page_revisions')->nullOnDelete();
            $table->timestamps();

            $table->index(['page_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX page_revisions_one_draft ON page_revisions (page_id) WHERE status = 'draft'");
        DB::statement("CREATE UNIQUE INDEX page_revisions_one_published ON page_revisions (page_id) WHERE status = 'published'");
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
