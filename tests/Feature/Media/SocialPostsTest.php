<?php

use App\Actions\Content\ImportSocialPosts;
use App\Enums\RoleName;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Filament\Resources\SocialPosts\Pages\CreateSocialPost;
use App\Filament\Resources\SocialPosts\Pages\EditSocialPost;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Models\MenuItem;
use App\Models\SocialPost;
use App\Models\User;
use App\Rules\SocialPostUrl;
use App\Support\MenuSections;
use Database\Seeders\DemoSocialPostsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;

/*
| «منشوراتنا» في المركز الإعلامي (طلب صفوان، 3 نوفمبر 2026 — docs/decisions/2026-11-04-social-posts.md): الصلاحيات، ورابط
| المنشور من نطاق منصته وحده، والاستيراد مسوداتٍ برفض المخالف والمكرر، والصفحة العامة للمنشور وحده بلا تضمين.
*/

beforeEach(function () {
    Storage::fake('public');
});

/**
 * ملف CSV بصف العناوين ثم الصفوف.
 *
 * @param  list<list<string>>  $rows
 */
function socialCsv(array $rows): string
{
    $lines = array_map(fn (array $cells): string => implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"', $cells)), [ImportSocialPosts::headings(), ...$rows]);

    return "\u{FEFF}".implode("\n", $lines)."\n";
}

/**
 * ملف إكسل بصف العناوين ثم الصفوف، ومسار نسخته المؤقتة.
 *
 * @param  list<list<mixed>>  $rows
 */
function socialXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'social').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    // خلية التاريخ بتنسيق تاريخ كما يحفظها إكسل
    $dates = (new Style)->setFormat('yyyy-mm-dd');

    foreach ([ImportSocialPosts::headings(), ...$rows] as $cells) {
        $writer->addRow(Row::fromValues($cells, $dates));
    }

    $writer->close();

    return $path;
}

/**
 * @param  list<list<string>>  $rows
 * @return array{created: int, rejected: list<array{row: int, reason: string}>}
 */
function importSocialCsv(array $rows, ?User $actor = null): array
{
    $path = tempnam(sys_get_temp_dir(), 'social');
    file_put_contents($path, socialCsv($rows));

    try {
        return app(ImportSocialPosts::class)->handle($actor ?? userWithRole(RoleName::ContentEditor), $path, 'csv');
    } finally {
        @unlink($path);
    }
}

it('opens the screen to the content editor and the system managers, and keeps the board and the secretary out', function () {
    foreach ([RoleName::ContentEditor, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin] as $role) {
        $this->actingAs(userWithRole($role))->get(SocialPostResource::getUrl('index'))->assertOk();
        $this->actingAs(userWithRole($role))->get(SocialPostResource::getUrl('create'))->assertOk();
    }

    foreach ([RoleName::BoardMember, RoleName::Secretary] as $role) {
        $this->actingAs(userWithRole($role))->get(SocialPostResource::getUrl('index'))->assertForbidden();
    }

    $post = SocialPost::factory()->create();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(SocialPostResource::getUrl('edit', ['record' => $post]))->assertForbidden();
});

it('creates a post with its image as a logged draft, and shows it once published', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateSocialPost::class)
        ->fillForm([
            'platform' => SocialPlatform::Instagram->value,
            'kind' => SocialPostKind::Event->value,
            'posted_on' => '2026-10-20',
            'url' => 'https://www.instagram.com/p/ABC123/',
            'body' => 'نص منشور الاختبار',
            'image' => [UploadedFile::fake()->image('post.jpg', 800, 500)],
            'is_published' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = SocialPost::query()->sole();

    expect($post->url)->toBe('https://instagram.com/p/ABC123')
        ->and($post->uuid)->not->toBeEmpty()
        ->and($post->image)->toStartWith(SocialPost::IMAGE_DIRECTORY.'/')
        ->and(Storage::disk('public')->exists($post->image))->toBeTrue()
        ->and(Activity::query()->whereMorphedTo('subject', $post)->where('event', 'created')->exists())->toBeTrue();

    $this->get(route('media.social'))->assertOk()->assertDontSee('نص منشور الاختبار');

    $post->update(['is_published' => true]);

    $this->get(route('media.social'))->assertOk()->assertSee('نص منشور الاختبار');
});

it('rejects a post link that is not https on the chosen platform', function (string $platform, string $url) {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateSocialPost::class)
        ->fillForm(['platform' => $platform, 'kind' => 'post', 'posted_on' => '2026-10-20', 'url' => $url, 'body' => 'نص'])
        ->call('create')
        ->assertHasFormErrors(['url']);

    expect(SocialPost::query()->count())->toBe(0);
})->with([
    'http' => ['x', 'http://x.com/ejaz/status/1'],
    'another platform' => ['x', 'https://www.instagram.com/p/1'],
    'look-alike host' => ['x', 'https://x.com.evil.example/status/1'],
    'suffix without a dot' => ['x', 'https://evilx.com/status/1'],
    'user before the host' => ['instagram', 'https://instagram.com@evil.example/p/1'],
    'javascript' => ['youtube', 'javascript:alert(1)'],
    'encoded host' => ['facebook', 'https://evil.example%2F.facebook.com/p'],
    'port' => ['x', 'https://x.com:8443/ejaz/status/1'],
    'port zero' => ['x', 'https://x.com:0/ejaz/status/1'],
]);

it('accepts the platform hosts and their subdomains', function (string $platform, string $url) {
    expect(SocialPostUrl::matches($url, SocialPlatform::from($platform)))->toBeTrue();
})->with([
    ['x', 'https://twitter.com/ejaz/status/1'],
    ['youtube', 'https://youtu.be/abc'],
    ['youtube', 'https://m.youtube.com/watch?v=abc'],
    ['facebook', 'https://m.facebook.com/story.php?id=1'],
    ['whatsapp', 'https://whatsapp.com/channel/abc'],
    ['snapchat', 'https://www.snapchat.com/add/ejaz'],
    ['tiktok', 'https://vm.tiktok.com/abc'],
    ['linkedin', 'https://www.linkedin.com/posts/abc'],
]);

it('refuses a second post with the same link, and lets a deleted one be entered again', function () {
    $existing = SocialPost::factory()->create(['url' => 'https://x.com/ejaz/status/42']);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $form = ['platform' => 'x', 'kind' => 'post', 'posted_on' => '2026-10-20', 'url' => 'https://x.com/ejaz/status/42/', 'body' => 'نص'];

    Livewire::test(CreateSocialPost::class)->fillForm($form)->call('create')->assertHasFormErrors(['url']);

    Livewire::test(ListSocialPosts::class)->callAction(TestAction::make('delete')->table($existing));
    expect(SocialPost::withTrashed()->find($existing->id)?->trashed())->toBeTrue();

    Livewire::test(CreateSocialPost::class)->fillForm($form)->call('create')->assertHasNoFormErrors();
});

it('answers a link saved by an import between the form check and the save with the duplicate message, not a server error', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));
    $other = SocialPost::factory()->create(['url' => 'https://x.com/ejaz/status/7']);

    // سباق: صف بالرابط نفسه يُدخَل بعد فحص النموذج وقبل حفظه، كاستيراد في اللحظة نفسها
    $race = fn (SocialPost $post) => DB::table('social_posts')->insert([
        'uuid' => (string) Str::uuid(), 'platform' => 'x', 'kind' => 'post', 'body' => 'مستورد', 'posted_on' => '2026-10-20',
        'url' => $post->url, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    SocialPost::creating($race);
    SocialPost::updating($race);

    $form = ['platform' => 'x', 'kind' => 'post', 'posted_on' => '2026-10-20', 'url' => 'https://x.com/ejaz/status/42', 'body' => 'نص'];
    // ويرجع الصف المُدخَل مع الحفظ الفاشل لأنه في معاملته نفسها، فالمهم رسالة الحقل لا خطأ الخادم
    Livewire::test(CreateSocialPost::class)->fillForm($form)->call('create')->assertHasFormErrors(['url']);
    expect(SocialPost::query()->where('url', 'https://x.com/ejaz/status/42')->exists())->toBeFalse();

    Livewire::test(EditSocialPost::class, ['record' => $other->getRouteKey()])
        ->fillForm(['url' => 'https://x.com/ejaz/status/43'])->call('save')->assertHasFormErrors(['url']);
    expect($other->fresh()?->url)->toBe('https://x.com/ejaz/status/7');
});

it('imports valid rows as drafts and reports the rejected and duplicate rows with their reasons', function () {
    SocialPost::factory()->create(['url' => 'https://x.com/ejaz/status/1']);

    $result = importSocialCsv([
        ['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/2', 'منشور مستورد', ''],
        ['instagram', 'حدث', '2026-10-02', 'https://www.instagram.com/p/XYZ/', 'حدث مستورد', 'https://cdn.example.com/a.jpg'],
        ['ماستودون', 'منشور', '2026-10-03', 'https://x.com/ejaz/status/3', 'منصة مجهولة', ''],
        ['إكس', 'تغريدة', '2026-10-03', 'https://x.com/ejaz/status/4', 'نوع مجهول', ''],
        ['إكس', 'منشور', '03/10/2026', 'https://x.com/ejaz/status/5', 'تاريخ خاطئ', ''],
        ['إكس', 'منشور', '2026-10-03', 'https://evil.example/status/6', 'رابط خارج المنصة', ''],
        ['إكس', 'منشور', '2026-10-03', 'https://x.com/ejaz/status/1', 'مكرر في القاعدة', ''],
        ['إكس', 'منشور', '2026-10-03', 'https://x.com/ejaz/status/2/', 'مكرر في الملف', ''],
        ['إكس', 'منشور', '2026-10-03', 'https://x.com/ejaz/status/7', '', ''],
        ['', '', '', '', '', ''],
    ]);

    expect($result['created'])->toBe(2)
        ->and(array_column($result['rejected'], 'row'))->toBe([4, 5, 6, 7, 8, 9, 10])
        ->and($result['rejected'][0]['reason'])->toBe(__('social_posts.import.platform', ['platforms' => ImportSocialPosts::platforms()]))
        ->and($result['rejected'][1]['reason'])->toBe(__('social_posts.import.kind'))
        ->and($result['rejected'][3]['reason'])->toContain('x.com')
        ->and($result['rejected'][4]['reason'])->toBe(__('social_posts.import.duplicate'))
        ->and($result['rejected'][5]['reason'])->toBe(__('social_posts.import.duplicate'));

    $imported = SocialPost::query()->where('url', 'https://instagram.com/p/XYZ')->sole();

    expect($imported->is_published)->toBeFalse()
        ->and($imported->platform)->toBe(SocialPlatform::Instagram)
        ->and($imported->kind)->toBe(SocialPostKind::Event)
        ->and($imported->posted_on->toDateString())->toBe('2026-10-02')
        ->and($imported->image)->toBeNull()
        ->and($imported->source_image_url)->toBe('https://cdn.example.com/a.jpg')
        ->and(SocialPost::query()->published()->count())->toBe(1);
});

it('reads an Excel file with a date cell', function () {
    $path = socialXlsx([[SocialPlatform::YouTube->getLabel(), 'منشور', new DateTimeImmutable('2026-09-15'), 'https://youtu.be/abc', 'مقطع مستورد', '']]);

    try {
        $result = app(ImportSocialPosts::class)->handle(userWithRole(RoleName::ContentEditor), $path, 'xlsx');
    } finally {
        @unlink($path);
    }

    expect($result)->toBe(['created' => 1, 'rejected' => []])
        ->and(SocialPost::query()->sole()->posted_on->toDateString())->toBe('2026-09-15');
});

it('imports through the admin action, and refuses an empty or unreadable file', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(ListSocialPosts::class)
        ->callAction('import', data: ['file' => UploadedFile::fake()->createWithContent('posts.csv', socialCsv([
            ['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/9', 'من اللوحة', ''],
            ['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/9', 'مكرر', ''],
        ]))])
        ->assertHasNoFormErrors()
        // الإشعار الأول وحده: assertNotified يفرغ الإشعارات بعد قراءتها
        ->assertNotified(__('social_posts.import.done', ['count' => 1]));

    expect(SocialPost::query()->count())->toBe(1);
    // والملف المرفوع لا يبقى في مجلد الرفع المؤقت
    expect(FileUploadConfiguration::storage()->allFiles(FileUploadConfiguration::directory()))->toBe([]);

    expect(fn () => importSocialCsv([]))->toThrow(ValidationException::class, __('social_posts.import.empty'));

    $path = tempnam(sys_get_temp_dir(), 'social');
    file_put_contents($path, 'not a zip');
    expect(fn () => app(ImportSocialPosts::class)->handle(userWithRole(RoleName::ContentEditor), $path, 'xlsx'))
        ->toThrow(ValidationException::class, __('social_posts.import.unreadable'));
    @unlink($path);
});

it('does not let a user without the permission import, nor show the import buttons to the read-only super admin', function () {
    expect(fn () => importSocialCsv([['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/9', 'نص', '']], userWithRole(RoleName::Secretary)))
        ->toThrow(AuthorizationException::class);

    expect(SocialPost::query()->count())->toBe(0);

    // المشرف العام اطلاع فقط: يرى القائمة بلا إنشاء ولا استيراد
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    $this->get(SocialPostResource::getUrl('index'))->assertOk();
    $this->get(SocialPostResource::getUrl('create'))->assertForbidden();
    Livewire::test(ListSocialPosts::class)->assertActionHidden('import')->assertActionHidden('template');
});

it('counts every copy of the same post as one link: tracking parameters, letter case, www, m. and twitter.com', function () {
    SocialPost::factory()->create(['url' => 'https://www.instagram.com/p/ABC/?igsh=first']);

    $result = importSocialCsv([
        ['إنستغرام', 'منشور', '2026-10-01', 'https://instagram.com/p/ABC?igsh=second', 'نسخة', ''],
        ['إنستغرام', 'منشور', '2026-10-01', 'https://WWW.Instagram.com/p/ABC/#comments', 'نسخة', ''],
        ['إكس', 'منشور', '2026-10-01', 'https://twitter.com/ejaz/status/5?s=20&t=abc', 'تغريدة', ''],
        ['تويتر', 'منشور', '2026-10-01', 'https://mobile.x.com/ejaz/status/5?s=46', 'تغريدة', ''],
        ['يوتيوب', 'منشور', '2026-10-01', 'https://m.youtube.com/watch?v=abc&feature=share', 'مقطع', ''],
        ['يوتيوب', 'منشور', '2026-10-01', 'https://www.youtube.com/watch?si=x&v=def', 'مقطع آخر', ''],
    ]);

    expect($result['created'])->toBe(3)
        ->and(array_column($result['rejected'], 'row'))->toBe([2, 3, 5])
        ->and(SocialPost::query()->pluck('url')->sort()->values()->all())->toBe([
            'https://instagram.com/p/ABC',
            'https://x.com/ejaz/status/5',
            'https://youtube.com/watch?v=abc',
            'https://youtube.com/watch?v=def',
        ]);
});

it('refuses redirect pages that lead off the platform', function (string $platform, string $url) {
    expect(SocialPostUrl::matches(SocialPostUrl::normalize($url), SocialPlatform::from($platform)))->toBeFalse();
})->with([
    ['facebook', 'https://l.facebook.com/l.php?u=https://evil.example'],
    ['instagram', 'https://l.instagram.com/?u=https://evil.example'],
    ['youtube', 'https://www.youtube.com/redirect?q=https://evil.example'],
    ['linkedin', 'https://www.linkedin.com/redir/redirect?url=https://evil.example'],
    'doubled slash' => ['youtube', 'https://youtube.com//redirect?q=1'],
    'encoded letter' => ['youtube', 'https://youtube.com/%72edirect?q=1'],
    'doubled slash on linkedin' => ['linkedin', 'https://www.linkedin.com//redir/redirect?url=x'],
    'facebook warning page' => ['facebook', 'https://facebook.com/flx/warn/?u=https://evil.example'],
    'youtube attribution link' => ['youtube', 'https://youtube.com/attribution_link?u=https://evil.example'],
]);

it('stops at a far row number in a small Excel file instead of reading the blank rows before it', function () {
    $path = socialXlsx([['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/1', 'نص', '']]);

    // ورقة صغيرة صفها الثاني برقم ملياري
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->addFromString('xl/worksheets/sheet1.xml', (string) preg_replace(['/<row r="2"/', '/r="([A-F])2"/'], ['<row r="2000000000"', 'r="${1}2000000000"'], $sheet));
    $zip->close();

    $started = microtime(true);
    expect(fn () => app(ImportSocialPosts::class)->handle(userWithRole(RoleName::ContentEditor), $path, 'xlsx'))
        ->toThrow(ValidationException::class, __('social_posts.import.too_many', ['max' => ImportSocialPosts::MAX_ROWS]));
    expect(microtime(true) - $started)->toBeLessThan(30.0);
    @unlink($path);
});

it('reports the real file row after a blank row, reads a semicolon CSV, and refuses a file without the template header', function () {
    $path = tempnam(sys_get_temp_dir(), 'social');
    $header = implode(';', ImportSocialPosts::headings());
    file_put_contents($path, "\u{FEFF}{$header}\nإكس;منشور;2026-10-01;https://x.com/ejaz/status/1;نص;\n\n\nإكس;منشور;خطأ;https://x.com/ejaz/status/2;نص;\n");

    $result = app(ImportSocialPosts::class)->handle(userWithRole(RoleName::ContentEditor), $path, 'csv');

    expect($result['created'])->toBe(1)
        ->and(array_column($result['rejected'], 'row'))->toBe([5]);

    file_put_contents($path, "إكس,منشور,2026-10-01,https://x.com/ejaz/status/3,نص,\n");

    expect(fn () => app(ImportSocialPosts::class)->handle(userWithRole(RoleName::ContentEditor), $path, 'csv'))
        ->toThrow(ValidationException::class, __('social_posts.import.header', ['columns' => implode('، ', ImportSocialPosts::headings())]));

    @unlink($path);
});

it('refuses more than the row limit, and records the importer as the causer', function () {
    $rows = array_map(fn (int $i): array => ['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/'.$i, 'نص', ''], range(1, ImportSocialPosts::MAX_ROWS + 1));

    expect(fn () => importSocialCsv($rows))->toThrow(ValidationException::class, __('social_posts.import.too_many', ['max' => ImportSocialPosts::MAX_ROWS]));
    expect(SocialPost::query()->count())->toBe(0);

    $editor = userWithRole(RoleName::ContentEditor);
    importSocialCsv([['إكس', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/1', 'نص', '']], $editor);

    expect(Activity::query()->whereMorphedTo('subject', SocialPost::query()->sole())->where('event', 'created')->value('causer_id'))->toBe($editor->id);
});

it('lists the rejected rows in the admin notification and downloads the template', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(ListSocialPosts::class)
        ->callAction('import', data: ['file' => UploadedFile::fake()->createWithContent('posts.csv', socialCsv([
            ['ماستودون', 'منشور', '2026-10-01', 'https://x.com/ejaz/status/1', 'نص', ''],
        ]))])
        ->assertNotified(__('social_posts.import.rejected_title', ['count' => 1]));

    Livewire::test(ListSocialPosts::class)->callAction('template')->assertFileDownloaded('social-posts-template.xlsx');
});

it('shows the published posts only, newest first, with the platform, date, excerpt and a safe outbound link', function () {
    SocialPost::factory()->create(['body' => 'منشور أقدم', 'posted_on' => '2026-09-01', 'url' => 'https://x.com/ejaz/status/100']);
    SocialPost::factory()->create(['platform' => SocialPlatform::Instagram, 'kind' => SocialPostKind::Event, 'body' => 'حدث أحدث', 'posted_on' => '2026-10-01', 'url' => 'https://www.instagram.com/p/new']);
    SocialPost::factory()->unpublished()->create(['body' => 'مسودة منشور']);
    SocialPost::factory()->create(['body' => 'منشور محذوف'])->delete();

    $html = (string) $this->get(route('media.social'))->assertOk()
        ->assertSeeInOrder(['حدث أحدث', 'منشور أقدم'])
        ->assertDontSee('مسودة منشور')
        ->assertDontSee('منشور محذوف')
        ->assertSee(SocialPostKind::Event->getLabel())
        ->assertSee('datetime="2026-10-01"', false)
        ->getContent();

    expect($html)->toContain('href="https://instagram.com/p/new" target="_blank" rel="noopener noreferrer"')
        ->and($html)->not->toContain('<iframe')
        ->and($html)->not->toMatch('#<script[^>]+src="https?://(?!'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST), '#').')#');
});

it('hides the outbound link of a post whose stored link left its platform', function () {
    $post = SocialPost::factory()->create(['body' => 'رابط معدّل']);
    SocialPost::query()->whereKey($post->id)->toBase()->update(['url' => 'https://evil.example/x']);

    $this->get(route('media.social'))->assertOk()->assertSee('رابط معدّل')->assertDontSee('evil.example');
});

it('filters by platform and kind, pages through, and sends a wrong filter back to the list', function () {
    SocialPost::factory()->create(['body' => 'منشور إكس']);
    SocialPost::factory()->create(['platform' => SocialPlatform::YouTube, 'kind' => SocialPostKind::Event, 'body' => 'حدث يوتيوب', 'url' => 'https://youtu.be/x1']);

    $this->get(route('media.social', ['platform' => 'youtube']))->assertOk()->assertSee('حدث يوتيوب')->assertDontSee('منشور إكس');
    $this->get(route('media.social', ['kind' => 'post']))->assertOk()->assertSee('منشور إكس')->assertDontSee('حدث يوتيوب');
    $this->get(route('media.social', ['platform' => 'x', 'kind' => 'event']))->assertOk()->assertSee(__('social_posts.list.no_results'));

    foreach (['platform=mastodon', 'kind=story', 'platform[]=x', 'page=0'] as $query) {
        $this->get('/media/social?'.$query)->assertRedirect(route('media.social'));
    }

    SocialPost::factory()->count(12)->create();
    $this->get('/media/social?page=2')->assertOk()->assertSee(__('site.page_of', ['page' => 2, 'pages' => 2]));
    $this->get('/media/social?page=3')->assertNotFound();

    expect(Route::getRoutes()->getByName('media.social')?->gatherMiddleware())->toContain('throttle:120,1');
});

it('shows the empty state, and the tile in the media center', function () {
    $this->get(route('media.social'))->assertOk()->assertSee(__('social_posts.list.empty'));

    $this->get(route('media.index'))->assertOk()
        ->assertSee('href="'.route('media.social').'"', false)
        ->assertSee(__('media.tiles.social'));
});

it('lists «منشوراتنا» in the media center dropdown only, without adding a menu row or overriding the editor', function () {
    $media = MenuItem::query()->create(['label' => 'المركز الإعلامي', 'url' => '/media', 'is_visible' => true, 'sort' => 3, 'opens_in_new_tab' => false]);
    $rows = MenuItem::query()->count();

    $html = $this->get('/contact')->assertOk()->getContent();
    preg_match('/<ul id="menu-'.$media->id.'"[^>]*>(.*?)<\/ul>/s', (string) $html, $dropdown);
    expect($dropdown[1])->toContain('href="'.url('/media/social').'"')->toContain(__('media.tiles.social'))
        // قسم مولَّد لا صف في «القائمة»، فالصف الأول وعناصر المحرر كما هي
        ->and(MenuItem::query()->count())->toBe($rows);

    // وعنصر المحرر بالرابط نفسه يأخذ مكانه باسمه، ولا يتكرر
    $children = MenuSections::children(['label' => 'المركز الإعلامي', 'url' => '/media', 'children' => [
        ['id' => 5, 'label' => 'منشورات الجمعية', 'url' => '/media/social', 'opens_in_new_tab' => false],
    ]]);
    expect(array_count_values(array_column($children, 'url'))['/media/social'])->toBe(1)
        ->and(array_column($children, 'label'))->toContain('منشورات الجمعية')->not->toContain(__('media.tiles.social'));
});

it('draws each platform filter with its logo inside an icon tile, and the type strip under its label', function () {
    $html = (string) $this->get(route('media.social', ['platform' => 'instagram']))->assertOk()->getContent();

    preg_match('/data-social-filter="platform">(.*?)<\/nav>/s', $html, $platforms);
    expect(substr_count($platforms[1], 'data-icon-tile'))->toBe(count(SocialPlatform::cases()) + 1)
        ->and(substr_count($platforms[1], 'data-social-filter-pill'))->toBe(count(SocialPlatform::cases()) + 1)
        ->and($platforms[1])->toMatch('/aria-current="page"[^>]*>\s*<span data-icon-tile/');

    preg_match('/data-social-filter="kind">(.*?)<\/nav>/s', $html, $kinds);
    expect($kinds[1])->toContain(__('social_posts.fields.kind'))->not->toContain('data-icon-tile');

    // ومربع الأيقونة بلا محتوى يرسم أيقونته كما كان
    expect(Blade::render('<x-icon-tile icon="squares-2x2" size="xs" />'))->toContain('<svg')->toContain('icon-tile-xs')
        ->and(Blade::render('<x-icon-tile size="xs"><b>شعار</b></x-icon-tile>'))->toContain('<b>شعار</b>');
});

it('seeds marked demo posts in the local environment only', function () {
    (new DemoSocialPostsSeeder)->run();
    (new DemoSocialPostsSeeder)->run();

    expect(SocialPost::query()->published()->count())->toBe(6)
        ->and(SocialPost::query()->where('body', 'not like', DemoSocialPostsSeeder::MARK.'%')->exists())->toBeFalse();

    $this->app['env'] = 'production';

    expect(fn () => (new DemoSocialPostsSeeder)->run())->toThrow(RuntimeException::class);
});
