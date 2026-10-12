<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس الترتيب والربط التي غابت عن الجداول الكبيرة (docs/reviews/2026-10-13-performance.md):
 * - activity_log.created_at: الحذف اليومي (activitylog:clean) يبحث بالتاريخ في أكبر جدول.
 * - users وpayments وinquiries بـ created_at: شاشاتها في اللوحة مرتبة بالأحدث بلا تصفية،
 *   وفهارس الحالة أو المستخدم القائمة تبدأ بعمود آخر فلا تخدم هذا الترتيب. وعدّ الحسابات الجديدة
 *   في الإحصائيات بالتاريخ.
 * - competition_submissions (competition_id, submitted_at): مشاركات المسابقة في اللوحة بالأحدث
 *   إرسالاً، وقد تبلغ عشرات الآلاف في المسابقة الواحدة.
 * - archive_items (archive_category_id, dated_on): الأرشيف مقصور على تصنيفات الحساب ومرتب
 *   بالتاريخ. وprogram_id مفتاح ربط يُصفّى به.
 * - form_entries.form_template_version_id: مفتاح ربط يُبحث به عند التصدير وقفل النموذج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
        });

        foreach (['users', 'payments', 'inquiries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->index(['competition_id', 'submitted_at']);
        });

        Schema::table('archive_items', function (Blueprint $table) {
            $table->index(['archive_category_id', 'dated_on']);
            $table->index('program_id');
        });

        Schema::table('form_entries', function (Blueprint $table) {
            $table->index('form_template_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_entries', function (Blueprint $table) {
            $table->dropIndex(['form_template_version_id']);
        });

        Schema::table('archive_items', function (Blueprint $table) {
            $table->dropIndex(['program_id']);
            $table->dropIndex(['archive_category_id', 'dated_on']);
        });

        Schema::table('competition_submissions', function (Blueprint $table) {
            $table->dropIndex(['competition_id', 'submitted_at']);
        });

        foreach (['inquiries', 'payments', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
