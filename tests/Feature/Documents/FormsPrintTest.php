<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Documents\ExportFormEntries;
use App\Actions\Documents\PrintFormEntry;
use App\Actions\Documents\SaveFormEntry;
use App\Actions\Documents\SaveFormTemplate;
use App\Enums\FormAudience;
use App\Enums\RoleName;
use App\Filament\Resources\FormEntries\Pages\CreateFormEntry;
use App\Filament\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\FormEntriesRelationManager;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Support\FormEntryLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| النماذج بشكل الورق (requirements/12 البند 10): منشئ بعناوين أقسام ونصوص ثابتة وجداول وصفوف من
| عمودين، وطباعة كورقة وورد على A4 بترويسة الجمعية، وحفظ المعبأ في ملف صاحبه، وتصدير المعبأ.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

/**
 * نموذج تجريبي بالتخطيط الجديد (ليس نموذجاً ورقياً للجمعية: لم تصل بعد).
 *
 * @return list<array<string, mixed>>
 */
function paperLikeFields(): array
{
    return [
        ['key' => 'part_one', 'label' => 'القسم الأول', 'type' => 'heading'],
        ['key' => 'intro', 'label' => 'تعليمات', 'type' => 'paragraph', 'text' => 'فقرة ثابتة تجريبية'],
        ['key' => 'name', 'label' => 'الاسم', 'type' => 'text', 'required' => true, 'width' => 'half'],
        ['key' => 'day', 'label' => 'التاريخ', 'type' => 'date', 'width' => 'half'],
        ['key' => 'items', 'label' => 'البنود', 'type' => 'table', 'columns' => ['البند', 'العدد']],
        ['key' => 'sign', 'label' => 'التوقيع', 'type' => 'signature', 'width' => 'half'],
    ];
}

function paperLikeTemplate(User $secretary, FormAudience $audience = FormAudience::Staff): FormTemplate
{
    return app(SaveFormTemplate::class)->handle($secretary, new FormTemplate, [
        'title' => 'نموذج تجريبي بشكل الورق',
        'audience' => $audience->value,
        'is_active' => true,
        'fields' => paperLikeFields(),
    ]);
}

// ── المنشئ ──

it('builds a form with section headings, fixed text, tables and two-column rows', function () {
    $template = paperLikeTemplate($this->secretary);
    $fields = collect($template->currentVersion->fields)->keyBy('key');

    expect($fields['part_one'])->toMatchArray(['type' => 'heading', 'required' => false])
        ->and($fields['intro']['text'])->toBe('فقرة ثابتة تجريبية')
        ->and($fields['name']['width'])->toBe('half')
        ->and($fields['items']['columns'])->toBe(['البند', 'العدد'])
        ->and($template->audience)->toBe(FormAudience::Staff);

    // الحقل بالعرض الكامل لا يحمل العرض، فلا ينشئ حفظ نموذج قديم إصداراً جديداً
    expect($fields['items'])->not->toHaveKey('width');
});

it('refuses broken layout definitions', function (array $field) {
    expect(fn () => app(SaveFormTemplate::class)->handle($this->secretary, new FormTemplate, ['title' => 'نموذج', 'fields' => [$field]]))
        ->toThrow(ValidationException::class);
})->with([
    'table without columns' => [['key' => 't', 'label' => 'جدول', 'type' => 'table', 'columns' => []]],
    'table with seven columns' => [['key' => 't', 'label' => 'جدول', 'type' => 'table', 'columns' => ['1', '2', '3', '4', '5', '6', '7']]],
    'paragraph without text' => [['key' => 'p', 'label' => 'فقرة', 'type' => 'paragraph']],
    'unknown width' => [['key' => 'a', 'label' => 'أ', 'type' => 'text', 'width' => 'third']],
]);

it('refuses an unknown audience', function () {
    expect(fn () => app(SaveFormTemplate::class)->handle($this->secretary, new FormTemplate, ['title' => 'نموذج', 'audience' => 'public', 'fields' => paperLikeFields()]))
        ->toThrow(ValidationException::class);
});

it('keeps the audience once a form has entries', function () {
    $template = paperLikeTemplate($this->secretary);
    $data = ['title' => $template->title, 'audience' => FormAudience::Participants->value, 'fields' => paperLikeFields()];

    // قبل أول معبأ يتغير
    app(SaveFormTemplate::class)->handle($this->secretary, $template, $data);
    expect($template->fresh()->audience)->toBe(FormAudience::Participants);

    FormEntry::factory()->create(['form_template_version_id' => $template->current_version_id]);

    expect(fn () => app(SaveFormTemplate::class)->handle($this->secretary, $template->fresh(), [...$data, 'audience' => FormAudience::Staff->value]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('audience'));
});

it('builds the new layout from the panel', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateFormTemplate::class)
        ->fillForm([
            'title' => 'نموذج من اللوحة',
            'audience' => FormAudience::Participants->value,
            'is_active' => true,
            'fields' => [
                ['label' => 'بيانات المشارك', 'key' => 'part', 'type' => 'heading'],
                ['label' => 'الاسم', 'key' => 'name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                ['label' => 'الدورات', 'key' => 'courses', 'type' => 'table', 'columns' => ['الدورة', 'السنة']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = FormTemplate::sole();

    expect($template->audience)->toBe(FormAudience::Participants)
        ->and($template->currentVersion->fields[2]['columns'])->toBe(['الدورة', 'السنة']);
});

// ── التعبئة ──

it('takes table rows by their columns and gives layout no answers', function () {
    $template = paperLikeTemplate($this->secretary);

    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => [
        'name' => 'اسم',
        'part_one' => 'لا يُحفظ',
        'items' => [['c0' => 'أقلام', 'c1' => '3'], ['c0' => '', 'c1' => ''], ['c0' => 'دفاتر', 'c1' => '']],
    ]], $template);

    expect($entry->answers)->toBe(['name' => 'اسم', 'items' => [['c0' => 'أقلام', 'c1' => '3'], ['c0' => 'دفاتر', 'c1' => '']]]);
});

it('refuses too many table rows or unknown columns', function (array $rows) {
    $template = paperLikeTemplate($this->secretary);

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم', 'items' => $rows]], $template))
        ->toThrow(ValidationException::class);
})->with([
    'too many rows' => [array_fill(0, 31, ['c0' => 'بند'])],
    'unknown column' => [[['c0' => 'بند', 'c9' => 'زائد']]],
]);

it('fills a table and links the person from the panel', function () {
    $template = paperLikeTemplate($this->secretary);
    $employee = userWithRole(RoleName::Secretary);
    $this->actingAs($this->secretary);

    Livewire::test(CreateFormEntry::class)
        ->fillForm(['form_template_id' => $template->id])
        ->fillForm(['person_id' => $employee->id, 'answers' => ['name' => 'اسم', 'items' => [['c0' => 'بند', 'c1' => '2']]]])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = FormEntry::sole();

    expect($entry->person_id)->toBe($employee->id)
        ->and($entry->answers['items'])->toBe([['c0' => 'بند', 'c1' => '2']]);
});

// ── ملف الموظف أو المشارك ──

it('links an entry to a person of the form audience, and refuses the wrong kind of person', function () {
    $employee = userWithRole(RoleName::Secretary);
    $participant = User::factory()->create();

    $staffForm = paperLikeTemplate($this->secretary);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $employee->id, 'answers' => ['name' => 'اسم']], $staffForm);
    expect($entry->person->is($employee))->toBeTrue();

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $participant->id, 'answers' => ['name' => 'اسم']], $staffForm))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('person_id'));

    $participantForm = paperLikeTemplate($this->secretary, FormAudience::Participants);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $participant->id, 'answers' => ['name' => 'اسم']], $participantForm);
    expect($entry->person_id)->toBe($participant->id);

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $employee->id, 'answers' => ['name' => 'اسم']], $participantForm))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => 999999, 'answers' => ['name' => 'اسم']], $participantForm))
        ->toThrow(ValidationException::class);
});

it('shows the entries saved in a person file on their admin page', function () {
    $employee = userWithRole(RoleName::Secretary);
    $mine = FormEntry::factory()->create(['person_id' => $employee->id]);
    $other = FormEntry::factory()->create(['person_id' => User::factory()->create()->id]);

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(FormEntriesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditUser::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);

    expect(FormEntriesRelationManager::canViewForRecord($employee, EditUser::class))->toBeTrue();

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    expect(FormEntriesRelationManager::canViewForRecord($employee, EditUser::class))->toBeFalse();
});

it('shows a participant the approved entries in their file, and nothing else', function () {
    $participant = User::factory()->create();
    $form = paperLikeTemplate($this->secretary, FormAudience::Participants);
    $version = $form->current_version_id;

    $approved = FormEntry::factory()->approved()->create(['form_template_version_id' => $version, 'person_id' => $participant->id]);
    $draft = FormEntry::factory()->create(['form_template_version_id' => $version, 'person_id' => $participant->id]);
    $others = FormEntry::factory()->approved()->create(['form_template_version_id' => $version, 'person_id' => User::factory()->create()->id]);
    $staff = FormEntry::factory()->approved()->create(['person_id' => $participant->id]);

    $this->actingAs($participant)->get(route('account.forms.index'))
        ->assertOk()
        ->assertSee($approved->reference)
        ->assertDontSee($draft->reference)
        ->assertDontSee($others->reference)
        ->assertDontSee($staff->reference);

    $this->get(route('account.forms.show', $approved))->assertOk()->assertSee('نموذج تجريبي بشكل الورق');

    foreach ([$draft, $others, $staff] as $entry) {
        $this->get(route('account.forms.show', $entry))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('account.forms.index'))->assertRedirect(route('login'));
});

// ── الطباعة ──

it('prints an entry in the browser like a Word page, with the letterhead, the values and the draft mark', function () {
    Setting::put('contact', ['email' => 'office@example.test', 'phone' => '0500000000', 'address' => 'عنوان تجريبي']);
    $template = paperLikeTemplate($this->secretary);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => [
        'name' => 'اسم <b>تجريبي</b>',
        'day' => '2026-10-10',
        'items' => [['c0' => 'أقلام', 'c1' => '3']],
    ]], $template);

    $this->actingAs($this->board)->get(route('documents.entries.print', $entry))
        ->assertOk()
        ->assertSee(__('site.name'))
        ->assertSee('images/brand/logo-horizontal.svg', false)
        ->assertSee('office@example.test')
        ->assertSee('عنوان تجريبي')
        ->assertSee('القسم الأول')
        ->assertSee('فقرة ثابتة تجريبية')
        ->assertSee('اسم &lt;b&gt;تجريبي&lt;/b&gt;', false)
        ->assertSee('أقلام')
        ->assertSee($entry->reference)
        ->assertSee(__('documents.print.draft'))
        ->assertSee('size: A4', false);

    expect(Activity::where('event', 'viewed')->whereMorphedTo('subject', $entry)->where('causer_id', $this->board->id)->exists())->toBeTrue();
});

it('marks the letterhead details as missing when the settings are empty, instead of inventing them', function () {
    $entry = FormEntry::factory()->create(['created_by' => $this->secretary->id]);

    $this->actingAs($this->secretary)->get(route('documents.entries.print', $entry))
        ->assertOk()
        ->assertSee(__('documents.print.missing_contact'));
});

it('keeps the browser print view from those who may not see the entry', function () {
    $entry = FormEntry::factory()->create();

    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(route('documents.entries.print', $entry))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('documents.entries.print', $entry))->assertForbidden();

    auth()->logout();
    $this->get(route('documents.entries.print', $entry))->assertRedirect(route('login'));
});

it('prints the new layout to a real PDF with the letterhead', function () {
    $template = paperLikeTemplate($this->secretary);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => [
        'name' => 'اسم {PAGENO}',
        'items' => [['c0' => 'أقلام', 'c1' => '3']],
    ]], $template);

    $pdf = app(PrintFormEntry::class)->handle($this->board, $entry);

    expect($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('/Subtype /Form') // الشعار الرسمي في الترويسة
        ->and(fn () => app(PrintFormEntry::class)->handle(userWithRole(RoleName::ContentEditor), $entry))->toThrow(AuthorizationException::class);
});

// ── التصدير ──

it('exports the entries of a form to CSV for those allowed, safely, and logs it', function () {
    $template = paperLikeTemplate($this->secretary);
    $employee = userWithRole(RoleName::Secretary, ['name' => 'موظف تجريبي']);
    app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $employee->id, 'answers' => [
        'name' => '=HYPERLINK("x")',
        'items' => [['c0' => 'أقلام', 'c1' => '3']],
    ]], $template);
    FormEntry::factory()->create(); // معبأ نموذج آخر لا يدخل

    $response = app(ExportFormEntries::class)->handle($this->secretary, $template);
    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('الاسم')
        ->and($csv)->toContain('البنود')
        ->and($csv)->not->toContain('القسم الأول')
        ->and($csv)->toContain('موظف تجريبي')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain('أقلام — 3')
        ->and(substr_count(trim($csv), "\n"))->toBe(1);

    $log = Activity::where('event', 'form_entries_exported')->sole();
    expect($log->causer_id)->toBe($this->secretary->id)
        ->and($log->properties['count'])->toBe(1);

    expect(fn () => app(ExportFormEntries::class)->handle($this->board, $template))->toThrow(AuthorizationException::class);
});

it('offers the export in the forms table to those allowed only', function () {
    $template = paperLikeTemplate($this->secretary);

    $this->actingAs($this->secretary);
    Livewire::test(ListFormTemplates::class)
        ->assertTableActionVisible('export', $template)
        ->callTableAction('export', $template)
        ->assertFileDownloaded();

    $this->actingAs($this->board);
    Livewire::test(ListFormTemplates::class)->assertTableActionHidden('export', $template);
});

// ── ما أضافته المراجعتان ──

it('lays half-width fields out in pairs and keeps a lone one on its own row', function () {
    $template = paperLikeTemplate($this->secretary);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم', 'items' => [['c0' => 'بند', 'c1' => '1']]]], $template);

    $blocks = FormEntryLayout::blocks($entry);

    expect(array_column($blocks, 'type'))->toBe(['heading', 'paragraph', 'row', 'table', 'row'])
        ->and(array_column($blocks[2]['cells'], 'label'))->toBe(['الاسم', 'التاريخ'])
        ->and($blocks[3]['rows'])->toBe([['بند', '1']])
        ->and(array_column($blocks[4]['cells'], 'label'))->toBe(['التوقيع']);
});

it('prints an entry of an older version that has no layout keys', function () {
    $entry = FormEntry::factory()->create(['answers' => ['name' => 'اسم قديم'], 'created_by' => $this->secretary->id]);

    expect(array_column(FormEntryLayout::blocks($entry), 'type'))->toBe(['row', 'row', 'row', 'row', 'row']);
    $this->actingAs($this->secretary)->get(route('documents.entries.print', $entry))->assertOk()->assertSee('اسم قديم');
});

it('refuses a required table sent with empty rows only', function () {
    $template = app(SaveFormTemplate::class)->handle($this->secretary, new FormTemplate, ['title' => 'نموذج', 'fields' => [
        ['key' => 'items', 'label' => 'البنود', 'type' => 'table', 'required' => true, 'columns' => ['البند']],
    ]]);

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['items' => [['c0' => ' ']]]], $template))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('answers.items'));
});

it('keeps the person when an edit does not send one, or sends the same one after the account changed', function () {
    $employee = userWithRole(RoleName::Secretary);
    $template = paperLikeTemplate($this->secretary);
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $employee->id, 'answers' => ['name' => 'اسم']], $template);

    app(SaveFormEntry::class)->handle($this->secretary, $entry, ['answers' => ['name' => 'تعديل']]);
    expect($entry->fresh()->person_id)->toBe($employee->id);

    $employee->forceFill(['is_active' => false])->save();
    app(SaveFormEntry::class)->handle($this->secretary, $entry->fresh(), ['person_id' => $employee->id, 'answers' => ['name' => 'تعديل ثان']]);
    expect($entry->fresh()->person_id)->toBe($employee->id);
});

it('refuses inactive, anonymized or admin accounts as the person of a participants form', function () {
    $form = paperLikeTemplate($this->secretary, FormAudience::Participants);

    foreach ([User::factory()->create(['is_active' => false]), User::factory()->create(['anonymized_at' => now()]), $this->board] as $person) {
        expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['person_id' => $person->id, 'answers' => ['name' => 'اسم']], $form))
            ->toThrow(ValidationException::class);
    }
});

it('downloads the PDF from the print view for the allowed only', function () {
    $entry = FormEntry::factory()->create();

    $this->actingAs($this->board)->get(route('documents.entries.pdf', $entry))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(route('documents.entries.pdf', $entry))->assertForbidden();
});

it('keeps the staff print view from a role outside the panel even with the permission', function () {
    $member = userWithRole(RoleName::AssemblyMember);
    $member->givePermissionTo('form_entries.view');
    $entry = FormEntry::factory()->create();

    $this->actingAs($member)->get(route('documents.entries.print', $entry))->assertForbidden();
});

it('lets a participant download the PDF of their approved entry only, and shows «my forms» only to those who have one', function () {
    $participant = User::factory()->create();
    $form = paperLikeTemplate($this->secretary, FormAudience::Participants);
    $draft = FormEntry::factory()->create(['form_template_version_id' => $form->current_version_id, 'person_id' => $participant->id]);

    expect($participant->can('viewOwnFormEntries'))->toBeFalse();

    $approved = FormEntry::factory()->approved()->create(['form_template_version_id' => $form->current_version_id, 'person_id' => $participant->id]);

    expect($participant->fresh()->can('viewOwnFormEntries'))->toBeTrue();

    $this->actingAs($participant)->get(route('account.forms.pdf', $approved))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('account.forms.pdf', $draft))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('account.forms.pdf', $approved))->assertForbidden();
});

it('includes the entries in the person file in their personal data copy', function () {
    $participant = User::factory()->create();
    $entry = FormEntry::factory()->create(['person_id' => $participant->id]);

    expect(app(ExportUserData::class)->handle($participant)['form_entries'][0]['reference'])->toBe($entry->reference);
});
