<?php

use App\Console\Commands\CheckHealth;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function beat(?int $minutesAgo = 0): void
{
    Cache::put(CheckHealth::HEARTBEAT_KEY, now()->subMinutes($minutesAgo)->getTimestamp(), now()->addDay());
}

function failJob(int $hoursAgo = 1): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException',
        'failed_at' => now()->subHours($hoursAgo),
    ]);
}

it('passes when every check is healthy', function () {
    beat();

    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.checks.database'))
        ->expectsOutputToContain(__('health.checks.schedule'))
        ->assertSuccessful();
});

it('fails when the scheduler has never beaten', function () {
    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.failed.schedule_missing'))
        ->assertFailed();
});

it('fails when the scheduler heartbeat is stale', function () {
    config(['health.schedule_max_silence_minutes' => 5]);
    beat(10);

    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.failed.schedule_stale', ['minutes' => 10]))
        ->assertFailed();
});

it('fails when jobs failed recently above the allowed count', function () {
    config(['health.failed_jobs_max' => 1]);
    beat();
    failJob();
    failJob();

    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.failed.failed_jobs', ['count' => 2, 'max' => 1]))
        ->assertFailed();
});

it('ignores failed jobs older than a day', function () {
    config(['health.failed_jobs_max' => 0]);
    beat();
    failJob(hoursAgo: 30);

    $this->artisan('ejaz:health')->assertSuccessful();
});

it('fails when the queue backlog is above the limit', function () {
    config(['health.queue_max_backlog' => 2]);
    beat();
    Queue::fake();
    Queue::push('job-one');
    Queue::push('job-two');
    Queue::push('job-three');

    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.failed.queue', ['size' => 3, 'max' => 2]))
        ->assertFailed();
});

it('fails when free disk space is below the limit', function () {
    config(['health.disk_min_free_percent' => 101]);
    beat();

    $this->artisan('ejaz:health')
        ->expectsOutputToContain(__('health.checks.disk'))
        ->assertFailed();
});

it('writes the heartbeat from the scheduler every minute', function () {
    $this->freezeTime();

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === 'schedule-heartbeat');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');

    $event->run(app());

    expect(Cache::get(CheckHealth::HEARTBEAT_KEY))->toBe(now()->getTimestamp());
});
