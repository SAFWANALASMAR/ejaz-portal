<?php

use App\Enums\RoleName;
use App\Filament\Resources\JobFiles\JobFileResource;
use App\Filament\Resources\JobFiles\Pages\CreateJobFile;
use App\Filament\Resources\JobFiles\Pages\EditJobFile;
use App\Filament\Resources\JobFiles\Pages\ListJobFiles;
use App\Filament\Resources\JobFiles\Pages\ViewJobFile;
use App\Filament\Resources\JobFiles\RelationManagers\DocumentsRelationManager;
use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| شاشات ملفات الوظائف في اللوحة: بطاقات الملفات لأصحابها، وصفحة الملف بمستنداته، والتنزيل برابط مؤقت
| موقّع يُفحص عنده الدخول والصلاحية. وكل اطلاع وتنزيل يُسجَّل.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->file = JobFile::factory()->responsible(RoleName::ProjectsManager, [RoleName::MarketingManager])->create(['title' => 'ملف المشاريع التجريبي']);
    $this->hidden = JobFile::factory()->responsible(RoleName::Accountant)->create(['title' => 'ملف المحاسب التجريبي']);
    $this->projects = userWithRole(RoleName::ProjectsManager);
});

function storedDocument(JobFile $file, User $creator): JobFileDocument
{
    $document = JobFileDocument::factory()->create(['job_file_id' => $file->id, 'created_by' => $creator->id, 'original_name' => 'خطة.pdf']);
    Storage::disk('local')->put($document->path, pdfContents());

    return $document;
}

it('shows each job holder the cards of their files, the executive director all, and others nothing', function () {
    $this->actingAs($this->projects);
    Livewire::test(ListJobFiles::class)
        ->assertCanSeeTableRecords([$this->file])
        ->assertCanNotSeeTableRecords([$this->hidden])
        ->assertActionHidden('create');
    $this->get(JobFileResource::getUrl('view', ['record' => $this->file]))->assertOk()->assertSee('ملف المشاريع التجريبي');
    $this->get(JobFileResource::getUrl('view', ['record' => $this->hidden]))->assertNotFound();
    $this->get(JobFileResource::getUrl('edit', ['record' => $this->file]))->assertForbidden();

    $executive = userWithRole(RoleName::ExecutiveDirector);
    $this->actingAs($executive);
    Livewire::test(ListJobFiles::class)->assertCanSeeTableRecords([$this->file, $this->hidden]);
    $this->get(JobFileResource::getUrl('view', ['record' => $this->hidden]))->assertOk();
    $this->get(JobFileResource::getUrl('create'))->assertOk();

    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(JobFileResource::getUrl('index'))->assertForbidden();
    }

    // المستفيد لا يدخل اللوحة، والزائر يُحوَّل إلى الدخول
    $this->actingAs(userWithRole(RoleName::Member))->get(JobFileResource::getUrl('index'))->assertForbidden();
    auth()->logout();
    $this->get(JobFileResource::getUrl('index'))->assertRedirect();
});

it('logs opening a file', function () {
    $this->actingAs($this->projects)->get(JobFileResource::getUrl('view', ['record' => $this->file]))->assertOk();

    $log = Activity::query()->where('log_name', 'job_files')->where('event', 'viewed')->sole();
    expect($log->causer_id)->toBe($this->projects->id)->and($log->subject_id)->toBe($this->file->id);
});

it('lets the responsible add, edit and delete documents from the file page', function () {
    $this->actingAs($this->projects);

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->file, 'pageClass' => ViewJobFile::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'title' => 'خطة تنفيذية',
            'path' => UploadedFile::fake()->createWithContent('plan.pdf', pdfContents()),
        ])
        ->assertHasNoFormErrors();

    $document = JobFileDocument::sole();
    expect($document->title)->toBe('خطة تنفيذية')
        ->and($document->original_name)->toBe('plan.pdf')
        ->and(Storage::disk('local')->exists($document->path))->toBeTrue();

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->file, 'pageClass' => ViewJobFile::class])
        ->callAction(TestAction::make(EditAction::class)->table($document), ['title' => 'خطة معدلة'])
        ->assertHasNoFormErrors();
    expect($document->fresh()->title)->toBe('خطة معدلة');

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->file, 'pageClass' => ViewJobFile::class])
        ->callAction(TestAction::make(DeleteAction::class)->table($document));
    expect($document->fresh()->trashed())->toBeTrue();
});

it('keeps a reading participant from adding, editing or deleting from the file page', function () {
    $marketing = userWithRole(RoleName::MarketingManager);
    $document = storedDocument($this->file, $this->projects);
    $this->actingAs($marketing);

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->file, 'pageClass' => ViewJobFile::class])
        ->assertCanSeeTableRecords([$document])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make(EditAction::class)->table($document))
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($document))
        ->assertActionVisible(TestAction::make('download')->table($document));
});

it('downloads a document by a temporary signed link, checked and logged', function () {
    $document = storedDocument($this->file, $this->projects);
    $url = URL::temporarySignedRoute('job-files.documents.download', now()->addMinutes(5), ['document' => $document]);

    $this->actingAs($this->projects)->get($url)->assertOk()->assertDownload();
    $log = Activity::query()->where('log_name', 'job_files')->where('event', 'file_downloaded')->sole();
    expect($log->causer_id)->toBe($this->projects->id)->and($log->subject_id)->toBe($document->id);

    // غير المشارك، والرابط بلا توقيع أو منتهٍ، والزائر: مرفوضون
    $this->actingAs(userWithRole(RoleName::Accountant))->get($url)->assertForbidden();
    $this->actingAs($this->projects)->get(route('job-files.documents.download', $document))->assertForbidden();
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
    $this->travelBack();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    // والمحذوف لا يُنزَّل
    $document->delete();
    $this->actingAs($this->projects)->get($url)->assertNotFound();
});

it('lets the executive director create a file from the panel', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(CreateJobFile::class)
        ->fillForm(['title' => 'ملف الفعاليات التجريبي', 'responsible_role' => 'marketing_manager', 'participant_roles' => ['projects_manager'], 'icon' => 'calendar'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(JobFile::query()->where('title', 'ملف الفعاليات التجريبي')->sole()->participant_roles)->toBe(['projects_manager']);
});

it('lets the responsible set participants from the file page', function () {
    $this->actingAs($this->projects);

    Livewire::test(ViewJobFile::class, ['record' => $this->file->uuid])
        ->callAction('participants', ['participant_roles' => ['accountant'], 'participants_contribute' => true])
        ->assertHasNoActionErrors();

    expect($this->file->fresh()->participant_roles)->toBe(['accountant'])
        ->and($this->file->fresh()->participants_contribute)->toBeTrue();

    $this->actingAs(userWithRole(RoleName::MarketingManager));
    Livewire::test(ViewJobFile::class, ['record' => $this->file->uuid])->assertNotFound();
});

it('lists several documents, letting an allowed participant edit only their own', function () {
    $marketing = userWithRole(RoleName::MarketingManager);
    $this->file->update(['participants_contribute' => true]);
    $theirs = storedDocument($this->file, $this->projects);
    $own = storedDocument($this->file, $marketing);
    $this->actingAs($marketing);

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->file, 'pageClass' => ViewJobFile::class])
        ->assertCanSeeTableRecords([$theirs, $own])
        ->assertActionVisible(TestAction::make(CreateAction::class)->table())
        ->assertActionVisible(TestAction::make('download')->table($theirs))
        ->assertActionVisible(TestAction::make(EditAction::class)->table($own))
        ->assertActionHidden(TestAction::make(EditAction::class)->table($theirs))
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($theirs));
});

it('lets the executive director delete an empty file from the panel, but not one with documents', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(EditJobFile::class, ['record' => $this->hidden->uuid])->callAction('delete');
    expect($this->hidden->fresh()->trashed())->toBeTrue();

    storedDocument($this->file, $this->projects);
    Livewire::test(EditJobFile::class, ['record' => $this->file->uuid])->assertActionHidden('delete');
});

it('sends a job holder without two-factor to set it up before downloading', function () {
    $document = storedDocument($this->file, $this->projects);
    $withoutTwoFactor = User::factory()->create();
    $withoutTwoFactor->assignRole(RoleName::ProjectsManager->value);
    $url = URL::temporarySignedRoute('job-files.documents.download', now()->addMinutes(5), ['document' => $document]);

    $this->actingAs($withoutTwoFactor)->get($url)->assertRedirect(route('account.security'));
    expect(Activity::query()->where('event', 'file_downloaded')->exists())->toBeFalse();
});
