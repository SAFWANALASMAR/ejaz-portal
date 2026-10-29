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
use App\Support\FormEntryLayout;
use Database\Seeders\LegacyRedirectsSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
    $this->admin = userWithRole(RoleName::TechnicalAdmin);
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

    // بلا صورة «البكرة» (img-2-png) المحذوفة من صفحة «عن الجمعية» (طلب صفوان، 29 أكتوبر 2026)
    expect(MediaAsset::query()->count())->toBe(74)
        ->and(MediaAsset::query()->where('mime_type', 'image/svg+xml')->count())->toBe(18)
        ->and(MediaAsset::query()->whereNotNull('web_path')->count())->toBe(56)
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
        ->and($home['hero_image_alt'])->toBe('مشاركون بالثوب والشماغ حول طاولات مستديرة في قاعة أمام شاشة عرض')
        ->and(Storage::disk('public')->exists($home['hero_image']))->toBeTrue()
        ->and($contact['email'])->toBe('info@ejaz.sa')
        ->and($contact['phone'])->toBe('+966 55 055 6758')
        ->and($contact['address'])->toBe('مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال')
        // رابط الخريطة القديم يشير إلى العنوان القديم، فلا يُنقل حتى يصل رابط العنوان المعتمد
        ->and($contact)->not->toHaveKey('map_url')
        // والبيانات الرسمية (البند 8، 26 أكتوبر 2026): العنوان الوطني المختصر لرابط الخريطة، ورقم واتساب
        ->and($contact['short_address'])->toBe('MALA4207')
        ->and($contact['whatsapp'])->toBe('+966550556758')
        ->and($contact['social'])->toBe([
            'x' => 'https://x.com/ejazsa2025',
            'instagram' => 'https://instagram.com/ejazsa2025',
            'youtube' => 'https://youtube.com/@ejazsa2025',
            'tiktok' => 'https://tiktok.com/@ejazsa2025',
            'telegram' => 'https://t.me/ejazsa2025',
            'facebook' => 'https://facebook.com/ejazsa2025',
            'whatsapp' => 'https://whatsapp.com/channel/0029VbDMz0kIt5rzj8sZo23U',
        ]);

    // صور الأهداف في بطاقاتها، وصورتا طلب الانضمام زخرفيتان، والبريد المصحَّح. وصورة «البكرة» الخضراء (img-2-png) محذوفة
    // من صفحة «عن الجمعية» ومن الوسائط بطلب صفوان (29 أكتوبر 2026)، فلا تعود عند استيراد الإطلاق
    $about = Page::query()->where('slug', 'about')->sole();
    $join = Page::query()->where('slug', 'assembly-membership')->sole();

    expect(ContentBlocks::images($about->blocks))->toHaveCount(5)
        ->and(json_encode($about->blocks))->not->toContain('img-2')
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
    // الزحف يطلب كل الروابط في دقيقة واحدة، فحد الطلبات (60 في الدقيقة لملفات الوثائق والمكتبة) خارج ما يُختبر هنا
    $this->withoutMiddleware(ThrottleRequests::class);
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
            'contact' => ['email' => 'info@example.com', 'fax' => '0120000000'],
        ],
    ], JSON_UNESCAPED_UNICODE));

    $before = Setting::group('home');

    $this->artisan('import:legacy-site', ['file' => $this->dir.'/broken.json', '--actor' => $this->admin->email])
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => '../outside-'.basename($this->dir).'.png']))
        ->expectsOutputToContain(__('import.errors.media_missing', ['media' => 'ghost']))
        ->expectsOutputToContain('fax')
        ->assertFailed();

    $asset = MediaAsset::query()->sole();

    expect($asset->slug)->toBe('pic')
        ->and(Page::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([$asset->web_path])
        ->and(Setting::group('home'))->toBe($before)
        ->and(Setting::group('contact')['email'] ?? null)->not->toBe('info@example.com');

    File::delete(dirname($this->dir).'/outside-'.basename($this->dir).'.png');
});

it('keeps the missing marks in the import file only where the association has not sent the content', function () {
    $data = json_decode((string) file_get_contents(base_path('docs/legacy-import/legacy-site.json')), true);
    $marks = substr_count((string) file_get_contents(base_path('docs/legacy-import/legacy-site.json')), '【ناقص');

    // واحدة كما في README: عنوان صوتية. وصفحات الاستفسارات والتوظيف والتطوع والتقارير بفقرة «مثال تجريبي»
    // (قرار صفوان، 26 أكتوبر 2026)، و«التوجه الاستراتيجي» الفارغ محذوف
    $pages = collect($data['pages'])->filter(fn (array $page): bool => str_contains((string) json_encode($page, JSON_UNESCAPED_UNICODE), '【ناقص'))->pluck('slug')->sort()->values()->all();
    $library = collect($data['library'])->filter(fn (array $item): bool => str_contains($item['title'], '【ناقص'))->pluck('slug')->all();
    $media = collect($data['media'])->reject(fn (array $item): bool => $item['is_decorative']);

    expect($marks)->toBe(1)
        ->and($pages)->toBe([])
        ->and($library)->toBe(['audio-02'])
        ->and($media)->toHaveCount(45)
        ->and($media->filter(fn (array $item): bool => blank($item['alt']) || str_contains($item['alt'], '【'))->all())->toBe([]);
});

it('shows the approved contact details on the contact page, the footer and the print letterhead after the import', function () {
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();

    $this->get('/contact')->assertOk()
        ->assertSee('+966 55 055 6758')
        ->assertSee('tel:+966550556758', false)
        ->assertSee('mailto:info@ejaz.sa', false)
        ->assertSee('مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال')
        ->assertDontSee(__('contact.info.missing'))
        ->assertSee('https://t.me/ejazsa2025', false)
        ->assertSee(__('settings.social.telegram'))
        ->assertSee(__('site.license'));

    expect(FormEntryLayout::letterhead()['contact'])->toBe([
        'address' => 'مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال',
        'phone' => '+966 55 055 6758',
        'email' => 'info@ejaz.sa',
    ]);

    // الجوال من اليسار في التذييل والترويسة، فلا ينقلب رمز + وأرقامه في السطر العربي
    $this->get('/contact')->assertSee('<bdi dir="ltr">+966 55 055 6758</bdi>', false);

    expect(view('documents.partials.form-letterhead', ['letterhead' => FormEntryLayout::letterhead(), 'logo' => ''])->render())
        ->toContain('<span dir="ltr">+966 55 055 6758</span>')
        ->toContain('<span dir="ltr">info@ejaz.sa</span>')
        ->toContain(__('site.license'));

    // تلغرام كبقية الحسابات: اسم المنصة ورابطها، في التذييل وفي «حساباتنا الرسمية» في الرئيسية
    $this->get('/')->assertOk()
        ->assertSee('href="https://t.me/ejazsa2025"', false)
        ->assertSee(__('settings.social.telegram'));
});

it('adds the missing social accounts one by one, keeps what the editor entered, and refuses an unknown network or an unsafe link', function () {
    Setting::put('contact', ['social' => ['x' => 'https://x.com/editor-choice']]);

    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();

    $social = Setting::group('contact')['social'];

    expect($social['x'])->toBe('https://x.com/editor-choice')
        ->and($social['telegram'])->toBe('https://t.me/ejazsa2025')
        ->and($social)->toHaveCount(7);

    foreach ([['myspace' => 'https://myspace.com/x'], ['x' => 'javascript:alert(1)']] as $bad) {
        File::put($this->dir.'/social.json', json_encode(['settings' => ['contact' => ['social' => $bad]]]));

        $this->artisan('import:legacy-site', ['file' => $this->dir.'/social.json', '--actor' => $this->admin->email])->assertFailed();
    }

    expect(Setting::group('contact')['social'])->toBe($social);
});

// قرار صفوان (29 أكتوبر 2026): صفحة «عن الجمعية» المستوردة قبل #66 بعناوين ممطوطة ومرادفة، ثم ترحيل الخطة الاستراتيجية،
// فتكررت المقاطع. الترحيل الجديد يزيل القديم ويبقي مقاطع الخطة، وتنتقل صور البطاقات إليها
it('removes the old about sections duplicated by the strategic plan migration, keeping the plan and the card pictures', function () {
    Page::query()->where('slug', 'about')->delete();
    $old = Page::factory()->create(['slug' => 'about', 'title' => 'عن الجمعية', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>نص من نحن</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الرســــالة', 'body' => '<p>رسالة قديمة</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الرؤيــــة', 'body' => '<p>رؤية قديمة</p>']],
        ['type' => 'cards', 'data' => ['heading' => 'الأهداف الاستراتيجية', 'items' => [
            ['icon' => null, 'title' => 'هدف قديم', 'text' => null, 'picture' => 'pages/goal-1.png'],
        ]]],
        ['type' => 'cards', 'data' => ['heading' => 'قيمنا', 'items' => [['icon' => 'star', 'title' => 'قيمة قديمة', 'text' => null]]]],
    ]]);
    DB::table('settings')->where('key', 'about_page_before_plan')->delete();

    (require database_path('migrations/2026_10_26_094000_align_about_page_with_strategic_plan.php'))->up();
    $doubled = collect($old->fresh()->blocks)->pluck('data.heading')->all();
    expect($doubled)->toHaveCount(9);

    (require database_path('migrations/2026_10_29_100100_remove_duplicate_about_sections.php'))->up();
    $blocks = collect($old->fresh()->blocks);
    $headings = $blocks->pluck('data.heading')->all();

    expect($headings)->toBe(['من نحن', 'الرؤية', 'الرسالة', 'أهداف الجمعية', 'قيم الجمعية'])
        ->and(array_unique($headings))->toHaveCount(5)
        ->and($blocks->firstWhere('data.heading', 'أهداف الجمعية')['data']['items'][0])->toMatchArray(['title' => 'تعزيز القيم والانتماء الوطني.', 'picture' => 'pages/goal-1.png'])
        ->and($blocks->firstWhere('data.heading', 'قيم الجمعية')['data']['items'][0]['icon'])->toBe('star')
        ->and(json_encode($blocks->all(), JSON_UNESCAPED_UNICODE))->not->toContain('رسالة قديمة')->not->toContain('ـــ');

    // صفحة بلا تكرار لا تُمس، ولا نسخة لها
    DB::table('settings')->where('key', 'about_page_before_dedupe')->delete();
    (require database_path('migrations/2026_10_29_100100_remove_duplicate_about_sections.php'))->up();
    expect(collect($old->fresh()->blocks)->pluck('data.heading')->all())->toBe($headings)
        ->and(DB::table('settings')->where('key', 'about_page_before_dedupe')->exists())->toBeFalse();
});

it('imports the current file and runs both about migrations without a duplicated heading', function () {
    $this->artisan('import:legacy-site', ['file' => $this->file, '--actor' => $this->admin->email])->assertSuccessful();
    DB::table('settings')->where('key', 'about_page_before_plan')->delete();

    (require database_path('migrations/2026_10_26_094000_align_about_page_with_strategic_plan.php'))->up();
    (require database_path('migrations/2026_10_29_100100_remove_duplicate_about_sections.php'))->up();

    $headings = collect(Page::query()->where('slug', 'about')->sole()->blocks)->pluck('data.heading')->filter()->values()->all();
    expect($headings)->toBe(array_values(array_unique($headings)))
        ->and($headings)->toContain('الرؤية')->toContain('قيم الجمعية');
});
