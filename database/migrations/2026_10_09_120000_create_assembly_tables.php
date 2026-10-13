<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الجمعية العمومية (requirements/04): سجل الأعضاء، والاجتماعات بمحضر كل منها، والحضور، وملفات
 * الأعضاء. والملفات في القرص الخاص، ومساراتها في الأعمدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assembly_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->date('joined_on')->nullable();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 200);
            $table->string('type', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location', 300)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_id', 500)->nullable();
            $table->text('online_join_url')->nullable();
            $table->text('agenda')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->unsignedTinyInteger('quorum_percent')->nullable();
            $table->unsignedInteger('members_count')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('minutes_file')->nullable();
            $table->unsignedInteger('minutes_size')->nullable();
            $table->foreignId('minutes_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('minutes_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('minutes_approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['meeting_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('assembly_files', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 200);
            $table->string('category', 30)->index();
            $table->string('audience', 20);
            $table->string('file');
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // من له دور عضو الجمعية العمومية قبل السجل يُسجَّل عضواً فعّالاً، بلا تاريخ عضوية (لا يُخترع)
        $roles = (string) config('permission.table_names.roles');
        $members = DB::table((string) config('permission.table_names.model_has_roles'))
            ->join($roles, $roles.'.id', '=', 'role_id')
            ->where($roles.'.name', 'assembly_member')
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id');

        DB::table('assembly_memberships')->insertOrIgnore($members->map(fn (int $id): array => [
            'user_id' => $id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_files');
        Schema::dropIfExists('meeting_attendances');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('assembly_memberships');
    }
};
