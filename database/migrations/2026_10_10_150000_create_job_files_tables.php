<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملفات الوظائف (requirements/11): لكل ملف وظيفة مسؤولة ووظائف مشاركة، وفيه مستندات في القرص الخاص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_files', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('responsible_role', 50)->index();
            $table->jsonb('participant_roles');
            $table->boolean('participants_contribute')->default(false);
            $table->string('icon', 30);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('from_matrix')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_file_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('job_file_id')->constrained()->restrictOnDelete();
            $table->string('title', 250);
            $table->text('description')->nullable();
            $table->string('path', 255)->unique();
            $table->string('original_name', 200);
            $table->unsignedBigInteger('size');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['job_file_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_file_documents');
        Schema::dropIfExists('job_files');
    }
};
