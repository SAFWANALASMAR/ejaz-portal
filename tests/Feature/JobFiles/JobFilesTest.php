<?php

use App\Actions\JobFiles\DeleteJobFileDocument;
use App\Actions\JobFiles\SaveJobFile;
use App\Actions\JobFiles\SaveJobFileDocument;
use App\Actions\JobFiles\UpdateJobFileParticipants;
use App\Enums\RoleName;
use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;
use Database\Seeders\JobFilesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| ملفات الوظائف (requirements/11): لكل وظيفة ملفاتها، يضيف المسؤول فيها ويعدّل ويحذف، ويطّلع المشاركون
| (ويضيفون إن مُنحوا)، والمدير التنفيذي يطّلع على الكل، ولا يرى غيرهم شيئاً. وكل عملية تُسجَّل.
*/

beforeEach(function () {
    Storage::fake('local');
});

function jobFileUpload(string $contents, string $extension = 'pdf'): array
{
    $path = storedPrivateFile(JobFileDocument::DIRECTORY, $contents, $extension);

    return ['title' => 'مستند تجريبي', 'description' => null, 'path' => $path, 'original_name' => 'مستند.'.$extension];
}

function saveDocument(User $actor, JobFile $file, array $data, ?JobFileDocument $document = null): JobFileDocument
{
    return app(SaveJobFileDocument::class)->handle($actor, $file, $document ?? new JobFileDocument, $data);
}

it('seeds the six jobs files and the matrix once, as transcribed', function () {
    $this->seed(JobFilesSeeder::class);

    // 12 للمسؤول التنفيذي (بلا 7 وبالتكرار) و10 لكل وظيفة أخرى، و20 صفاً في المصفوفة
    expect(JobFile::query()->where('from_matrix', false)->count())->toBe(12 + 10 * 5)
        ->and(JobFile::query()->where('from_matrix', true)->count())->toBe(20)
        ->and(JobFile::query()->where('title', 'التكليفات والدراسات')->count())->toBe(2);

    $hr = JobFile::query()->where('from_matrix', true)->where('title', 'الموارد البشرية')->sole();
    expect($hr->responsible_role)->toBe(RoleName::ExecutiveDirector)
        ->and($hr->participant_roles)->toBe(['secretary', 'financial_manager', 'accountant']);

    $all = JobFile::query()->where('from_matrix', true)->where('title', 'المراسلات والأرشيف')->sole();
    expect($all->participant_roles)->toHaveCount(5)->not->toContain('secretary');

    $policies = JobFile::query()->where('from_matrix', true)->where('title', 'اللوائح والسياسات')->sole();
    expect($policies->participant_roles)->toBe([]);

    $ledger = JobFile::query()->where('responsible_role', 'accountant')->where('sort', 1)->sole();
    expect($ledger->title)->toBe('القيود والسجلات المحاسبية')
        ->and($ledger->description)->toBe('القيود اليومية، الأستاذ، ميزان المراجعة، والسجلات المساندة.');

    // ولا يعيد البذر ما عدّلته الإدارة
    $ledger->update(['title' => 'عنوان معدل']);
    $this->seed(JobFilesSeeder::class);
    expect(JobFile::query()->count())->toBe(82)->and($ledger->fresh()->title)->toBe('عنوان معدل');
});

it('shows each job holder only the files they are responsible for or take part in', function () {
    $own = JobFile::factory()->responsible(RoleName::FinancialManager)->create();
    $shared = JobFile::factory()->responsible(RoleName::ExecutiveDirector, [RoleName::FinancialManager])->create();
    $other = JobFile::factory()->responsible(RoleName::MarketingManager)->create();

    $finance = userWithRole(RoleName::FinancialManager);
    $marketing = userWithRole(RoleName::MarketingManager);
    $executive = userWithRole(RoleName::ExecutiveDirector);

    expect(JobFile::visibleTo($finance)->pluck('id')->sort()->values()->all())->toBe([$own->id, $shared->id])
        ->and($finance->can('view', $other))->toBeFalse()
        ->and($marketing->can('view', $shared))->toBeFalse()
        ->and($marketing->can('view', $other))->toBeTrue()
        // المدير التنفيذي يطّلع على الكل
        ->and(JobFile::visibleTo($executive)->count())->toBe(3)
        ->and($executive->can('view', $other))->toBeTrue();

    // غير الموظفين لا يرون شيئاً
    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::Member] as $role) {
        $user = userWithRole($role);
        expect($user->can('viewAny', JobFile::class))->toBeFalse()
            ->and($user->can('view', $own))->toBeFalse()
            ->and(JobFile::visibleTo($user)->count())->toBe(0);
    }
});

it('lets the responsible add, edit and delete documents, with real files only', function () {
    $file = JobFile::factory()->responsible(RoleName::Accountant)->create();
    $accountant = userWithRole(RoleName::Accountant);
    $this->actingAs($accountant);

    $document = saveDocument($accountant, $file, jobFileUpload(pdfContents()));
    expect($document->created_by)->toBe($accountant->id)->and($document->size)->toBeGreaterThan(0);

    $png = jobFileUpload(imageBytes('png', 20, 20), 'png');
    $old = $document->path;

    saveDocument($accountant, $file, [...$png, 'title' => 'عنوان معدل'], $document);
    expect($document->fresh()->title)->toBe('عنوان معدل')
        ->and($document->fresh()->path)->toBe($png['path'])
        // والمستبدَل يبقى في القرص، ومساره القديم والجديد في سجل التعديل، والامتداد من نوع الملف
        ->and(Storage::disk('local')->exists($old))->toBeTrue()
        ->and($document->fresh()->original_name)->toBe('مستند.png');
    $replacement = Activity::query()->where('log_name', 'job_files')->where('event', 'updated')->where('subject_id', $document->id)->sole();
    expect($replacement->attribute_changes['old']['path'] ?? null)->toBe($old)
        ->and($replacement->attribute_changes['attributes']['path'] ?? null)->toBe($png['path']);

    app(DeleteJobFileDocument::class)->handle($accountant, $document);
    expect($document->fresh()->trashed())->toBeTrue();

    $script = jobFileUpload('<?php echo 1;', 'php');
    expect(fn () => saveDocument($accountant, $file, $script))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($script['path']))->toBeFalse()
        ->and(fn () => saveDocument($accountant, $file, jobFileUpload(pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'))))->toThrow(ValidationException::class)
        // ومسار مستند آخر لا يُعاد استعماله، ولا مسار خارج مجلد الملفات
        ->and(fn () => saveDocument($accountant, $file, ['title' => 'x', 'path' => $png['path']]))->toThrow(ValidationException::class)
        ->and(fn () => saveDocument($accountant, $file, ['title' => 'x', 'path' => 'archive/other.pdf']))->toThrow(ValidationException::class)
        ->and(fn () => saveDocument($accountant, $file, ['title' => '', 'path' => jobFileUpload(pdfContents())['path']]))->toThrow(ValidationException::class);

    // كل إضافة وتعديل وحذف في السجل باسم المنفّذ
    $events = Activity::query()->where('log_name', 'job_files')->where('subject_type', $document->getMorphClass())->where('subject_id', $document->id)->pluck('event')->all();
    expect($events)->toBe(['created', 'updated', 'deleted'])
        ->and(Activity::query()->where('log_name', 'job_files')->where('subject_type', $document->getMorphClass())->pluck('causer_id')->unique()->all())->toBe([$accountant->id]);
});

it('keeps participants to reading unless allowed to add, and then to their own documents', function () {
    $file = JobFile::factory()->responsible(RoleName::FinancialManager, [RoleName::Accountant])->create();
    $manager = userWithRole(RoleName::FinancialManager);
    $accountant = userWithRole(RoleName::Accountant);
    $executive = userWithRole(RoleName::ExecutiveDirector);
    $managerDocument = saveDocument($manager, $file, jobFileUpload(pdfContents()));

    expect($accountant->can('view', $managerDocument))->toBeTrue()
        ->and(fn () => saveDocument($accountant, $file, jobFileUpload(pdfContents())))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteJobFileDocument::class)->handle($accountant, $managerDocument))->toThrow(AuthorizationException::class)
        // المدير التنفيذي يطّلع ولا يعدّل ملفاً ليس له فيه دور
        ->and($executive->can('view', $managerDocument))->toBeTrue()
        ->and(fn () => saveDocument($executive, $file, jobFileUpload(pdfContents())))->toThrow(AuthorizationException::class)
        ->and($executive->can('update', $managerDocument))->toBeFalse();

    app(UpdateJobFileParticipants::class)->handle($manager, $file, ['participant_roles' => ['accountant'], 'participants_contribute' => true]);
    $file->refresh();

    $own = saveDocument($accountant, $file, jobFileUpload(pdfContents()));
    expect($accountant->can('update', $own))->toBeTrue()
        ->and($accountant->can('delete', $managerDocument))->toBeFalse()
        ->and($manager->can('delete', $own))->toBeTrue()
        // ولا يُحفظ مستند ملف آخر عبر هذا الملف
        ->and(fn () => saveDocument($manager, JobFile::factory()->responsible(RoleName::FinancialManager)->create(), jobFileUpload(pdfContents()), $own))->toThrow(AuthorizationException::class);

    // والمشرف العام يطّلع ولا يضيف ولا يعدّل (منع صريح لا يغيّره Gate::after)، ورفض المرفوع يحذفه
    $superAdmin = userWithRole(RoleName::TechnicalAdmin);
    $rejected = jobFileUpload(pdfContents());
    expect($superAdmin->can('view', $managerDocument))->toBeTrue()
        ->and($superAdmin->can('update', $managerDocument))->toBeFalse()
        ->and(fn () => saveDocument($superAdmin, $file, $rejected))->toThrow(AuthorizationException::class)
        ->and(Storage::disk('local')->exists($rejected['path']))->toBeFalse();

    // ولا يطّلع غير المشارك ولا غير الموظف
    expect(userWithRole(RoleName::MarketingManager)->can('view', $managerDocument))->toBeFalse()
        ->and(userWithRole(RoleName::Member)->can('view', $managerDocument))->toBeFalse();
});

it('lets the responsible and the executive director alone set the participants of a file, from the six jobs', function () {
    $file = JobFile::factory()->responsible(RoleName::ExecutiveDirector)->create();
    $executive = userWithRole(RoleName::ExecutiveDirector);

    app(UpdateJobFileParticipants::class)->handle($executive, $file, ['participant_roles' => ['secretary', 'executive_director'], 'participants_contribute' => false]);
    expect($file->fresh()->participant_roles)->toBe(['secretary']);

    expect(fn () => app(UpdateJobFileParticipants::class)->handle(userWithRole(RoleName::Secretary), $file, ['participant_roles' => ['accountant']]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateJobFileParticipants::class)->handle($executive, $file, ['participant_roles' => ['board_member']]))->toThrow(ValidationException::class);

    $marketingFile = JobFile::factory()->responsible(RoleName::MarketingManager)->create();
    expect(userWithRole(RoleName::MarketingManager)->can('manageParticipants', $marketingFile))->toBeTrue()
        ->and(userWithRole(RoleName::ProjectsManager)->can('manageParticipants', $marketingFile))->toBeFalse()
        // المدير التنفيذي (job_files.update) يحددهم في كل ملف
        ->and($executive->can('manageParticipants', $marketingFile))->toBeTrue();
});

it('lets the executive director manage the files themselves, and nobody else', function () {
    $executive = userWithRole(RoleName::ExecutiveDirector);
    $data = ['title' => 'ملف جديد', 'responsible_role' => 'projects_manager', 'participant_roles' => ['accountant'], 'icon' => 'chart', 'sort' => 3];

    $file = app(SaveJobFile::class)->handle($executive, new JobFile, $data);
    expect($file->responsible_role)->toBe(RoleName::ProjectsManager)->and($file->fresh()->from_matrix)->toBeFalse();

    expect(fn () => app(SaveJobFile::class)->handle(userWithRole(RoleName::ProjectsManager), $file, $data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveJobFile::class)->handle($executive, new JobFile, [...$data, 'responsible_role' => 'member']))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveJobFile::class)->handle($executive, new JobFile, [...$data, 'icon' => 'skull']))->toThrow(ValidationException::class);

    // والملف الذي فيه مستندات لا يُحذف
    expect($executive->can('delete', $file))->toBeTrue();
    JobFileDocument::factory()->create(['job_file_id' => $file->id]);
    expect($executive->can('delete', $file->fresh()))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('delete', $file->fresh()))->toBeFalse();
});
