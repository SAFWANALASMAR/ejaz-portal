<?php

use App\Console\Commands\PrunePublicFiles;
use App\Models\Contracts\HasPublicImages;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| حذف ملفات القرص العام غير المرتبطة بأي سجل بعد مدة (docs/plans/prune-public-files.md): صور رُفعت
| ثم فشل حفظ سجلها. ولا يمس المرتبط، ولا الحديث، ولا ما خارج مجلدات السجلات.
*/

beforeEach(function () {
    Storage::fake('public');
    config(['filesystems.public_orphan_days' => 7]);
});

function publicFile(string $path, int $daysOld): string
{
    Storage::disk('public')->put($path, 'image-bytes');
    touch(Storage::disk('public')->path($path), now()->subDays($daysOld)->getTimestamp());

    return $path;
}

it('deletes old files no record points to, and keeps every referenced, recent or foreign file', function () {
    $orphan = publicFile('news/orphan.jpg', 8);
    $orphanSettings = publicFile('settings/old-hero.jpg', 30);

    NewsArticle::factory()->create([
        'image' => publicFile('news/cover.jpg', 30),
        'gallery' => [publicFile('news/gallery-1.jpg', 30)],
    ]);
    Page::factory()->create(['blocks' => [['type' => 'image', 'data' => ['picture' => publicFile('pages/block.jpg', 30)]]]]);
    LibraryItem::factory()->create(['thumbnail' => publicFile('library/thumb.jpg', 30)])->delete();
    Setting::put('home', ['hero_image' => publicFile('settings/hero.jpg', 30)]);
    MediaAsset::query()->create([
        'slug' => 'logo', 'title' => 'شعار', 'original_name' => 'logo.png', 'original_path' => 'media/originals/logo.png',
        'mime_type' => 'image/png', 'size' => 11, 'web_path' => publicFile('media/logo.webp', 30),
    ]);

    $recent = publicFile('news/uploading.jpg', 1);
    $foreign = publicFile('elsewhere/old.jpg', 30);
    $hidden = publicFile('news/.gitignore', 30);

    $this->artisan('ejaz:prune-public-files')->assertSuccessful();

    $disk = Storage::disk('public');
    expect($disk->exists($orphan))->toBeFalse()
        ->and($disk->exists($orphanSettings))->toBeFalse();

    foreach (['news/cover.jpg', 'news/gallery-1.jpg', 'pages/block.jpg', 'library/thumb.jpg', 'settings/hero.jpg', 'media/logo.webp', $recent, $foreign, $hidden] as $kept) {
        expect($disk->exists($kept))->toBeTrue("{$kept} should be kept");
    }
});

it('only counts in a dry run', function () {
    $orphan = publicFile('partners/orphan.png', 8);

    $this->artisan('ejaz:prune-public-files', ['--dry-run' => true])->assertSuccessful();

    expect(Storage::disk('public')->exists($orphan))->toBeTrue();
});

it('covers every model with public images', function () {
    $models = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, HasPublicImages::class))
        ->sort()->values()->all();

    expect($models)->toBe(collect(PrunePublicFiles::MODELS)->sort()->values()->all());
});

it('runs every day on one server', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'ejaz:prune-public-files'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->onOneServer)->toBeTrue();
});
