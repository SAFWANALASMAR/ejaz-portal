<?php

use App\Actions\Documents\ApproveFormEntry;
use App\Actions\Documents\PrintFormEntry;
use App\Actions\Documents\SaveFormEntry;
use App\Actions\Documents\SaveFormTemplate;
use App\Enums\FormEntryStatus;
use App\Enums\RoleName;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use Database\Factories\FormTemplateFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| النماذج الإلكترونية (requirements/05): منشئ نماذج بإصدارات لا تغيّر المعبأ قبلها، والتعبئة بأنواع
| الحقول ومنها المرفق والتوقيع، والتعديل قبل الاعتماد، والطباعة PDF بترويسة الجمعية.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

/**
 * توقيع صالح كما ترسمه لوحة الرسم: صورة PNG بترميز base64.
 */
function signatureDataUrl(): string
{
    $image = imagecreatetruecolor(120, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imageline($image, 5, 30, 110, 10, imagecolorallocate($image, 20, 60, 50));
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

function formFile(string $contents = ''): array
{
    $path = storedPrivateFile(FormEntry::FILES_DIRECTORY, $contents === '' ? null : $contents);

    return ['path' => $path, 'name' => 'مرفق.pdf'];
}

it('lets the secretary build a form, and keeps the board and others out of building', function () {
    $data = ['title' => 'نموذج صرف تجريبي', 'is_active' => true, 'fields' => FormTemplateFactory::FIELDS];

    foreach ([$this->board, userWithRole(RoleName::ContentEditor)] as $user) {
        expect(fn () => app(SaveFormTemplate::class)->handle($user, new FormTemplate, $data))->toThrow(AuthorizationException::class);
    }

    $template = app(SaveFormTemplate::class)->handle($this->secretary, new FormTemplate, $data);

    expect($template->versions()->count())->toBe(1)
        ->and($template->currentVersion->version)->toBe(1)
        ->and($template->currentVersion->created_by)->toBe($this->secretary->id);
});

it('makes a new version when the fields change, and keeps earlier entries on theirs', function () {
    $template = FormTemplate::factory()->create();
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'قيمة أولى']], $template);

    // تغيير العنوان وحده لا ينشئ إصداراً، ولا ترتيب المفاتيح داخل الحقل
    app(SaveFormTemplate::class)->handle($this->secretary, $template, ['title' => 'عنوان آخر', 'fields' => array_map(fn (array $field): array => array_reverse($field, true), FormTemplateFactory::FIELDS)]);
    expect($template->fresh()->versions()->count())->toBe(1);

    app(SaveFormTemplate::class)->handle($this->secretary, $template->fresh(), ['title' => 'عنوان آخر', 'fields' => [['key' => 'subject', 'label' => 'الموضوع', 'type' => 'text', 'required' => true]]]);
    $template->refresh();

    expect($template->versions()->count())->toBe(2)
        ->and($template->currentVersion->version)->toBe(2)
        ->and($entry->fresh()->version->version)->toBe(1)
        ->and($entry->fresh()->version->fields[0]['key'])->toBe('name')
        ->and($entry->fresh()->answers)->toBe(['name' => 'قيمة أولى']);

    // والجديد على الإصدار الحالي
    $new = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['subject' => 'موضوع']], $template);
    expect($new->version->version)->toBe(2);
});

it('refuses invalid form definitions', function (array $fields) {
    expect(fn () => app(SaveFormTemplate::class)->handle($this->secretary, new FormTemplate, ['title' => 'نموذج', 'fields' => $fields]))->toThrow(ValidationException::class);
})->with([
    'no fields' => [[]],
    'bad key' => [[['key' => 'Bad Key', 'label' => 'حقل', 'type' => 'text']]],
    'repeated key' => [[['key' => 'a', 'label' => 'أ', 'type' => 'text'], ['key' => 'a', 'label' => 'ب', 'type' => 'number']]],
    'unknown type' => [[['key' => 'a', 'label' => 'أ', 'type' => 'html']]],
    'choice without options' => [[['key' => 'a', 'label' => 'أ', 'type' => 'select']]],
]);

it('checks answers by their field types, and the required ones', function (array $answers, string $error) {
    $template = FormTemplate::factory()->create();

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => $answers], $template))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($error));
})->with([
    'missing required' => [['notes' => 'ملاحظة'], 'answers.name'],
    'not a number' => [['name' => 'اسم', 'amount' => 'كثير'], 'answers.amount'],
    'bad date' => [['name' => 'اسم', 'day' => '2026/13/40'], 'answers.day'],
    'unknown choice' => [['name' => 'اسم', 'kind' => 'ج'], 'answers.kind'],
]);

it('takes a real PDF attachment and a drawn signature, and refuses a fake PDF or a broken signature', function () {
    $template = FormTemplate::factory()->create();
    app(SaveFormTemplate::class)->handle($this->secretary, $template, ['title' => $template->title, 'fields' => [
        ['key' => 'name', 'label' => 'الاسم', 'type' => 'text', 'required' => true],
        ['key' => 'scan', 'label' => 'المستند', 'type' => 'file', 'required' => true],
        ['key' => 'sign', 'label' => 'توقيع المستلم', 'type' => 'signature', 'required' => true],
    ]]);
    $template->refresh();

    $fake = formFile('ليس PDF');
    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم'], 'files' => ['scan' => $fake], 'signatures' => ['sign' => signatureDataUrl()]], $template))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($fake['path']))->toBeFalse()
        ->and(Storage::disk('local')->allFiles(FormEntry::SIGNATURES_DIRECTORY))->toBe([]);

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم'], 'files' => ['scan' => formFile()], 'signatures' => ['sign' => 'data:image/png;base64,'.base64_encode('not a png')]], $template))
        ->toThrow(ValidationException::class);

    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم'], 'files' => ['scan' => formFile()], 'signatures' => ['sign' => signatureDataUrl()]], $template);

    expect($entry->files[0]['key'])->toBe('scan')
        ->and($entry->files[0]['size'])->toBeGreaterThan(0)
        ->and($entry->signatures[0]['path'])->toStartWith(FormEntry::SIGNATURES_DIRECTORY.'/')
        ->and(Storage::disk('local')->get($entry->signatures[0]['path']))->toStartWith("\x89PNG");

    // تعديل التوقيع يحذف السابق
    $old = $entry->signatures[0]['path'];
    app(SaveFormEntry::class)->handle($this->secretary, $entry, ['answers' => ['name' => 'اسم'], 'files' => ['scan' => ['path' => $entry->files[0]['path'], 'name' => 'مرفق.pdf']], 'signatures' => ['sign' => signatureDataUrl()]]);
    expect(Storage::disk('local')->exists($old))->toBeFalse();
});

it('lets drafts be edited, a board member who did not fill it approve, and locks what is approved', function () {
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم']], FormTemplate::factory()->create());

    // من عبّأ لا يعتمد ولو كان له الدوران
    $this->secretary->assignRole(RoleName::BoardMember->value);
    expect(fn () => app(ApproveFormEntry::class)->handle($this->secretary, $entry))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveFormEntry::class)->handle(userWithRole(RoleName::ContentEditor), $entry))->toThrow(AuthorizationException::class);

    app(ApproveFormEntry::class)->handle($this->board, $entry);
    $entry->refresh();

    expect($entry->status)->toBe(FormEntryStatus::Approved)
        ->and($entry->approved_by)->toBe($this->board->id)
        ->and(Activity::where('event', 'form_entry_approved')->sole()->causer_id)->toBe($this->board->id)
        ->and(fn () => app(SaveFormEntry::class)->handle($this->secretary, $entry, ['answers' => ['name' => 'تغيير']]))->toThrow(AuthorizationException::class)
        ->and(userWithRole(RoleName::SuperAdmin)->can('update', $entry))->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('delete', $entry))->toBeFalse();
});

it('refuses filling an inactive form, and filling by those without the permission', function () {
    $inactive = FormTemplate::factory()->create(['is_active' => false]);

    expect(fn () => app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم']], $inactive))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveFormEntry::class)->handle($this->board, new FormEntry, ['answers' => ['name' => 'اسم']], FormTemplate::factory()->create()))->toThrow(AuthorizationException::class);
});

it('prints an entry as a real PDF for those who may see it, and logs the print', function () {
    $entry = app(SaveFormEntry::class)->handle($this->secretary, new FormEntry, ['answers' => ['name' => 'اسم {PAGENO}']], FormTemplate::factory()->create());

    $pdf = app(PrintFormEntry::class)->handle($this->board, $entry);

    expect($pdf)->toStartWith('%PDF-')
        ->and(Activity::where('event', 'file_downloaded')->whereMorphedTo('subject', $entry)->sole()->properties->all())->toBe(['file' => 'pdf'])
        ->and(fn () => app(PrintFormEntry::class)->handle(userWithRole(RoleName::ContentEditor), $entry))->toThrow(AuthorizationException::class);
});
