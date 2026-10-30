<?php

use App\Enums\LibrarySection;
use App\Enums\ProgramDelivery;
use App\Models\Album;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\ShareImage;
use Illuminate\Testing\TestResponse;

/**
 * البيانات المنظمة schema.org (StructuredData): كل كتل JSON-LD في الصفحة عقداً مسطّحة.
 *
 * @return list<array<string, mixed>>
 */
function jsonLdNodes(TestResponse $response): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', (string) $response->getContent(), $matches);

    return collect($matches[1])
        ->flatMap(function (string $json): array {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            expect($data['@context'])->toBe('https://schema.org');

            return $data['@graph'] ?? [$data];
        })
        ->values()
        ->all();
}

/**
 * @return array<string, mixed>|null
 */
function jsonLdNode(TestResponse $response, string $type): ?array
{
    return collect(jsonLdNodes($response))->firstWhere('@type', $type);
}

it('describes the association on every public page from the site settings', function () {
    Setting::put('contact', [
        'email' => 'info@ejaz.sa',
        'social' => ['x' => 'https://x.com/ejaz_sa', 'youtube' => 'javascript:alert(1)'],
    ]);

    foreach (['/', '/governance', '/programs', '/contact'] as $path) {
        $organization = jsonLdNode($this->get($path)->assertOk(), 'Organization');

        expect($organization)->not->toBeNull()
            ->and($organization['name'])->toBe(__('site.name'))
            ->and($organization['url'])->toBe(url('/'))
            ->and($organization['logo'])->toBe(asset(ShareImage::DEFAULT))
            ->and($organization['email'])->toBe('info@ejaz.sa')
            // الرابط غير الآمن لا يُكتب
            ->and($organization['sameAs'])->toBe(['https://x.com/ejaz_sa']);
    }
});

it('writes no contact field the settings do not have', function () {
    $organization = jsonLdNode($this->get('/')->assertOk(), 'Organization');

    expect($organization)->not->toHaveKeys(['email', 'sameAs'])
        // والرئيسية بلا مسار تنقل
        ->and(jsonLdNode($this->get('/'), 'BreadcrumbList'))->toBeNull();
});

it('gives inner pages a breadcrumb from the home page down', function () {
    $list = jsonLdNode($this->get('/governance')->assertOk(), 'BreadcrumbList');

    expect($list['itemListElement'])->toBe([
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('site.home'), 'item' => route('home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('governance.title'), 'item' => url('/governance')],
    ]);

    Document::factory()->create(['slug' => 'bylaws', 'title' => 'اللائحة الأساسية']);
    $nodes = jsonLdNodes($this->get('/governance/bylaws')->assertOk());
    $crumbs = collect($nodes)->where('@type', 'BreadcrumbList');

    // مسار واحد بأقسامه، لا الافتراضي معه
    expect($crumbs)->toHaveCount(1)
        ->and(array_column($crumbs->first()['itemListElement'], 'name'))->toBe([__('site.home'), __('governance.title'), 'اللائحة الأساسية']);
});

it('gives detail pages one breadcrumb through their sections', function () {
    NewsArticle::factory()->create(['slug' => 'first-news', 'title' => 'خبر']);
    Album::factory()->create(['slug' => 'first-album', 'title' => 'ألبوم']);
    Initiative::factory()->create(['slug' => 'qiyam', 'name' => 'قيم']);

    $cases = [
        '/media/news/first-news' => [__('site.home'), __('media.title'), __('news.title'), 'خبر'],
        '/media/albums/first-album' => [__('site.home'), __('media.title'), __('albums.title'), 'ألبوم'],
        '/initiatives/qiyam' => [__('site.home'), __('content.initiatives.title'), 'قيم'],
        '/media/partners' => [__('site.home'), __('media.title'), __('partners.title')],
    ];

    foreach ($cases as $path => $names) {
        $crumbs = collect(jsonLdNodes($this->get($path)->assertOk()))->where('@type', 'BreadcrumbList');
        $items = $crumbs->first()['itemListElement'];

        expect($crumbs)->toHaveCount(1)
            ->and(array_column($items, 'name'))->toBe($names)
            ->and(end($items)['item'])->toBe(url($path));
    }
});

it('leaves all structured data out of pages that are not indexed', function () {
    // ومنها معرض الشواهد برابطه السري: لا شيء يعرّف بالصفحة لمحركات البحث (مراجعة المشرف على #104)
    expect(jsonLdNode($this->get('/login')->assertOk(), 'BreadcrumbList'))->toBeNull()
        ->and(jsonLdNode($this->get('/login'), 'Organization'))->toBeNull();
});

it('describes a published program as an event without its meeting link', function () {
    $program = Program::factory()->create([
        'slug' => 'tafsir-course',
        'title' => 'دورة في التفسير العلمي',
        'summary' => 'وصف مختصر',
        'location' => 'مكة المكرمة',
        'starts_at' => '2026-12-01 07:00:00',
        'ends_at' => '2026-12-01 09:00:00',
    ]);

    $event = jsonLdNode($this->get('/programs/tafsir-course')->assertOk(), 'Event');

    expect($event)->toMatchArray([
        'name' => 'دورة في التفسير العلمي',
        'description' => 'وصف مختصر',
        'url' => route('programs.show', $program->slug),
        'startDate' => '2026-12-01T10:00:00+03:00',
        'endDate' => '2026-12-01T12:00:00+03:00',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => ['@type' => 'Place', 'name' => 'مكة المكرمة', 'address' => 'مكة المكرمة'],
        'organizer' => ['@type' => 'Organization', 'name' => __('site.name'), 'url' => url('/')],
    ]);

    $remote = Program::factory()->remote('https://teams.microsoft.com/l/meetup-join/secret')->create(['slug' => 'remote-course']);
    $response = $this->get('/programs/remote-course')->assertOk();
    $event = jsonLdNode($response, 'Event');

    expect($event['eventAttendanceMode'])->toBe('https://schema.org/OnlineEventAttendanceMode')
        ->and($event['location'])->toBe(['@type' => 'VirtualLocation', 'url' => route('programs.show', $remote->slug)])
        ->and(json_encode(jsonLdNodes($response)))->not->toContain('meetup-join')
        ->and(array_column(jsonLdNode($response, 'BreadcrumbList')['itemListElement'], 'item'))
        ->toBe([route('home'), route('programs.index'), route('programs.show', $remote->slug)]);
});

it('describes a published competition as an online event', function () {
    $competition = Competition::factory()->create(['slug' => 'brahin', 'title' => 'مسابقة البراهين', 'description' => 'عن المسابقة']);

    $event = jsonLdNode($this->get('/competitions/brahin')->assertOk(), 'Event');

    expect($event)->toMatchArray([
        'name' => 'مسابقة البراهين',
        'description' => 'عن المسابقة',
        'startDate' => $competition->opens_at->setTimezone('Asia/Riyadh')->toIso8601String(),
        'endDate' => $competition->closes_at->setTimezone('Asia/Riyadh')->toIso8601String(),
        'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
        'location' => ['@type' => 'VirtualLocation', 'url' => route('competitions.show', 'brahin')],
    ]);
});

it('describes library books and videos with their stored data', function () {
    LibraryItem::factory()->create([
        'slug' => 'first-book',
        'title' => 'كتاب الإعجاز',
        'author' => 'مؤلف الكتاب',
        'description' => 'نبذة الكتاب',
        'published_on' => '2025-05-01',
        'thumbnail' => 'library/covers/book.webp',
    ]);
    LibraryItem::factory()->video('https://www.youtube.com/watch?v=abcdefghijk')->create([
        'slug' => 'first-talk',
        'title' => 'محاضرة',
        'published_on' => '2025-06-02',
        'thumbnail' => 'library/thumbnails/talk.jpg',
    ]);

    // ووصف الصفحة نبذة العنصر لا اسم قسمه
    $book = jsonLdNode($this->get('/library/books/first-book')->assertOk()->assertSee('<meta name="description" content="نبذة الكتاب">', false), 'Book');

    expect($book)->toMatchArray([
        'name' => 'كتاب الإعجاز',
        'description' => 'نبذة الكتاب',
        'author' => ['@type' => 'Person', 'name' => 'مؤلف الكتاب'],
        'datePublished' => '2025-05-01',
        'image' => url('/storage/library/covers/book.webp'),
        'url' => route('library.show', ['books', 'first-book']),
    ]);

    $response = $this->get('/library/videos/first-talk')->assertOk();
    $video = jsonLdNode($response, 'VideoObject');

    // Google: name وthumbnailUrl وuploadDate، والوصف بالعنوان إن لم يُكتب
    expect($video)->toMatchArray([
        'name' => 'محاضرة',
        'description' => 'محاضرة',
        'thumbnailUrl' => url('/storage/library/thumbnails/talk.jpg'),
        'uploadDate' => '2025-06-02',
        'embedUrl' => 'https://www.youtube-nocookie.com/embed/abcdefghijk',
    ])->and(array_column(jsonLdNode($response, 'BreadcrumbList')['itemListElement'], 'name'))
        ->toBe([__('site.home'), __('library.title'), LibrarySection::Videos->getLabel(), 'محاضرة']);
});

it('writes nothing for unpublished items', function () {
    Program::factory()->unpublished()->create(['slug' => 'draft-program', 'title' => 'برنامج مسودة']);
    Competition::factory()->unpublished()->create(['slug' => 'draft-competition', 'title' => 'مسابقة مسودة']);
    LibraryItem::factory()->unpublished()->create(['slug' => 'draft-book', 'title' => 'كتاب مسودة']);

    foreach (['/programs/draft-program', '/competitions/draft-competition', '/library/books/draft-book'] as $path) {
        $response = $this->get($path)->assertNotFound();
        expect(collect(jsonLdNodes($response))->pluck('@type')->all())->not->toContain('Event', 'Book');
    }

    foreach (['/programs', '/competitions', '/library/books'] as $path) {
        expect((string) $this->get($path)->assertOk()->getContent())->not->toContain('مسودة');
    }
});

it('keeps a title with a closing script tag inside the JSON block', function () {
    Program::factory()->create(['slug' => 'escape', 'title' => 'عنوان </script><script>alert(1)</script> & "اقتباس"']);

    $response = $this->get('/programs/escape')->assertOk();
    $content = (string) $response->getContent();

    expect($content)->not->toContain('</script><script>alert(1)')
        ->and(jsonLdNode($response, 'Event')['name'])->toBe('عنوان </script><script>alert(1)</script> & "اقتباس"');
});

it('gives every block the request nonce of the content security policy', function () {
    $response = $this->get('/governance')->assertOk();
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);

    preg_match_all('#<script type="application/ld\+json" nonce="([^"]+)">#', (string) $response->getContent(), $blocks);

    expect($blocks[1])->not->toBeEmpty()
        ->and(array_unique($blocks[1]))->toBe([$nonce[1]]);
});

it('writes no event or video the search engine would reject for a missing required field', function () {
    Program::factory()->create(['slug' => 'no-place', 'location' => null]);
    LibraryItem::factory()->video()->create(['slug' => 'no-thumbnail', 'thumbnail' => null]);
    LibraryItem::factory()->video()->create(['slug' => 'no-date', 'thumbnail' => 'library/thumbnails/a.jpg', 'published_on' => null]);

    $program = $this->get('/programs/no-place')->assertOk();
    expect(jsonLdNode($program, 'Event'))->toBeNull()
        ->and(jsonLdNode($program, 'BreadcrumbList'))->not->toBeNull()
        ->and(jsonLdNode($this->get('/library/videos/no-thumbnail')->assertOk(), 'VideoObject'))->toBeNull()
        ->and(jsonLdNode($this->get('/library/videos/no-date')->assertOk(), 'VideoObject'))->toBeNull();
});

it('places a hybrid program both at its venue and on its page', function () {
    Program::factory()->create(['slug' => 'hybrid', 'delivery' => ProgramDelivery::Hybrid, 'location' => 'مكة المكرمة', 'meeting_url' => 'https://teams.microsoft.com/l/meetup-join/x']);

    expect(jsonLdNode($this->get('/programs/hybrid')->assertOk(), 'Event'))->toMatchArray([
        'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode',
        'location' => [
            ['@type' => 'Place', 'name' => 'مكة المكرمة', 'address' => 'مكة المكرمة'],
            ['@type' => 'VirtualLocation', 'url' => route('programs.show', 'hybrid')],
        ],
    ]);
});

it('gives a student competition no end date, as its closing is the end of registration', function () {
    Competition::factory()->student()->create(['slug' => 'students']);

    expect(jsonLdNode($this->get('/competitions/students')->assertOk(), 'Event'))
        ->toHaveKey('startDate')
        ->not->toHaveKey('endDate');
});

it('describes the announcements page by its intro only once it replaces the sample text', function () {
    $this->get('/media/announcements')->assertOk()->assertSee('<meta name="description" content="'.e(__('site.description')).'">', false);

    Setting::put(HomeContent::KEY, ['announcements' => ['intro' => 'مقدمة الإعلانات من اللوحة']]);

    $this->get('/media/announcements')->assertOk()->assertSee('<meta name="description" content="مقدمة الإعلانات من اللوحة">', false);
});

it('renders a library page cached before the structured data existed', function () {
    $item = LibraryItem::factory()->create(['slug' => 'old-cache', 'title' => 'كتاب قديم']);
    ContentCache::remember('library.show.old-cache', fn (): array => ['title' => 'كتاب قديم', 'summary' => 'الكتب', 'image' => null, 'html' => '<p>قديم</p>']);

    $response = $this->get('/library/books/old-cache')->assertOk()->assertSee('قديم');

    expect(jsonLdNode($response, 'Book'))->toBeNull()
        ->and(jsonLdNode($response, 'BreadcrumbList'))->not->toBeNull()
        ->and($item->exists)->toBeTrue();
});
