<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المساعد الذكي (requirements/10-ai-assistant.md، docs/plans/ai-assistant.md):
 * - الإعدادات سجل واحد، ومفتاح المزود فيه مشفّر (encrypted في النموذج).
 * - الأسئلة الشائعة المعتمدة، ومقالات «دليل استخدام البوابة».
 * - فهرس الاسترجاع: مقاطع المحتوى المنشور بنص مطبَّع (App\Support\Assistant\ArabicText)، وعمود tsvector محسوب
 *   منه بفهرس GIN. والإعداد 'simple' لأن التطبيع والتجريد في PHP، فيطابق السؤالُ المحتوى بالقواعد نفسها.
 * - سجل المحادثات بلا عنوان IP ولا معرّف مستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('generation_enabled')->default(false);
            $table->string('provider', 30)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('base_url', 300)->nullable();
            $table->text('api_key')->nullable();
            $table->decimal('monthly_cap', 10, 2)->nullable();
            $table->decimal('input_price', 10, 4)->nullable();
            $table->decimal('output_price', 10, 4)->nullable();
            $table->text('welcome_text')->nullable();
            $table->jsonb('suggested_questions')->nullable();
            $table->unsignedSmallInteger('log_retention_days')->nullable();
            $table->string('cap_alerted_month', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 300);
            $table->text('answer');
            $table->string('keywords', 500)->nullable();
            $table->string('source_url', 500);
            $table->string('status', 20)->default('draft');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('assistant_guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 200);
            $table->string('keywords', 500)->nullable();
            $table->jsonb('steps');
            $table->string('route_name', 150);
            $table->string('status', 20)->default('draft');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('assistant_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title', 300);
            $table->string('url', 500);
            $table->text('body');
            $table->text('title_norm');
            $table->text('body_norm');
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        DB::statement("ALTER TABLE assistant_chunks ADD COLUMN search tsvector GENERATED ALWAYS AS (setweight(to_tsvector('simple', title_norm), 'A') || setweight(to_tsvector('simple', body_norm), 'B')) STORED");
        DB::statement('CREATE INDEX assistant_chunks_search_index ON assistant_chunks USING GIN (search)');

        Schema::create('assistant_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->jsonb('sources')->nullable();
            $table->string('mode', 20);
            $table->string('rating', 20)->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->text('flag_note')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->string('provider', 30)->nullable();
            $table->string('model', 150)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // التكلفة التقديرية لكل يوم بتوقيت الرياض، للسقف الشهري واليومي. مستقلة عن السجل، فحذفه بمدة الاحتفاظ لا يُنقص
        // ما أُنفق في الشهر (المراجعة الأمنية، م1)
        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_usage');
        Schema::dropIfExists('assistant_logs');
        Schema::dropIfExists('assistant_chunks');
        Schema::dropIfExists('assistant_guides');
        Schema::dropIfExists('assistant_faqs');
        Schema::dropIfExists('assistant_settings');
    }
};
