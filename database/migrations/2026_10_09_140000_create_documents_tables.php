<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * النماذج الإلكترونية بإصداراتها ومعبآتها، والأرشيف والشواهد بتصنيفاتها وعدّاد رقم القيد
 * (requirements/05). والملفات في القرص الخاص، ومساراتها في الأعمدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('fields');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['form_template_id', 'version']);
        });

        Schema::table('form_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('form_template_versions')->nullOnDelete();
        });

        Schema::create('form_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('form_template_version_id')->constrained()->restrictOnDelete();
            $table->jsonb('answers')->nullable();
            $table->jsonb('files')->nullable();
            $table->jsonb('signatures')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('archive_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->jsonb('roles');
            $table->timestamps();
        });

        Schema::create('archive_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 20)->index();
            $table->foreignId('archive_category_id')->constrained()->restrictOnDelete();
            $table->string('title', 250);
            $table->string('entity', 200)->nullable();
            $table->date('dated_on');
            $table->unsignedSmallInteger('year')->index();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('registration_number')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('files')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // رقم القيد لا يتكرر في النوع والسنة، ولو تزامن الإدخال (archive_sequences)
            $table->unique(['type', 'year', 'registration_number']);
        });

        Schema::create('archive_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next')->default(1);

            $table->unique(['type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_sequences');
        Schema::dropIfExists('archive_items');
        Schema::dropIfExists('archive_categories');
        Schema::dropIfExists('form_entries');
        Schema::table('form_templates', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('form_template_versions');
        Schema::dropIfExists('form_templates');
    }
};
