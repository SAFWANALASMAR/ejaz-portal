<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «حفظ النماذج تلقائيًا في ملفات الموظفين أو المشاركين» (requirements/12 البند 10): النموذج لجمهور
 * (موظفون أو مشاركون)، والمعبأ مربوط بصاحبه فيظهر في ملفه (docs/plans/forms-print.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->string('audience', 20)->default('staff');
        });

        Schema::table('form_entries', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('form_template_version_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
