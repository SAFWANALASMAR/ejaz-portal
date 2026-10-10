<?php

use App\Actions\Livestream\ArchiveLiveStream;
use App\Actions\Livestream\SaveLiveStream;
use App\Enums\LibrarySection;
use App\Enums\LiveStreamProvider;
use App\Enums\LiveStreamStatus;
use App\Enums\RoleName;
use App\Filament\Resources\LiveStreams\LiveStreamResource;
use App\Filament\Resources\LiveStreams\Pages\CreateLiveStream;
use App\Models\LiveStream;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('hides the live page without a current published stream', function () {
    $this->get('/live')->assertNotFound();

    LiveStream::factory()->draft()->create();
    LiveStream::factory()->ended()->create();
    $archived = LiveStream::factory()->create();
    $archived->forceFill(['archived_at' => now()])->save();

    $this->get('/live')->assertNotFound();
});

it('shows the countdown for an upcoming stream and the player when it is live', function () {
    LiveStream::factory()->upcoming()->create(['title' => 'بث تجريبي قادم']);

    $this->get('/live')->assertOk()
        ->assertSee('بث تجريبي قادم')
        ->assertSee('data-countdown="', false)
        ->assertDontSee('youtube-nocookie.com/embed', false);

    LiveStream::factory()->create(['title' => 'بث تجريبي جارٍ', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

    // الجاري مقدَّم على القادم
    $this->get('/live')->assertOk()
        ->assertSee('بث تجريبي جارٍ')
        ->assertSee('youtube-nocookie.com/embed/abcdefghijk', false)
        ->assertDontSee('data-countdown', false);
});

it('offers a join button for a Teams stream instead of an embedded player', function () {
    LiveStream::factory()->teams()->create();

    $this->get('/live')->assertOk()
        ->assertSee(__('livestream.show.join_teams'))
        ->assertSee('https://teams.microsoft.com/', false)
        ->assertDontSee('<iframe', false);
});

it('announces a live or upcoming stream in the header, and only then', function () {
    $this->get('/')->assertOk()->assertDontSee(__('livestream.header.live'))->assertDontSee(__('livestream.header.upcoming'));

    $stream = LiveStream::factory()->upcoming()->create();
    $this->get('/')->assertSee(__('livestream.header.upcoming'));

    $stream->forceFill(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()])->save();
    $this->get('/')->assertSee(__('livestream.header.live'));

    // أبعد من أيام الإعلان، ثم غير منشور
    $stream->forceFill(['starts_at' => now()->addDays(30), 'ends_at' => now()->addDays(31)])->save();
    $this->get('/')->assertDontSee(__('livestream.header.upcoming'));

    $stream->forceFill(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'is_published' => false])->save();
    $this->get('/')->assertDontSee(__('livestream.header.upcoming'));
});

it('saves a stream through the action with its rules, and keeps outsiders out', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $data = [
        'title' => 'بث',
        'provider' => 'youtube',
        'url' => 'https://youtu.be/abcdefghijk',
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
        'is_published' => true,
    ];

    $stream = app(SaveLiveStream::class)->handle($editor, new LiveStream, $data);

    expect($stream->exists)->toBeTrue()
        ->and($stream->provider)->toBe(LiveStreamProvider::Youtube)
        ->and($stream->status())->toBe(LiveStreamStatus::Upcoming);

    expect(fn () => app(SaveLiveStream::class)->handle($editor, new LiveStream, ['ends_at' => now()->toDateTimeString()] + $data))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveLiveStream::class)->handle($editor, new LiveStream, ['url' => 'https://example.com/video'] + $data))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveLiveStream::class)->handle($editor, new LiveStream, ['provider' => 'teams', 'url' => 'https://evil.example/join'] + $data))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveLiveStream::class)->handle(userWithRole(RoleName::BoardMember), new LiveStream, $data))->toThrow(AuthorizationException::class);

    $teams = app(SaveLiveStream::class)->handle($editor, new LiveStream, ['provider' => 'teams', 'url' => 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_x'] + $data);

    expect($teams->provider)->toBe(LiveStreamProvider::Teams);
});

it('archives an ended YouTube stream into the video library once, unpublished until reviewed', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $stream = LiveStream::factory()->ended()->create(['title' => 'بث انتهى']);

    $item = app(ArchiveLiveStream::class)->handle($editor, $stream);

    expect($item->section)->toBe(LibrarySection::Videos)
        ->and($item->video_url)->toBe($stream->url)
        ->and($item->is_published)->toBeFalse()
        ->and($item->title)->toBe('بث انتهى')
        ->and($stream->fresh()->archived_at)->not->toBeNull()
        ->and($stream->fresh()->library_item_id)->toBe($item->id)
        ->and($stream->fresh()->status())->toBe(LiveStreamStatus::Archived);

    expect(fn () => app(ArchiveLiveStream::class)->handle($editor, $stream->fresh()))->toThrow(ValidationException::class)
        ->and(fn () => app(ArchiveLiveStream::class)->handle($editor, LiveStream::factory()->create()))->toThrow(ValidationException::class)
        ->and(fn () => app(ArchiveLiveStream::class)->handle(userWithRole(RoleName::BoardMember), LiveStream::factory()->ended()->create()))->toThrow(AuthorizationException::class);

    // تيمز لا تسجيل له عند البوابة، فيلزم رابط التسجيل
    $teams = LiveStream::factory()->teams()->ended()->create();

    expect(fn () => app(ArchiveLiveStream::class)->handle($editor, $teams))->toThrow(ValidationException::class);

    $recording = app(ArchiveLiveStream::class)->handle($editor, $teams, 'https://www.youtube.com/watch?v=zyxwvutsrqp');

    expect($recording->video_url)->toBe('https://www.youtube.com/watch?v=zyxwvutsrqp');
});

it('opens the panel to the content editor, lets the board look, and keeps others out', function () {
    $stream = LiveStream::factory()->create();

    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(LiveStreamResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(LiveStreamResource::getUrl('edit', ['record' => $stream]))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(LiveStreamResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(LiveStreamResource::getUrl('create'))->assertForbidden();

    foreach ([RoleName::Secretary, RoleName::ProgramManager, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(LiveStreamResource::getUrl('index'))->assertForbidden();
    }
});

it('creates a stream from the panel through the action', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateLiveStream::class)
        ->fillForm([
            'title' => 'بث من اللوحة',
            'provider' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LiveStream::query()->where('title', 'بث من اللوحة')->exists())->toBeTrue();
});
