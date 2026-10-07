<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البرامج والدورات (requirements/03): أنواعها تُدار من اللوحة، والبرنامج بمقاعده وفترة تسجيله،
 * والتسجيل فيه: تسجيل واحد لكل مستخدم، والمقاعد تُحسب بقفل سطر البرنامج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 80)->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiative_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('delivery', 20);
            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->string('audience', 255)->nullable();
            $table->text('conditions')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->boolean('has_waitlist')->default(false);
            $table->jsonb('form_fields')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'starts_at']);
            $table->index('program_type_id');
        });

        Schema::create('program_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->jsonb('answers')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('waitlisted_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'user_id']);
            $table->index(['program_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_registrations');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('program_types');
    }
};
