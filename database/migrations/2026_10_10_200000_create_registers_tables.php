<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| السجلات والمستندات ذات الصلاحية، وسجلات المجالس واللجان، والخطة السنوية، وحسابات المنصات
| (requirements/13 — docs/plans/registers.md). لا عمود لكلمة مرور في أي منها.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 40)->index();
            $table->string('title', 250);
            $table->string('issuer', 200)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->date('issued_on');
            // آخر يوم صالح، وnull لما لا ينتهي
            $table->date('expires_on')->nullable()->index();
            $table->unsignedSmallInteger('alert_days');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // تنبيه واحد لكل مستند وعتبة وتاريخ انتهاء: القيد يمنع التكرار ولو تزامن تشغيلان
        Schema::create('register_entry_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_entry_id')->constrained()->cascadeOnDelete();
            $table->string('threshold', 20);
            $table->date('expires_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['register_entry_id', 'threshold', 'expires_on']);
        });

        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('body_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('body', 20);
            $table->foreignId('committee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['body', 'committee_id']);
        });

        DB::statement("alter table body_memberships add constraint body_memberships_committee_check check ((body = 'committee') = (committee_id is not null))");

        Schema::table('meetings', function (Blueprint $table) {
            $table->string('body', 20)->default('general_assembly')->index();
            $table->foreignId('committee_id')->nullable()->constrained()->restrictOnDelete();
        });

        DB::statement("alter table meetings add constraint meetings_committee_check check ((body = 'committee') = (committee_id is not null))");

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title', 300);
            $table->text('decision')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('not_started');
            $table->timestamps();
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('annual_plan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('department', 200)->nullable();
            $table->string('type', 200)->nullable();
            $table->string('title', 300);
            $table->text('objective_breakdown')->nullable();
            $table->decimal('estimated_budget', 14, 2)->nullable();
            $table->text('supporting_entities')->nullable();
            $table->string('target_group', 300)->nullable();
            $table->unsignedInteger('target_beneficiaries')->nullable();
            $table->string('preparation_period', 200)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('approvals')->nullable();
            // البرنامج المنفذ: مربوط ببند واحد، فلا يُعد مرتين
            $table->foreignId('program_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 200);
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 200)->nullable();
            $table->string('login_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_accounts');
        Schema::dropIfExists('annual_plan_items');
        Schema::dropIfExists('meeting_agenda_items');
        DB::statement('alter table meetings drop constraint if exists meetings_committee_check');
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('committee_id');
            $table->dropColumn('body');
        });
        Schema::dropIfExists('body_memberships');
        Schema::dropIfExists('committees');
        Schema::dropIfExists('register_entry_alerts');
        Schema::dropIfExists('register_entries');
    }
};
