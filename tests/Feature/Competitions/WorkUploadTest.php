<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Competitions\AssignReviewers;
use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Enums\RoleName;
use App\Enums\WorkType;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionReview;
use App\Models\SubmissionWork;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| «رفع الأعمال (صور – PDF – روابط)» (docs/plans/competition-exam.md): الأنواع المسموحة وحدها، وإعادة
| ترميز الصورة، والـ PDF الحقيقي، والرابط https، والقرص الخاص، ووصول الغير مرفوض، والمحكّم يراها.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->competition = Competition::factory()->withQuestions()->create(['slug' => 'works', 'work_types' => ['image', 'pdf', 'link']]);
    $this->applicant = User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]);
    $this->actingAs($this->applicant)->post('/competitions/works/entry/start');
    $this->submission = CompetitionSubmission::sole();
});

function pngUpload(string $name = 'work.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 20, 20);
}

it('shows the works section with the allowed types only', function () {
    $this->get('/competitions/works/entry')->assertOk()
        ->assertSee('رفع الأعمال (صور – PDF – روابط)')
        ->assertSee(__('competitions.works.image'))
        ->assertSee(__('competitions.works.link'));

    $this->competition->forceFill(['work_types' => ['link']])->save();

    $this->get('/competitions/works/entry')->assertSee(__('competitions.works.link'))->assertDontSee(__('competitions.works.pdf'));

    $this->competition->forceFill(['work_types' => null])->save();
    $this->get('/competitions/works/entry')->assertDontSee('رفع الأعمال (صور – PDF – روابط)');
});

it('stores an image re-encoded on the private disk, a real PDF and an https link', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => pngUpload()])->assertRedirect('/competitions/works/entry')->assertSessionHasNoErrors();
    $this->post('/competitions/works/entry/works', ['type' => 'pdf', 'file' => UploadedFile::fake()->createWithContent('work.pdf', pdfContents())])->assertSessionHasNoErrors();
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com/my-work'])->assertSessionHasNoErrors();

    [$image, $pdf, $link] = SubmissionWork::query()->orderBy('id')->get()->all();

    expect($image->type)->toBe(WorkType::Image)
        ->and($image->path)->toStartWith('competition-works/')
        ->and($image->path)->not->toContain('work.png')
        ->and($image->mime)->toBe('image/png')
        ->and($pdf->mime)->toBe('application/pdf')
        ->and($link->url)->toBe('https://example.com/my-work')
        ->and($link->path)->toBeNull();

    Storage::disk('local')->assertExists([$image->path, $pdf->path]);

    $this->get('/competitions/works/entry')->assertSee('https://example.com/my-work')->assertSee(route('competitions.works.show', $image), false);
    $this->get(route('competitions.works.show', $image))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('refuses disallowed types, fake files, active PDFs and unsafe links', function () {
    $this->competition->forceFill(['work_types' => ['image', 'link']])->save();

    $this->post('/competitions/works/entry/works', ['type' => 'pdf', 'file' => UploadedFile::fake()->createWithContent('w.pdf', pdfContents())])->assertSessionHasErrors('type');
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => UploadedFile::fake()->createWithContent('w.png', 'not an image')])->assertSessionHasErrors('file');
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => UploadedFile::fake()->createWithContent('w.png', pdfContents())])->assertSessionHasErrors('file');
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'http://example.com'])->assertSessionHasErrors('url');
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://ejaz.sa@evil.example'])->assertSessionHasErrors('url');
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com', 'file' => pngUpload()])->assertSessionHasErrors('file');

    $this->competition->forceFill(['work_types' => ['pdf']])->save();
    $this->post('/competitions/works/entry/works', ['type' => 'pdf', 'file' => UploadedFile::fake()->createWithContent('w.pdf', pdfContents("4 0 obj\n<< /S /JavaScript /JS (app.alert(1)) >>\nendobj\n"))])->assertSessionHasErrors('file');

    expect(SubmissionWork::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('competition-works'))->toBe([]);
});

it('caps the number of works', function () {
    config(['competitions.works.max_items' => 2]);

    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com/1']);
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com/2']);
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => pngUpload()])->assertSessionHasErrors('type');

    expect(SubmissionWork::count())->toBe(2)
        ->and(Storage::disk('local')->allFiles('competition-works'))->toBe([]);
});

it('keeps works to their owner, and allows removing them only while a draft', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => pngUpload()]);
    $work = SubmissionWork::sole();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('competitions.works.show', $work))->assertNotFound();
    $this->delete(route('competitions.works.destroy', $work))->assertNotFound();
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com'])->assertNotFound();

    $this->actingAs($this->applicant)->delete(route('competitions.works.destroy', $work))->assertRedirect('/competitions/works/entry');
    expect(SubmissionWork::count())->toBe(0);
    Storage::disk('local')->assertMissing($work->path);

    // بعد الإرسال لا إضافة ولا حذف
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com']);
    $this->submission->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'reference' => 'ABCDEFGH23'])->save();

    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com/late'])->assertSessionHas('error');
    $this->delete(route('competitions.works.destroy', SubmissionWork::sole()))->assertSessionHas('error');
    expect(SubmissionWork::count())->toBe(1);
});

it('shows the works files to the assigned reviewer with two-factor only', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => pngUpload()]);
    $work = SubmissionWork::sole();
    $this->submission->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'reference' => 'ABCDEFGH23'])->save();

    $manager = userWithRole(RoleName::ProgramManager);
    $reviewerA = userWithRole(RoleName::Reviewer);
    $reviewerB = userWithRole(RoleName::Reviewer);
    $this->competition->reviewers()->attach([$reviewerA->id, $reviewerB->id]);
    app(AssignReviewers::class)->handle($manager, $this->competition, [$this->submission->id], [$reviewerA->id]);

    $url = route('competitions.reviews.work', [SubmissionReview::sole(), $work]);

    $this->actingAs($reviewerA)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($reviewerB)->get($url)->assertNotFound();
    $this->actingAs($this->applicant)->get($url)->assertNotFound();

    $reviewerA->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    $this->actingAs($reviewerA)->get($url)->assertForbidden();
});

it('deletes the works and the exam registration data with the personal data purge', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'image', 'file' => pngUpload()]);
    $work = SubmissionWork::sole();
    $this->submission->forceFill(['full_name' => 'اسم', 'contact_email' => 'a@example.com', 'national_id' => '1000000008', 'national_id_hash' => str_repeat('a', 64)])->save();

    app(PurgeSubmissionPersonalData::class)->handle($this->submission);

    $raw = DB::table('competition_submissions')->where('id', $this->submission->id)->first();
    expect(SubmissionWork::count())->toBe(0)
        ->and($raw->full_name)->toBeNull()
        ->and($raw->contact_email)->toBeNull()
        ->and($raw->national_id)->toBeNull()
        ->and($raw->national_id_hash)->toBeNull();
    Storage::disk('local')->assertMissing($work->path);
});

it('serves a PDF work as a download, never inline', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'pdf', 'file' => UploadedFile::fake()->createWithContent('work.pdf', pdfContents())]);
    $work = SubmissionWork::sole();

    $response = $this->get(route('competitions.works.show', $work))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');

    $this->get('/competitions/works/entry')->assertSee(__('competitions.works.download'));
});

it('refuses works in an exam competition and after the competition closes', function () {
    $this->competition->forceFill(['closes_at' => now()->subMinute()])->save();
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com'])->assertSessionHas('error');

    $exam = Competition::factory()->exam()->create(['slug' => 'exam-works', 'work_types' => ['link']]);
    Storage::disk('local')->put('competition-media/intro.mp4', mp4Contents(30));
    $this->post('/competitions/exam-works/entry/start');
    $this->post('/competitions/exam-works/entry/works', ['type' => 'link', 'url' => 'https://example.com'])->assertSessionHasErrors('type');

    expect(SubmissionWork::count())->toBe(0);
});

it('includes the exam registration and the works in the user data copy', function () {
    $this->post('/competitions/works/entry/works', ['type' => 'link', 'url' => 'https://example.com/w']);
    $this->submission->forceFill(['full_name' => 'اسم المشارك', 'education_stage' => 'university'])->save();

    $copy = app(ExportUserData::class)->handle($this->applicant);
    $entry = $copy['competition_submissions'][0];

    expect($entry['exam_registration']['full_name'])->toBe('اسم المشارك')
        ->and($entry['exam_registration']['education_stage'])->toBe('university')
        ->and($entry['works'][0]['url'])->toBe('https://example.com/w');
});
