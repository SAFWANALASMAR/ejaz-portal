<?php

use App\Enums\RoleName;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\Program;

// الجداول والنماذج في لوحة الإدارة (الدفعة ٣ من طلب لوحة الإدارة، 4 نوفمبر 2026): أقسام نموذج البرنامج بأيقوناتها،
// وأنماطها في ملف مستقل يستورده ملف السمة.

/** عدد رؤوس الأقسام التي تبدأ بأيقونة قبل نصها */
function sectionHeadersWithIcon(string $html): int
{
    $chunks = array_slice(explode('class="fi-section-header"', $html), 1);

    return count(array_filter($chunks, fn (string $chunk): bool => str_contains(strstr($chunk, 'fi-section-header-text-ctn', true) ?: '', '<svg')));
}

it('heads every section of the program form with its icon', function () {
    $program = Program::factory()->create();

    $html = $this->actingAs(userWithRole(RoleName::ProgramManager))
        ->get(ProgramResource::getUrl('edit', ['record' => $program]))
        ->assertOk()
        ->assertSeeInOrder([
            __('programs.admin.sections.basics'),
            __('programs.admin.sections.schedule'),
            __('programs.admin.sections.registration'),
            __('programs.admin.sections.form_fields'),
        ])
        ->getContent();

    expect(sectionHeadersWithIcon($html))->toBeGreaterThanOrEqual(4);
});

it('keeps the programs list closed to a user without the permission', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember))
        ->get(ProgramResource::getUrl('index'))
        ->assertForbidden();
});

it('loads the tables and forms styles from their own file in the admin theme', function () {
    expect(file_get_contents(resource_path('css/filament/admin/theme.css')))->toContain("@import './tables-forms.css';")
        ->and(resource_path('css/filament/admin/tables-forms.css'))->toBeFile();
});
