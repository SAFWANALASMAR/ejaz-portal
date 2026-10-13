<?php

use App\Actions\Registers\SaveRegisterEntry;
use App\Actions\Registers\UpdateRegisterDefaults;
use App\Enums\RegisterEntryStatus;
use App\Enums\RegisterEntryType;
use App\Enums\RoleName;
use App\Models\RegisterEntry;
use App\Models\User;
use App\Support\Registers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| السجلات والمستندات ذات الصلاحية (requirements/13 §1): المتبقي والحالة محسوبان من التاريخين ومختبران عند
| الحدود، والمرفقات في القرص الخاص برابط مؤقت موقّع، وكل اطلاع وتنزيل وتعديل يُسجَّل.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

function registerData(array $overrides = []): array
{
    return [
        'type' => 'license',
        'title' => 'ترخيص تجريبي',
        'issuer' => 'جهة تجريبية',
        'document_number' => 'T-1',
        'issued_on' => '2026-01-01',
        'expires_on' => '2027-01-01',
        'alert_days' => 30,
        'responsible_id' => null,
        'notes' => null,
        'attachments' => [],
        ...$overrides,
    ];
}

function registerFile(string $contents, string $extension = 'pdf'): array
{
    $path = storedPrivateFile(RegisterEntry::DIRECTORY, $contents, $extension);

    return ['path' => $path, 'name' => 'مرفق.'.$extension];
}

it('computes remaining days and status at the boundaries', function (?string $expires, int $alertDays, ?int $remaining, RegisterEntryStatus $status) {
    $today = Carbon::parse('2026-10-10');
    $entry = RegisterEntry::factory()->make(['expires_on' => $expires, 'alert_days' => $alertDays]);

    expect($entry->remainingDays($today))->toBe($remaining)
        ->and($entry->status($today))->toBe($status);
})->with([
    'the last valid day' => ['2026-10-10', 30, 0, RegisterEntryStatus::Expiring],
    'the first day of the alert window' => ['2026-11-09', 30, 30, RegisterEntryStatus::Expiring],
    'the day before the alert window' => ['2026-11-10', 30, 31, RegisterEntryStatus::Valid],
    'the day after the last valid day' => ['2026-10-09', 30, -1, RegisterEntryStatus::Expired],
    'long expired' => ['2025-01-01', 30, -647, RegisterEntryStatus::Expired],
    'no expiry' => [null, 30, null, RegisterEntryStatus::NoExpiry],
    'a zero-day window warns on the last day only' => ['2026-10-11', 0, 1, RegisterEntryStatus::Valid],
]);

it('takes today in Riyadh, not UTC', function () {
    // 22:30 بتوقيت UTC في التاسع = 01:30 في الرياض يوم العاشر
    $this->travelTo(Carbon::parse('2026-10-09 22:30:00', 'UTC'));
    $entry = RegisterEntry::factory()->make(['expires_on' => '2026-10-09', 'alert_days' => 30]);

    expect(Registers::today()->toDateString())->toBe('2026-10-10')
        ->and($entry->status())->toBe(RegisterEntryStatus::Expired);
});

it('filters by status in the database exactly as computed', function () {
    $this->travelTo(Carbon::parse('2026-10-10 09:00:00', 'Asia/Riyadh'));
    $entries = collect([
        RegisterEntry::factory()->create(['expires_on' => '2026-10-10', 'alert_days' => 30]),
        RegisterEntry::factory()->create(['expires_on' => '2026-11-09', 'alert_days' => 30]),
        RegisterEntry::factory()->create(['expires_on' => '2026-11-10', 'alert_days' => 30]),
        RegisterEntry::factory()->create(['expires_on' => '2026-10-09', 'alert_days' => 30]),
        RegisterEntry::factory()->create(['expires_on' => null, 'alert_days' => 30]),
        RegisterEntry::factory()->create(['expires_on' => '2026-12-01', 'alert_days' => 60]),
    ]);

    foreach (RegisterEntryStatus::cases() as $status) {
        $expected = $entries->filter(fn (RegisterEntry $entry): bool => $entry->status() === $status)->pluck('id')->sort()->values()->all();

        expect(RegisterEntry::query()->whereStatus($status)->pluck('id')->sort()->values()->all())->toBe($expected);
    }

    expect(RegisterEntry::query()->whereStatus(RegisterEntryStatus::Expiring)->count())->toBe(3);
});

it('lets the executive director and the secretary manage, and the board only read', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData());
    app(SaveRegisterEntry::class)->handle($this->secretary, $entry, registerData(['title' => 'ترخيص معدّل']));

    expect($entry->fresh()->title)->toBe('ترخيص معدّل')
        ->and($entry->type)->toBe(RegisterEntryType::License)
        ->and($entry->created_by)->toBe($this->director->id)
        ->and(Activity::where('subject_type', RegisterEntry::class)->where('event', 'updated')->exists())->toBeTrue();

    expect(fn () => app(SaveRegisterEntry::class)->handle($this->board, new RegisterEntry, registerData()))->toThrow(AuthorizationException::class)
        ->and($this->board->can('view', $entry))->toBeTrue()
        ->and($this->board->can('update', $entry))->toBeFalse()
        ->and($this->board->can('delete', $entry))->toBeFalse();

    foreach ([RoleName::ProjectsManager, RoleName::ContentEditor, RoleName::ProgramManager, RoleName::AssemblyMember, RoleName::Member] as $role) {
        $user = userWithRole($role);

        expect($user->can('viewAny', RegisterEntry::class))->toBeFalse()
            ->and($user->can('view', $entry))->toBeFalse()
            ->and(fn () => app(SaveRegisterEntry::class)->handle($user, new RegisterEntry, registerData()))->toThrow(AuthorizationException::class);
    }

    expect($this->director->can('forceDelete', $entry))->toBeFalse();
});

it('validates dates, type and the responsible account', function (array $override, string $field) {
    expect(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData($override)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'unknown type' => [['type' => 'passport'], 'type'],
    'no title' => [['title' => ''], 'title'],
    'expiry before issue' => [['issued_on' => '2026-05-01', 'expires_on' => '2026-04-01'], 'expires_on'],
    'negative alert window' => [['alert_days' => -1], 'alert_days'],
    'huge alert window' => [['alert_days' => 5000], 'alert_days'],
]);

it('only takes a responsible account that can see the registers', function () {
    expect(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['responsible_id' => userWithRole(RoleName::Member)->id])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('responsible_id'));

    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['responsible_id' => $this->secretary->id]));
    expect($entry->responsible_id)->toBe($this->secretary->id);
});

it('saves without an expiry date for what does not expire', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['type' => 'property', 'expires_on' => null]));

    expect($entry->status())->toBe(RegisterEntryStatus::NoExpiry)
        ->and($entry->remainingDays())->toBeNull();
});

it('takes real PDFs and re-encoded images as attachments, and refuses other files', function () {
    $png = registerFile(imageBytes('png', 40, 30), 'png');

    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents()), $png]]));
    expect($entry->attachments)->toHaveCount(2)
        ->and($entry->attachments[0]['size'])->toBeGreaterThan(0);

    $script = registerFile('<?php echo 1;', 'php');
    expect(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [$script]])))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($script['path']))->toBeFalse()
        ->and(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'))]])))->toThrow(ValidationException::class)
        // مرفق سجل آخر لا يُعاد استعماله، ولا مسار خارج المجلد
        ->and(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [['path' => $png['path'], 'name' => 'x.png']]])))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [['path' => 'meeting-minutes/x.pdf', 'name' => 'x.pdf']]])))->toThrow(ValidationException::class);
});

it('refuses an attachment over the upload limit before reading it, and deletes it', function () {
    $large = registerFile(pdfContents().str_repeat(' ', 20 * 1024 * 1024));

    expect(fn () => app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [$large]])))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($large['path']))->toBeFalse();
});

it('deletes the file of a removed attachment after saving', function () {
    $file = registerFile(pdfContents());
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [$file]]));

    app(SaveRegisterEntry::class)->handle($this->director, $entry, registerData(['attachments' => []]));

    expect(Storage::disk('local')->exists($file['path']))->toBeFalse()
        ->and($entry->fresh()->attachments)->toBeNull();
});

it('downloads an attachment by a signed link only, for those who can see it, and logs it', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents())]]));
    $url = $entry->attachmentUrl(0);

    $this->actingAs($this->board)->get($url)->assertOk()->assertDownload();
    expect(Activity::where('subject_type', RegisterEntry::class)->where('event', 'file_downloaded')->where('causer_id', $this->board->id)->exists())->toBeTrue();

    // بلا توقيع، أو لغير صاحب الصلاحية، أو لزائر، أو لرقم غير موجود
    $this->actingAs($this->board)->get(route('registers.attachment', [$entry, 0]))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect();
    $this->actingAs($this->board)->get($entry->attachmentUrl(5))->assertNotFound();

    // ورابط منتهٍ
    $this->travel(6)->minutes();
    $this->actingAs($this->board)->get($url)->assertForbidden();
});

it('refuses downloads of a deleted entry', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents())]]));
    $url = $entry->attachmentUrl(0);
    $entry->delete();

    $this->actingAs($this->director)->get($url)->assertNotFound();
});

it('lets the managers set the default alert window, and uses it when the form opens', function () {
    expect(Registers::defaultAlertDays())->toBe((int) config('registers.default_alert_days'));

    app(UpdateRegisterDefaults::class)->handle($this->secretary, ['default_alert_days' => 45]);
    expect(Registers::defaultAlertDays())->toBe(45);

    expect(fn () => app(UpdateRegisterDefaults::class)->handle($this->board, ['default_alert_days' => 10]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateRegisterDefaults::class)->handle($this->secretary, ['default_alert_days' => 0]))->toThrow(ValidationException::class);
});

it('logs attachment changes by file name', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents())]]));
    app(SaveRegisterEntry::class)->handle($this->director, $entry, registerData(['attachments' => []]));

    $logs = Activity::where('subject_type', RegisterEntry::class)->where('subject_id', $entry->id)->whereNotNull('properties->attachments_removed')->orderBy('id')->get();
    expect($logs->last()->properties['attachments_removed'])->toBe(['مرفق.pdf']);
});

it('refuses the download to a session without two-factor authentication', function () {
    $entry = app(SaveRegisterEntry::class)->handle($this->director, new RegisterEntry, registerData(['attachments' => [registerFile(pdfContents())]]));
    $board = User::factory()->create();
    $board->assignRole(RoleName::BoardMember->value);

    $this->actingAs($board)->get($entry->attachmentUrl(0))->assertRedirect();
});
