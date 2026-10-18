<?php

use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\ContentBlocks;
use Database\Seeders\LegacyRedirectsSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| نقل أرشيف الموقع السابق (docs/legacy-import): ملف الاستيراد الفعلي بملفات بديلة صغيرة بأسمائها
| وأنواعها (الأصلية خارج git)، ثم جدول التحويلات redirects.csv كاملاً، ثم كل رابط داخلي في الموقع.
*/

beforeEach(function () {
    Storage::fake(Document::disk());
    Storage::fake('public');

    $this->dir = sys_get_temp_dir().'/archive-import-'.Str::random(8);
    $this->file = archiveImportFixture($this->dir);
    $this->admin = userWithRole(RoleName::SuperAdmin);
});

afterEach(fn () => File::deleteDirectory($this->dir));

/**
 * نسخة من legacy-site.json في مجلد مؤقت، ولكل ملف فيه ملف بديل صغير بنوعه.
 */
function archiveImportFixture(string $dir): string
{
    $json = (string) file_get_contents(base_path('docs/legacy-import/legacy-site.json'));
    $data = json_decode($json, true);

    foreach ([...$data['media'], ...$data['documents'], ...$data['library']] as $record) {
        if (! is_string($record['file'] ?? null)) {
            continue;
        }

        $path = $dir.'/'.$record['file'];
        File::ensureDirectoryExists(dirname($path));
        File::put($path, match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => pdfContents(),
            'mp3' => mp3Contents(),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>',
            default => imageBytes(strtolower(pathinfo($path, PATHINFO_EXTENSION))),
        });
    }

    File::put($dir.'/legacy-site.json', $json);

    return $dir.'/legacy-site.json';
}

/**
 * ينشر كل المستورد، كما ستفعل الجمعية بعد المراجعة، ليُفحص الموقع كاملاً.
 */
function publishEverything(): void
{
    foreach ([Page::class, Initiative::class, Document::class, LibraryItem::class] as $model) {
        $model::query()->update(['is_published' => true]);
    }

    // التحديث الجماعي بلا أحداث النموذج، فبعده أمر النشر كما بعد تعديل القاعدة مباشرة: ذاكرة المحتوى والصفحات المنشورة
    Artisan::call('ejaz:refresh-content');
}

it('imports the whole archive as drafts with the counts of the archive', function () {
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])
        ->expectsOutputToContain('رُفض 0')
        ->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(75)
        ->and(MediaAsset::query()->where('mime_type', 'image/svg+xml')->count())->toBe(18)
        ->and(MediaAsset::query()->whereNotNull('web_path')->count())->toBe(57)
        ->and(MediaAsset::query()->where('from_template', true)->where('mime_type', '!=', 'image/svg+xml')->count())->toBe(30)
        ->and(Page::query()->pluck('slug')->sort()->values()->all())->toBe(['about', 'assembly-membership', 'contact', 'inquiries', 'jobs', 'reports', 'volunteering'])
        ->and(Initiative::query()->count())->toBe(5)
        ->and(Document::query()->count())->toBe(14)
        ->and(LibraryItem::query()->where('section', LibrarySection::Audio)->count())->toBe(30)
        ->and(LibraryItem::query()->where('section', LibrarySection::Videos)->whereNotNull('video_url')->count())->toBe(20)
        ->and(LibraryItem::query()->whereIn('section', [LibrarySection::Books, LibrarySection::Research])->exists())->toBeFalse();

    // كله مسودات
    foreach ([Page::class, Initiative::class, Document::class, LibraryItem::class] as $model) {
        expect($model::query()->where('is_published', true)->exists())->toBeFalse();
    }

    // كل صورة نسختها للويب WebP والأصل في القرص الخاص، والأيقونة SVG أصل وحده
    MediaAsset::query()->each(function (MediaAsset $asset): void {
        expect(Storage::disk(MediaAsset::disk())->exists($asset->original_path))->toBeTrue();

        if ($asset->mime_type !== 'image/svg+xml') {
            expect($asset->web_path)->toEndWith('.webp')
                ->and(Storage::disk('public')->exists((string) $asset->web_path))->toBeTrue();
        }
    });
});

it('applies the archive corrections: audio order and titles, videos, documents, pages and settings', function () {
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();

    $audio = LibraryItem::query()->where('section', LibrarySection::Audio)->orderBy('slug')->pluck('title')->all();

    expect($audio[0])->toBe('الإيمان فطرة والعبادة شفاء')
        ->and($audio[1])->toBe('【ناقص: عنوان】')
        ->and($audio[29])->toBe('أخفض منطقة في البسيطة')
        ->and(LibraryItem::query()->where('title', 'أعماق المحيطات')->exists())->toBeFalse()
        ->and(LibraryItem::query()->where('slug', 'video-01')->value('video_url'))->toBe('https://youtu.be/7YYsS0_hoqM')
        ->and(LibraryItem::query()->where('slug', 'video-20')->value('title'))->toBe('مرج البحرين يلتقيان بينهما برزخٌ لا يبغيان')
        ->and(Document::query()->where('slug', 'disclosure-document')->value('title'))->toBe('وثيقة الافصاح')
        ->and(Document::query()->where('slug', 'no-branches-statement')->value('title'))->toBe('افادة بعدم وجود فروع أو مكاتب للجمعية');

    $home = Setting::group('home');
    $contact = Setting::group('contact');

    expect($home['hero_title'])->toBe('جمعية إعجاز للدراسات والبحوث العلمية')
        ->and($home['hero_button_url'])->toBe('/about')
        ->and($home['video_url'])->toBe('https://youtube.com/watch?v=WaSvMAl4zPw')
        ->and($home['hero_image'])->toStartWith('settings/')
        ->and($home['hero_image_alt'])->toBe('【ناقص: نص بديل】')
        ->and(Storage::disk('public')->exists($home['hero_image']))->toBeTrue()
        ->and($contact['email'])->toBe('info@ejaz.sa')
        ->and($contact['map_url'])->toBe('https://maps.app.goo.gl/kYLnBrC7bKCu9WxJ6');

    // صور الأهداف في بطاقاتها، وصورتا طلب الانضمام زخرفيتان، والبريد المصحَّح
    $about = Page::query()->where('slug', 'about')->sole();
    $join = Page::query()->where('slug', 'assembly-membership')->sole();

    expect(ContentBlocks::images($about->blocks))->toHaveCount(6)
        ->and(collect($join->blocks)->where('type', 'image')->pluck('data.alt')->all())->toBe(['', ''])
        ->and(json_encode($join->blocks, JSON_UNESCAPED_UNICODE))->toContain('mailto:info@ejaz.sa')->not->toContain('ejaz.org');

    // ولا نص إنجليزي من القالب، ولا رابط إلى الموقع السابق
    $content = json_encode([Page::all()->toArray(), Initiative::all()->toArray(), Setting::group('home')], JSON_UNESCAPED_UNICODE);

    foreach (["Let's talk", 'Business Insight', 'Testimonials', '2.3x', '98%', '168m', 'https://ejaz.sa', 'http://ejaz.sa'] as $text) {
        expect($content)->not->toContain($text);
    }
});

it('leaves nothing behind in a dry run', function () {
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email, '--dry-run' => true])
        ->expectsOutputToContain('رُفض 0')
        ->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(0)
        ->and(Page::query()->count())->toBe(0)
        ->and(Redirect::query()->count())->toBe(0)
        ->and(Setting::group('home'))->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk(Document::disk())->allFiles())->toBe([]);
});

it('sends every old link in redirects.csv permanently to a page that answers', function () {
    $this->seed(LegacyRedirectsSeeder::class);
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();
    publishEverything();

    $rows = array_map(str_getcsv(...), array_slice(file(base_path('docs/legacy-import/redirects.csv'), FILE_IGNORE_NEW_LINES), 1));

    expect($rows)->toHaveCount(154);

    foreach ($rows as [$source, $target, $kind]) {
        $url = implode('/', array_map(rawurlencode(...), explode('/', (string) strtok($source, '?')))).(str_contains($source, '?') ? '?'.Str::after($source, '?') : '');

        if ($kind === 'served') {
            $this->get($url)->assertOk();

            continue;
        }

        $response = $this->get($url);
        expect($response->status())->toBe(301, "{$source} ({$kind})");
        expect($response->headers->get('Location'))->toBe(url($target), $source);
        $this->get($target)->assertOk();
    }
});

it('keeps the WordPress admin paths a plain 404', function () {
    $this->get('/wp-admin/')->assertNotFound();
    $this->get('/wp-login.php')->assertNotFound();
    $this->get('/no-such-page')->assertNotFound()->assertSee(__('errors.404.title'));
});

it('answers every internal link of the published site without a 404', function () {
    $this->seed(MenuSeeder::class);
    $this->seed(LegacyRedirectsSeeder::class);
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();
    publishEverything();

    $base = rtrim(url('/'), '/');
    $queue = ['/'];
    $seen = [];

    while ($queue !== [] && count($seen) < 400) {
        $path = array_shift($queue);

        if (isset($seen[$path])) {
            continue;
        }

        // ملف ثابت في public (الأيقونة المفضلة والصور) يقدّمه خادم الويب لا لارافيل
        if (preg_match('#^/[^?]+\.(ico|png|svg|webp|jpg|css|js)$#', $path) === 1) {
            $seen[$path] = is_file(public_path(ltrim($path, '/'))) ? 200 : 404;

            continue;
        }

        $response = $this->get($path);
        $seen[$path] = $response->getStatusCode();

        if ($response->isRedirect()) {
            $queue[] = Str::after((string) $response->headers->get('Location'), $base) ?: '/';

            continue;
        }

        expect($response->getStatusCode())->toBe(200, $path);

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            continue;
        }

        preg_match_all('/(?:href|src)="([^"#]+)/', (string) $response->getContent(), $links);

        foreach ($links[1] as $link) {
            $link = html_entity_decode($link);

            if (str_starts_with($link, $base.'/')) {
                $link = Str::after($link, $base);
            }

            // الموقع العام وحده: لا اللوحة ولا الحساب ولا ملفات البناء
            if (str_starts_with($link, '/') && ! str_starts_with($link, '//') && ! preg_match('#^/(admin|account|livewire|build|storage|logout)#', $link)) {
                $queue[] = $link;
            }
        }
    }

    expect(count($seen))->toBeGreaterThan(100)
        ->and(array_filter($seen, fn (int $status): bool => $status >= 400))->toBe([]);
});

it('rejects a media file outside the folder, a missing media item, an unknown setting and a non-YouTube home video, leaving no copies behind', function () {
    File::put($this->dir.'/files/pic.png', imageBytes('png'));
    File::put(dirname($this->dir).'/outside-'.basename($this->dir).'.png', imageBytes('png'));
    File::put($this->dir.'/broken.json', json_encode([
        'media' => [
            ['slug' => 'pic', 'file' => 'files/pic.png', 'title' => 'صورة تجريبية'],
            ['slug' => 'outside', 'file' => '../outside-'.basename($this->dir).'.png', 'title' => 'صورة تجريبية'],
        ],
        // الصورة الأولى توضع ثم يُرفض السجل بالثانية، فلا تبقى نسختها
        'pages' => [['slug' => 'with-ghost', 'title' => 'صفحة تجريبية', 'blocks' => [
            ['type' => 'image', 'data' => ['media' => 'pic']],
            ['type' => 'image', 'data' => ['media' => 'ghost']],
        ]]],
        'settings' => [
            'home' => ['video_url' => 'https://cdn.example.com/clip.mp4'],
            'contact' => ['email' => 'info@example.com', 'phone' => '0500000000'],
        ],
    ], JSON_UNESCAPED_UNICODE));

    $before = Setting::group('home');

    $this->artisan('import:legacy-site', ['file' => $this->dir.'/broken.json', '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => '../outside-'.basename($this->dir).'.png']))
        ->expectsOutputToContain(__('import.errors.media_missing', ['media' => 'ghost']))
        ->expectsOutputToContain('phone')
        ->assertFailed();

    $asset = MediaAsset::query()->sole();

    expect($asset->slug)->toBe('pic')
        ->and(Page::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([$asset->web_path])
        ->and(Setting::group('home'))->toBe($before)
        ->and(Setting::group('contact')['email'] ?? null)->not->toBe('info@example.com');

    File::delete(dirname($this->dir).'/outside-'.basename($this->dir).'.png');
});
