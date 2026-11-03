<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس المفاتيح الأجنبية في معرض الشواهد والإنجازات (docs/decisions/2026-11-03-deploy-readiness.md): PostgreSQL لا يفهرس
 * المفتاح الأجنبي تلقائياً، فحذف مستخدم أو برنامج أو ألبوم أو عنصر أرشيف يمسح الجدول كله ليطبّق nullOnDelete أو cascadeOnDelete.
 * إضافة وحدها: لا عمود ولا بيانات تتغير.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> الجدول ← العمود ← اسم الفهرس */
    private const INDEXES = [
        'evidence_galleries' => [
            'program_id' => 'evidence_galleries_program_id_index',
            'token_issued_by' => 'evidence_galleries_token_issued_by_index',
            'created_by' => 'evidence_galleries_created_by_index',
        ],
        'evidence_gallery_items' => [
            'album_id' => 'evidence_gallery_items_album_id_index',
            'archive_item_id' => 'evidence_gallery_items_archive_item_id_index',
        ],
        'achievements' => [
            'created_by' => 'achievements_created_by_index',
            'updated_by' => 'achievements_updated_by_index',
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $column => $name) {
                    $blueprint->index($column, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
