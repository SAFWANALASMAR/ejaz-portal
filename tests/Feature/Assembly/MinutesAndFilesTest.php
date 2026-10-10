<?php

use App\Actions\Assembly\ApproveMeetingMinutes;
use App\Actions\Assembly\SaveAssemblyFile;
use App\Actions\Assembly\UploadMeetingMinutes;
use App\Enums\AssemblyFileAudience;
use App\Enums\RoleName;
use App\Models\AssemblyFile;
use App\Models\Meeting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| المحاضر (يرفعها السكرتير مسودة ويعتمدها المجلس، فتظهر للأعضاء بعد الاعتماد)، ومكتبة ملفات الأعضاء
| بصلاحيات اطلاع (requirements/04).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

function uploadedPdf(string $directory, string $contents = ''): string
{
    $path = $directory.'/'.fake()->uuid().'.pdf';
    Storage::disk('local')->put($path, $contents === '' ? pdfContents() : $contents);

    return $path;
}

it('lets the secretary upload minutes of a held meeting as a draft, replacing an earlier draft', function () {
    $scheduled = Meeting::factory()->create();
    $held = Meeting::factory()->held()->create();

    expect(fn () => app(UploadMeetingMinutes::class)->handle($this->secretary, $scheduled, uploadedPdf(Meeting::MINUTES_DIRECTORY)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UploadMeetingMinutes::class)->handle($this->board, $held, uploadedPdf(Meeting::MINUTES_DIRECTORY)))->toThrow(AuthorizationException::class);

    $first = uploadedPdf(Meeting::MINUTES_DIRECTORY);
    app(UploadMeetingMinutes::class)->handle($this->secretary, $held, $first);
    $second = uploadedPdf(Meeting::MINUTES_DIRECTORY);
    app(UploadMeetingMinutes::class)->handle($this->secretary, $held->fresh(), $second);

    $held->refresh();
    expect($held->minutes_file)->toBe($second)
        ->and($held->hasDraftMinutes())->toBeTrue()
        ->and($held->minutes_uploaded_by)->toBe($this->secretary->id)
        ->and(Storage::disk('local')->exists($first))->toBeFalse()
        ->and(Activity::where('event', 'minutes_uploaded')->count())->toBe(2);
});

it('refuses minutes that are not a real PDF in their folder, and deletes the upload', function () {
    $held = Meeting::factory()->held()->create();
    $fake = uploadedPdf(Meeting::MINUTES_DIRECTORY, 'not a pdf');

    expect(fn () => app(UploadMeetingMinutes::class)->handle($this->secretary, $held, $fake))->toThrow(ValidationException::class)
        ->and(fn () => app(UploadMeetingMinutes::class)->handle($this->secretary, $held, uploadedPdf(AssemblyFile::DIRECTORY)))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($fake))->toBeFalse()
        ->and($held->fresh()->minutes_file)->toBeNull();
});

it('lets a board member approve minutes, never the one who uploaded them, and locks them once approved', function () {
    $held = Meeting::factory()->held()->create();
    app(UploadMeetingMinutes::class)->handle($this->secretary, $held, uploadedPdf(Meeting::MINUTES_DIRECTORY));

    // من رفع لا يعتمد ولو كان له الدوران
    $this->secretary->assignRole(RoleName::BoardMember->value);
    expect(fn () => app(ApproveMeetingMinutes::class)->handle($this->secretary, $held->fresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveMeetingMinutes::class)->handle(userWithRole(RoleName::ContentEditor), $held->fresh()))->toThrow(AuthorizationException::class);

    app(ApproveMeetingMinutes::class)->handle($this->board, $held->fresh());
    $held->refresh();

    expect($held->hasApprovedMinutes())->toBeTrue()
        ->and($held->minutes_approved_by)->toBe($this->board->id)
        ->and(Activity::where('event', 'minutes_approved')->sole()->causer_id)->toBe($this->board->id)
        // المعتمد لا يُستبدل ولا يُعاد اعتماده، والمشرف العام منهم
        ->and(fn () => app(UploadMeetingMinutes::class)->handle(userWithRole(RoleName::SuperAdmin), $held, uploadedPdf(Meeting::MINUTES_DIRECTORY)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveMeetingMinutes::class)->handle($this->board, $held))->toThrow(AuthorizationException::class);
});

it('lets the secretary add a members file as a real PDF, and the board read the list only', function () {
    $data = ['title' => 'القوائم المالية التجريبية', 'category' => 'financial_statements', 'audience' => 'board', 'file' => uploadedPdf(AssemblyFile::DIRECTORY)];

    expect(fn () => app(SaveAssemblyFile::class)->handle($this->board, new AssemblyFile, $data))->toThrow(AuthorizationException::class);

    $file = app(SaveAssemblyFile::class)->handle($this->secretary, new AssemblyFile, $data);

    expect($file->audience)->toBe(AssemblyFileAudience::Board)
        ->and($file->file_size)->toBeGreaterThan(0)
        ->and($this->board->can('viewAny', AssemblyFile::class))->toBeTrue()
        ->and($this->board->can('update', $file))->toBeFalse();

    $replacement = uploadedPdf(AssemblyFile::DIRECTORY);
    app(SaveAssemblyFile::class)->handle($this->secretary, $file, [...$data, 'file' => $replacement]);

    expect(Storage::disk('local')->exists($data['file']))->toBeFalse()
        ->and($file->fresh()->file)->toBe($replacement);

    $fake = uploadedPdf(AssemblyFile::DIRECTORY, 'not a pdf');
    expect(fn () => app(SaveAssemblyFile::class)->handle($this->secretary, new AssemblyFile, [...$data, 'file' => $fake]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveAssemblyFile::class)->handle($this->secretary, new AssemblyFile, [...$data, 'file' => $replacement]))->toThrow(ValidationException::class);
});
