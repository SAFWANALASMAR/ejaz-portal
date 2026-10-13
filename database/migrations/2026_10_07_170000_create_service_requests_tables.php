<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الطلبات الإلكترونية (requirements/02): الفرص التي تنشرها الجمعية بحقول نموذجها، والطلبات
 * عليها، وسجل حالات كل طلب. المرفقات في القرص الخاص، ومساراتها في عمود files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('title', 200);
            $table->string('slug', 80)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->jsonb('form_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'is_published']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('opening_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->jsonb('answers')->nullable();
            $table->jsonb('files')->nullable();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });

        // طلب واحد لكل مستخدم في كل فرصة، والمحذوف ناعماً لا يمنع طلباً جديداً
        DB::statement('create unique index service_requests_opening_user_unique on service_requests (opening_id, user_id) where deleted_at is null');

        Schema::create('service_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('message')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['service_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_events');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('openings');
    }
};
