<?php

use App\Enums\RoleName;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/*
| بعد الاستعادة من نسخة احتياطية (scripts/restore.md): من أُخفيت بياناته بعد وقت النسخة يعود في القاعدة المستعادة
| ببياناته، فيُعاد إخفاؤه بالإجراء نفسه، ويبقى وقت الإخفاء الأصلي فتُحسب مدة الاحتفاظ منه لا من الاستعادة.
*/

beforeEach(function () {
    $this->admin = userWithRole(RoleName::SuperAdmin, ['email' => 'admin@example.test']);
    // كما في النسخة: قبل طلب الحذف
    $this->restored = User::factory()->create(['name' => 'اسم عاد من النسخة', 'email' => 'back@example.test', 'phone' => '+966512345678']);
});

it('re-anonymizes an account that came back from a backup, keeping the original time', function () {
    $this->artisan('ejaz:reapply-anonymization', [
        'user' => $this->restored->id, '--at' => '2026-09-01T10:00:00Z', '--actor' => 'Admin@Example.test',
    ])->assertSuccessful();

    $user = $this->restored->fresh();

    expect($user->name)->toBe(__('users.anonymized_name'))
        ->and($user->email)->toBe('deleted-'.$user->id.'@deleted.invalid')
        ->and($user->phone)->toBeNull()
        ->and($user->is_active)->toBeFalse()
        ->and($user->anonymized_at->toIso8601ZuluString())->toBe('2026-09-01T10:00:00Z')
        ->and($user->deletion_requested_at)->not->toBeNull()
        ->and(Activity::where('event', 'anonymized')->where('subject_id', $user->id)->where('causer_id', $this->admin->id)->exists())->toBeTrue();
});

it('skips an account already anonymized or missing from the backup', function () {
    $done = User::factory()->create(['deletion_requested_at' => now(), 'anonymized_at' => now()->subDay(), 'name' => 'مخفى']);

    $this->artisan('ejaz:reapply-anonymization', ['user' => $done->id, '--at' => '2026-09-01T10:00:00Z', '--actor' => 'admin@example.test'])
        ->assertSuccessful();
    $this->artisan('ejaz:reapply-anonymization', ['user' => 999999, '--at' => '2026-09-01T10:00:00Z', '--actor' => 'admin@example.test'])
        ->assertSuccessful();

    expect($done->fresh()->name)->toBe('مخفى')
        ->and(Activity::where('event', 'anonymized')->exists())->toBeFalse();
});

it('refuses an actor without the user permissions, an unknown actor, or a bad time', function () {
    userWithRole(RoleName::ContentEditor, ['email' => 'editor@example.test']);

    $runs = [
        ['--at' => '2026-09-01T10:00:00Z', '--actor' => 'editor@example.test'],
        ['--at' => '2026-09-01T10:00:00Z', '--actor' => 'nobody@example.test'],
        ['--at' => '2026-09-01T10:00:00Z'],
        ['--actor' => 'admin@example.test'],
        ['--at' => 'not-a-date', '--actor' => 'admin@example.test'],
        ['--at' => now()->addDay()->toIso8601ZuluString(), '--actor' => 'admin@example.test'],
    ];

    foreach ($runs as $options) {
        $this->artisan('ejaz:reapply-anonymization', ['user' => $this->restored->id, ...$options])->assertFailed();
    }

    $user = $this->restored->fresh();

    expect($user->name)->toBe('اسم عاد من النسخة')
        ->and($user->deletion_requested_at)->toBeNull()
        ->and($user->anonymized_at)->toBeNull();
});

it('documents the re-anonymization step after a restore', function () {
    $doc = file_get_contents(base_path('scripts/restore.md'));

    expect($doc)->toContain('ejaz:reapply-anonymization')
        ->toContain('activity_log')
        ->toContain('deletion_requested_at')
        ->toContain('ejaz:purge-anonymized-data');
});
