<?php

use Illuminate\Support\Facades\DB;

it('indexes the foreign keys of the evidence galleries and achievements', function (string $table, string $index, string $column) {
    $definition = DB::scalar(
        'select indexdef from pg_indexes where schemaname = current_schema() and tablename = ? and indexname = ?',
        [$table, $index],
    );

    expect($definition)->toBeString()->toContain('('.$column.')');
})->with([
    ['evidence_galleries', 'evidence_galleries_program_id_index', 'program_id'],
    ['evidence_galleries', 'evidence_galleries_token_issued_by_index', 'token_issued_by'],
    ['evidence_galleries', 'evidence_galleries_created_by_index', 'created_by'],
    ['evidence_gallery_items', 'evidence_gallery_items_album_id_index', 'album_id'],
    ['evidence_gallery_items', 'evidence_gallery_items_archive_item_id_index', 'archive_item_id'],
    ['achievements', 'achievements_created_by_index', 'created_by'],
    ['achievements', 'achievements_updated_by_index', 'updated_by'],
]);
