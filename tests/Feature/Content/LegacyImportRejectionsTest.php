<?php

use App\Enums\RoleName;
use App\Enums\SurveyAudience;
use App\Filament\Pages\SiteSettings;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Survey;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
| مسارات الرفض في أقسام الاستيراد الجديدة (الوسائط والإعدادات وصور المقاطع)، وترحيل القائمة،
| وحقل مقطع الرئيسية، وبطاقة «قياس الرضا» بموعدها.
*/

beforeEach(function () {
    Storage::fake(MediaAsset::disk());
    Storage::fake('public');

    $this->dir = sys_get_temp_dir().'/import-rejections-'.Str::random(8);
    File::ensureDirectoryExists($this->dir.'/export/files');
    File::put($this->dir.'/export/files/ok.png', pngBytes());
    File::put($this->dir.'/outside.png', pngBytes());

    $this->admin = userWithRole(RoleName::SuperAdmin);
});

afterEach(fn () => File::deleteDirectory($this->dir));

function pngBytes(): string
{
    ob_start();
    imagepng(imagecreatetruecolor(4, 4));

    return (string) ob_get_clean();
}

/**
 * @param  array<string, mixed>  $data
 */
function importFile(string $dir, array $data): string
{
    File::put($dir.'/export/import.json', json_encode($data, JSON_UNESCAPED_UNICODE));

    return $dir.'/export/import.json';
}

it('rejects a media file outside the import folder or missing, and keeps nothing of it', function () {
    $file = importFile($this->dir, ['media' => [
        ['slug' => 'outside', 'title' => 'x', 'file' => '../outside.png'],
        ['slug' => 'missing', 'title' => 'x', 'file' => 'files/missing.png'],
        ['slug' => 'ok', 'title' => 'x', 'file' => 'files/ok.png', 'is_decorative' => true],
    ]]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => '../outside.png']))
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => 'files/missing.png']))
        ->assertFailed();

    expect(MediaAsset::query()->pluck('slug')->all())->toBe(['ok']);
});

it('imports a media item once, and a second run leaves it unchanged', function () {
    $file = importFile($this->dir, ['media' => [['slug' => 'ok', 'title' => 'x', 'file' => 'files/ok.png']]]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])->assertSuccessful();
    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('import.outcomes.unchanged'))
        ->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('rejects a page whose block names an unknown media item, and leaves none of its copies behind', function () {
    $file = importFile($this->dir, [
        'media' => [['slug' => 'ok', 'title' => 'x', 'file' => 'files/ok.png', 'is_decorative' => true]],
        'pages' => [['slug' => 'privacy', 'title' => 'صفحة', 'blocks' => [
            ['type' => 'cards', 'data' => ['heading' => null, 'items' => [['title' => 'أ', 'media' => 'ok'], ['title' => 'ب', 'media' => 'ok']]]],
            ['type' => 'image', 'data' => ['media' => 'no-such-media']],
        ]]],
    ]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('import.errors.media_missing', ['media' => 'no-such-media']))
        ->assertFailed();

    expect(Page::query()->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles('pages'))->toBe([]);
});

it('refuses a picture path written in the file, so the import never touches another record image', function () {
    Storage::disk('public')->put('pages/other.webp', 'x');

    $file = importFile($this->dir, ['pages' => [['slug' => 'privacy', 'title' => 'صفحة', 'blocks' => [
        ['type' => 'cards', 'data' => ['heading' => null, 'items' => [['title' => 'أ', 'picture' => 'pages/other.webp']]]],
    ]]]]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])->assertFailed();

    expect(Page::query()->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists('pages/other.webp'))->toBeTrue();
});

it('fills only blank settings, rejects an unknown key and a video that is not on YouTube', function () {
    Setting::put('contact', ['email' => 'editor@example.com']);

    $file = importFile($this->dir, ['settings' => [
        'contact' => ['email' => 'info@ejaz.sa', 'address' => 'العنوان'],
        'home' => ['hero_title' => 'عنوان', 'video_url' => 'https://cdn.example.com/clip.mp4'],
    ]]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('validation.video_url'))
        ->assertFailed();

    expect(Setting::group('contact'))->toBe(['email' => 'editor@example.com', 'address' => 'العنوان'])
        ->and(Setting::group('home'))->toBe([]);

    $unknown = importFile($this->dir, ['settings' => ['home' => ['stats' => []]]]);
    $this->artisan('import:legacy-site', ['file' => $unknown, '--actor' => $this->admin->email])->assertFailed();
});

it('refuses the whole run before any change when the actor cannot add media or change settings', function () {
    $actor = userWithRole(RoleName::ProgramManager);
    $file = importFile($this->dir, [
        'media' => [['slug' => 'ok', 'title' => 'x', 'file' => 'files/ok.png']],
        'settings' => ['home' => ['hero_title' => 'عنوان']],
    ]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $actor->email])
        ->expectsOutputToContain(__('import.sections.media'))
        ->assertFailed();

    expect(MediaAsset::query()->exists())->toBeFalse()->and(Setting::group('home'))->toBe([]);
});

it('adds the old about submenu once with its document links hidden, and fixes the four broken default links', function () {
    $about = MenuItem::create(['label' => 'عن الجمعية', 'url' => '/about', 'sort' => 1]);
    MenuItem::create(['label' => 'الأخبار', 'url' => '/news', 'sort' => 2]);

    $migration = require database_path('migrations/2026_10_11_090100_add_legacy_about_items_to_main_menu.php');
    $migration->up();
    $migration->up();

    $children = MenuItem::query()->where('parent_id', $about->id)->orderBy('sort')->get();

    expect($children)->toHaveCount(10)
        ->and($children->where('is_visible', false)->pluck('url')->every(fn (string $url): bool => str_starts_with($url, '/governance/')))->toBeTrue()
        ->and($children->where('is_visible', false))->toHaveCount(7)
        ->and(MenuItem::query()->where('label', 'الأخبار')->value('url'))->toBe('/media/news');

    $migration->down();

    expect(MenuItem::query()->where('parent_id', $about->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->where('label', 'الأخبار')->value('url'))->toBe('/news');
});

it('accepts only a YouTube link for the home video in the settings screen', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->set('data.home.video_url', 'https://cdn.example.com/clip.mp4')
        ->call('save')
        ->assertHasErrors(['data.home.video_url']);
});

it('shows and hides the survey tile at the survey times, without waiting for the cache', function () {
    Survey::factory()->create(['audience' => SurveyAudience::General, 'opens_at' => now()->addHour(), 'closes_at' => now()->addHours(3)]);

    $this->get('/media')->assertOk()->assertDontSee(__('media.tiles.surveys'));

    $this->travel(2)->hours();
    $this->get('/media')->assertOk()->assertSee(__('media.tiles.surveys'));

    $this->travel(2)->hours();
    $this->get('/media')->assertOk()->assertDontSee(__('media.tiles.surveys'));
});
