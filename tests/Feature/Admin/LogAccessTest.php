<?php

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;

/*
| سجل الاطلاع والتنزيل الواحد (LogAccess): يكتب في سجل وحدته، ولا يقبل اسم سجل غائباً عن تصفية سجل العمليات.
*/

it('writes the access in the log of its module, in the name of the actor', function () {
    $actor = userWithRole(RoleName::SuperAdmin);
    $subject = User::factory()->create();

    app(LogAccess::class)->handle($actor, $subject, 'registers', ActivityEvent::FileDownloaded, ['file' => 'a.pdf']);

    $log = Activity::where('event', ActivityEvent::FileDownloaded->value)->sole();
    expect($log->log_name)->toBe('registers')
        ->and($log->causer_id)->toBe($actor->id)
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->properties->all())->toBe(['file' => 'a.pdf']);
});

it('refuses a log name the activity log screen does not list', function () {
    expect(fn () => app(LogAccess::class)->handle(userWithRole(RoleName::SuperAdmin), User::factory()->create(), 'document', ActivityEvent::Viewed))
        ->toThrow(InvalidArgumentException::class);
});

it('passes only listed log names from every caller', function () {
    $names = collect(File::allFiles(app_path()))
        ->flatMap(fn (SplFileInfo $file): array => preg_match_all("/->handle\([^;]*?, '([a-z_]+)', ActivityEvent::/", $file->getContents(), $matches) ? $matches[1] : [])
        ->unique()->values();

    expect($names)->not->toBeEmpty()
        ->and($names->diff(ActivityResource::LOGS)->all())->toBe([]);
});
