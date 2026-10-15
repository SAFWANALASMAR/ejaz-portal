<?php

use App\Models\FormEntry;
use App\Models\ServiceRequest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| الملفات الشخصية التي أعادتها استعادة نسخة احتياطية (مراجعة التكامل، 14 أكتوبر 2026): مرفقات النماذج المعبأة
| وتواقيعها ومرفقات الطلبات بلا سجل يشير إليها تُحذف، ولا يُمس المرتبط ولا الحديث ولا ما خارج مجلداتها.
*/

beforeEach(function () {
    Storage::fake('local');
});

function agedPrivateFile(string $directory, int $daysOld): string
{
    $path = storedPrivateFile($directory);
    touch(Storage::disk('local')->path($path), now()->subDays($daysOld)->getTimestamp());

    return $path;
}

it('deletes old personal files no record points to, and keeps referenced, recent and foreign files', function () {
    $orphans = [
        agedPrivateFile(FormEntry::FILES_DIRECTORY, 3),
        agedPrivateFile(FormEntry::SIGNATURES_DIRECTORY, 3),
        agedPrivateFile(ServiceRequest::FILE_DIRECTORY, 3),
    ];

    $attachment = agedPrivateFile(FormEntry::FILES_DIRECTORY, 30);
    $signature = agedPrivateFile(FormEntry::SIGNATURES_DIRECTORY, 30);
    FormEntry::factory()->create([
        'files' => [['key' => 'id', 'path' => $attachment, 'name' => 'هوية.pdf', 'size' => 8]],
        'signatures' => [['key' => 'sign', 'path' => $signature]],
    ]);
    $cv = agedPrivateFile(ServiceRequest::FILE_DIRECTORY, 30);
    // المحذوف ناعماً يُستعاد، فملفه مرجع
    ServiceRequest::factory()->create(['files' => ['cv' => ['path' => $cv, 'name' => 'سيرتي.pdf', 'size' => 8]]])->delete();

    $recent = storedPrivateFile(FormEntry::FILES_DIRECTORY);
    $foreign = agedPrivateFile('archive', 30);

    $this->artisan('ejaz:prune-orphan-private-files')->assertSuccessful();

    $disk = Storage::disk('local');
    foreach ($orphans as $orphan) {
        expect($disk->exists($orphan))->toBeFalse();
    }
    foreach ([$attachment, $signature, $cv, $recent, $foreign] as $kept) {
        expect($disk->exists($kept))->toBeTrue();
    }
});

it('only counts in a dry run, and takes any age right after a restore', function () {
    $fresh = storedPrivateFile(ServiceRequest::FILE_DIRECTORY);

    $this->artisan('ejaz:prune-orphan-private-files', ['--dry-run' => true, '--days' => 0])
        ->expectsOutputToContain('سيُحذف 1 ملف')
        ->assertSuccessful();
    expect(Storage::disk('local')->exists($fresh))->toBeTrue();

    $this->artisan('ejaz:prune-orphan-private-files', ['--days' => 0])->assertSuccessful();
    expect(Storage::disk('local')->exists($fresh))->toBeFalse();
});

it('stops when the deletion looks wholesale, unless forced', function () {
    $paths = array_map(fn () => agedPrivateFile(FormEntry::FILES_DIRECTORY, 3), range(1, 51));

    $this->artisan('ejaz:prune-orphan-private-files')->assertFailed();
    expect(Storage::disk('local')->exists($paths[0]))->toBeTrue();

    $this->artisan('ejaz:prune-orphan-private-files', ['--force' => true])->assertSuccessful();
    expect(Storage::disk('local')->exists($paths[0]))->toBeFalse();
});

it('schedules the orphan private files cleanup weekly', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'ejaz:prune-orphan-private-files'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * 0');
});
