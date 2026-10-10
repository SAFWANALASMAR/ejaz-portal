<?php

use App\Actions\Documents\SaveFormTemplate;
use App\Enums\FormEntryStatus;
use App\Enums\RoleName;
use App\Filament\Resources\ArchiveCategories\ArchiveCategoryResource;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Filament\Resources\ArchiveItems\Pages\CreateArchiveItem;
use App\Filament\Resources\ArchiveItems\Pages\ListArchiveItems;
use App\Filament\Resources\ArchiveItems\Pages\ViewArchiveItem;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Filament\Resources\FormEntries\Pages\CreateFormEntry;
use App\Filament\Resources\FormEntries\Pages\EditFormEntry;
use App\Filament\Resources\FormEntries\Pages\ViewFormEntry;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Filament\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| شاشات النماذج والأرشيف في اللوحة: السكرتير يبني ويعبّئ ويؤرشف، والمجلس يطّلع ويعتمد، والمشرف العام
| وحده يحدد تصنيفات الأرشيف ومن يطّلع عليها. وكل اطلاع وتنزيل وطباعة يُسجَّل.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

it('opens each screen to its roles and keeps the others out', function () {
    $this->actingAs($this->secretary);
    foreach ([FormTemplateResource::getUrl('create'), FormEntryResource::getUrl('create'), ArchiveItemResource::getUrl('create')] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get(ArchiveCategoryResource::getUrl('index'))->assertForbidden();

    $this->actingAs($this->board);
    $this->get(FormTemplateResource::getUrl('index'))->assertOk();
    $this->get(FormTemplateResource::getUrl('create'))->assertForbidden();
    $this->get(FormEntryResource::getUrl('create'))->assertForbidden();
    $this->get(ArchiveItemResource::getUrl('index'))->assertOk();
    $this->get(ArchiveItemResource::getUrl('create'))->assertForbidden();

    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(ArchiveCategoryResource::getUrl('index'))->assertOk();

    $editor = userWithRole(RoleName::ContentEditor);
    foreach ([FormTemplateResource::getUrl('index'), FormEntryResource::getUrl('index'), ArchiveItemResource::getUrl('index')] as $url) {
        $this->actingAs($editor)->get($url)->assertForbidden();
    }
});

it('lets the secretary build a form and fill it from the panel, logging the view of the entry', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateFormTemplate::class)
        ->fillForm([
            'title' => 'نموذج استلام تجريبي',
            'is_active' => true,
            'fields' => [
                ['label' => 'المستلم', 'key' => 'receiver', 'type' => 'text', 'required' => true],
                ['label' => 'العدد', 'key' => 'count', 'type' => 'number', 'required' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = FormTemplate::sole();
    expect($template->currentVersion->fields[0]['key'])->toBe('receiver');

    Livewire::test(CreateFormEntry::class)
        ->fillForm(['form_template_id' => $template->id])
        ->fillForm(['answers' => ['receiver' => 'اسم المستلم', 'count' => '3']])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = FormEntry::sole();
    expect($entry->answers)->toEqual(['receiver' => 'اسم المستلم', 'count' => '3'])
        ->and($entry->status)->toBe(FormEntryStatus::Draft);

    Livewire::test(EditFormEntry::class, ['record' => $entry->uuid])
        ->fillForm(['answers' => ['receiver' => 'اسم آخر']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($entry->fresh()->answers['receiver'])->toBe('اسم آخر');

    Livewire::test(ViewFormEntry::class, ['record' => $entry->uuid])->assertOk()->assertSee('اسم آخر');
    expect(Activity::where('event', 'viewed')->whereMorphedTo('subject', $entry)->sole()->causer_id)->toBe($this->secretary->id);
});

it('draws a signature field as a pad on the fill form', function () {
    $template = FormTemplate::factory()->create();
    app(SaveFormTemplate::class)->handle($this->secretary, $template, ['title' => $template->title, 'fields' => [
        ['key' => 'sign', 'label' => 'توقيع المستلم', 'type' => 'signature', 'required' => true],
    ]]);

    $this->actingAs($this->secretary);
    Livewire::test(CreateFormEntry::class)
        ->fillForm(['form_template_id' => $template->id])
        ->assertSeeHtml('<canvas')
        ->assertSee(__('documents.fields.signature_clear'));
});

it('lets the board approve and print an entry from the panel, and logs the print', function () {
    $entry = FormEntry::factory()->create(['created_by' => $this->secretary->id]);

    $this->actingAs($this->board);
    Livewire::test(ViewFormEntry::class, ['record' => $entry->uuid])
        ->assertActionHidden('edit')
        ->callAction('print')
        ->assertFileDownloaded($entry->reference.'.pdf')
        ->callAction('approve');

    expect($entry->fresh()->status)->toBe(FormEntryStatus::Approved)
        ->and(Activity::where('event', 'file_downloaded')->whereMorphedTo('subject', $entry)->sole()->properties->all())->toBe(['file' => 'pdf']);
});

it('files an archive item with a PDF from the panel, and logs the view and the download', function () {
    $category = ArchiveCategory::factory()->create();
    $this->actingAs($this->secretary);

    Livewire::test(CreateArchiveItem::class)
        ->fillForm([
            'type' => 'incoming',
            'archive_category_id' => $category->id,
            'title' => 'خطاب وارد تجريبي',
            'entity' => 'جهة تجريبية',
            'dated_on' => '2026-04-01',
            'files' => [UploadedFile::fake()->createWithContent('letter.pdf', pdfContents())],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = ArchiveItem::sole();
    expect($item->registration())->toBe('2026/0001')
        ->and($item->files)->toHaveCount(1);

    Livewire::test(ViewArchiveItem::class, ['record' => $item->uuid])
        ->callAction('download_0')
        ->assertFileDownloaded('letter.pdf');

    expect(Activity::where('event', 'viewed')->whereMorphedTo('subject', $item)->exists())->toBeTrue()
        ->and(Activity::where('event', 'file_downloaded')->whereMorphedTo('subject', $item)->exists())->toBeTrue();
});

it('hides items of a category the role may not see, in the list and on their page', function () {
    $secret = ArchiveCategory::factory()->roles(['secretary'])->create();
    $hidden = ArchiveItem::factory()->create(['archive_category_id' => $secret->id]);
    $shown = ArchiveItem::factory()->create();

    $this->actingAs($this->board);
    Livewire::test(ListArchiveItems::class)->assertCanSeeTableRecords([$shown])->assertCanNotSeeTableRecords([$hidden]);
    $this->get(ArchiveItemResource::getUrl('view', ['record' => $hidden]))->assertNotFound();

    $this->actingAs($this->secretary);
    Livewire::test(ListArchiveItems::class)->assertCanSeeTableRecords([$shown, $hidden]);
});
