<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * معرض الشواهد برابط سري (قرار صفوان، 30 أكتوبر 2026): صفحة تجمع صوراً وملفات موجودة لمانح أو مسؤول بلا حساب.
 * لا يُخزَّن ملف ولا صورة هنا: البنود إشارات إلى ألبوم في مكتبة الصور أو عنصر في الأرشيف، ومعها ما اختير منه.
 * والرمز لا يُحفظ، بل بصمته (sha256)، وسجل الفتح بالوقت والشبكة المقنّعة وحدهما.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_galleries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_issued_at')->nullable();
            // من أصدر الرابط: لا يُعرض منه إلا ما يطّلع عليه هو الآن، وتعطيل حسابه يوقف الرابط
            $table->foreignId('token_issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('evidence_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_gallery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('album_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('archive_item_id')->nullable()->constrained()->cascadeOnDelete();
            // ما اختير من صور الألبوم أو ملفات العنصر، بمساراتها. وnull: كلها
            $table->jsonb('paths')->nullable();
            $table->timestamps();

            $table->index(['evidence_gallery_id', 'position']);
        });

        // البند من مصدر واحد: ألبوم أو عنصر أرشيف
        DB::statement('ALTER TABLE evidence_gallery_items ADD CONSTRAINT evidence_gallery_items_one_source CHECK ((album_id IS NULL) <> (archive_item_id IS NULL))');

        Schema::create('evidence_gallery_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_gallery_id')->constrained()->cascadeOnDelete();
            // الشبكة لا العنوان: /24 في IPv4 و/48 في IPv6 (NetworkPrefix::masked)
            $table->string('network', 64);
            $table->timestamp('visited_at');

            $table->index(['evidence_gallery_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_gallery_visits');
        Schema::dropIfExists('evidence_gallery_items');
        Schema::dropIfExists('evidence_galleries');
    }
};
