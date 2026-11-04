<?php

use App\Enums\RoleName;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Document;
use App\Models\Opening;
use App\Models\Program;
use App\Models\User;

// الجداول والنماذج في لوحة الإدارة (الدفعة ٣ من طلب لوحة الإدارة، 4 نوفمبر 2026): أقسام نموذج البرنامج بأيقوناتها،
// وأنماطها في ملف مستقل يستورده ملف السمة. ثم ملاحظات المشرف: عنوانا نموذجي المستخدم والوثيقة، ونص الجدول الفارغ وأيقونته،
// وحقل الرفع العربي في الواجهة العامة. وحالتا الخطأ والتعطيل في الحقول باختبار متصفح (tests/browser/admin-field-states.spec.mjs).

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

it('heads the user and document forms with a titled section and its icon', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create();

    $userForm = $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->get(UserResource::getUrl('edit', ['record' => $user]))
        ->assertOk()
        ->assertSee(__('users.sections.account'))
        ->getContent();

    $documentForm = $this->actingAs(userWithRole(RoleName::ContentEditor))
        ->get(DocumentResource::getUrl('edit', ['record' => $document]))
        ->assertOk()
        ->assertSee(__('governance.sections.document'))
        ->getContent();

    expect(sectionHeadersWithIcon($userForm))->toBeGreaterThanOrEqual(1)
        ->and(sectionHeadersWithIcon($documentForm))->toBeGreaterThanOrEqual(1);
});

it('keeps the document form closed to a user without the permission', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember))
        ->get(DocumentResource::getUrl('create'))
        ->assertForbidden();
});

it('words an empty table in sound Arabic with an empty-box icon, not a cross', function () {
    $html = $this->actingAs(userWithRole(RoleName::ProgramManager))
        ->get(ProgramResource::getUrl('index'))
        ->assertOk()
        ->assertSee(__('filament-tables::table.empty.heading'))
        ->assertDontSee('لا يوجد البرامج')
        ->getContent();

    $emptyState = substr($html, (int) strpos($html, 'fi-ta-empty-state'), 4000);

    expect(__('filament-tables::table.empty.heading'))->toBe('لا توجد سجلات بعد')
        ->and($emptyState)->toContain('m7.875 14.25 1.214 1.942') // inbox-stack
        ->and($emptyState)->not->toContain('M6 18 18 6M6 6l12 12'); // x-mark
});

it('replaces the browser "No file chosen" with Arabic text from lang/ar, keeping the field attributes', function () {
    $member = userWithRole(RoleName::Member);
    $opening = Opening::factory()->create(['slug' => 'data-analyst']);

    $this->actingAs($member)
        ->get(route('services.apply', $opening->slug))
        ->assertOk()
        ->assertSee('data-file-input', false)
        ->assertSee(__('site.file_input.choose'))
        ->assertSee(__('site.file_input.none'))
        ->assertSee('type="file"', false)
        ->assertSee('accept="application/pdf"', false)
        ->assertSee('aria-describedby="field-files-cv-hint"', false);
});

it('keeps the application form behind sign-in', function () {
    $opening = Opening::factory()->create(['slug' => 'data-analyst']);

    $this->get(route('services.apply', $opening->slug))->assertRedirect(route('login'));
});
