<?php

use App\Models\Competition;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\User;
use App\Support\ShareImage;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

/*
| مراجعة محركات البحث (3 نوفمبر 2026): canonical الصفحات المرقّمة وعنوانها، والخبر مقالةً، وصورة المشاركة الافتراضية،
| ووصف البرنامج والمسابقة، وX-Robots-Tag، وTrustHosts في الإنتاج.
*/

function headMeta(string $html, string $attribute, string $name): ?string
{
    return preg_match('/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)"/u', $html, $match) === 1 ? html_entity_decode($match[1]) : null;
}

function canonicalOf(string $html): ?string
{
    return preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $match) === 1 ? html_entity_decode($match[1]) : null;
}

it('gives a later page of a list its own canonical link and its number in the title, and keeps the first page plain', function () {
    NewsArticle::factory()->count(13)->create();

    $first = (string) $this->get('/media/news')->assertOk()->getContent();
    $second = (string) $this->get('/media/news?page=2')->assertOk()->getContent();
    $one = (string) $this->get('/media/news?page=1')->assertOk()->getContent();

    expect(canonicalOf($first))->toBe(url('/media/news'))
        ->and(canonicalOf($one))->toBe(url('/media/news'))
        ->and(canonicalOf($second))->toBe(url('/media/news?page=2'))
        ->and(headMeta($second, 'property', 'og:url'))->toBe(url('/media/news?page=2'))
        ->and($second)->toContain('<title>'.e(__('news.title').' - '.__('site.page_number', ['number' => 2]).' | '.__('site.short_name')).'</title>')
        ->and($first)->not->toContain(__('site.page_number', ['number' => 1]));

    // وصفحة بلا ترقيم لا يغيّرها ?page، ولا رقم غير صحيح، والقائمة المصفّاة تشير إلى الأصل كما كانت
    $plain = (string) $this->get('/contact?page=3')->assertOk()->getContent();
    $odd = (string) $this->get('/contact?page=2abc')->assertOk()->getContent();
    $filtered = (string) $this->get('/media/news?q=&page=2')->assertOk()->getContent();
    expect(canonicalOf($plain))->toBe(url('/contact'))
        ->and($plain)->not->toContain(__('site.page_number', ['number' => 3]))
        ->and(canonicalOf($odd))->toBe(url('/contact'))
        ->and(canonicalOf($filtered))->toBe(url('/media/news'))
        ->and($filtered)->toContain(__('site.page_number', ['number' => 2]));
});

it('marks a news page as an article with its publication date and NewsArticle structured data', function () {
    Storage::fake('public');
    $article = NewsArticle::factory()->create(['slug' => 'forum', 'title' => 'الملتقى السنوي', 'summary' => 'ملخص الخبر', 'published_on' => '2026-02-01', 'image' => 'news/cover.jpg']);

    $html = (string) $this->get('/media/news/forum')->assertOk()->getContent();

    expect(headMeta($html, 'property', 'og:type'))->toBe('article')
        ->and(headMeta($html, 'property', 'article:published_time'))->toBe('2026-02-01')
        ->and($html)->toContain('"@type":"NewsArticle"')
        ->and($html)->toContain('"headline":"الملتقى السنوي"')
        ->and($html)->toContain('"datePublished":"2026-02-01"')
        ->and($html)->toContain('"image":"'.Storage::disk('public')->url('news/cover.jpg').'"');

    $list = (string) $this->get('/media/news')->assertOk()->getContent();
    expect(headMeta($list, 'property', 'og:type'))->toBe('website')
        ->and($list)->not->toContain('article:published_time');

    expect($article->title)->toBe('الملتقى السنوي');
});

it('describes the default share image with its size and alt text on a small card, and a page image on a large one', function () {
    $home = (string) $this->get('/')->assertOk()->getContent();

    expect(headMeta($home, 'property', 'og:image'))->toBe(asset(ShareImage::DEFAULT))
        ->and(headMeta($home, 'property', 'og:image:width'))->toBe((string) ShareImage::DEFAULT_WIDTH)
        ->and(headMeta($home, 'property', 'og:image:height'))->toBe((string) ShareImage::DEFAULT_HEIGHT)
        ->and(headMeta($home, 'property', 'og:image:alt'))->toBe(__('site.share_image_alt', ['name' => __('site.name')]))
        ->and(headMeta($home, 'name', 'twitter:card'))->toBe('summary');

    [$width, $height] = getimagesize(public_path(ShareImage::DEFAULT));
    expect([$width, $height])->toBe([ShareImage::DEFAULT_WIDTH, ShareImage::DEFAULT_HEIGHT]);

    Storage::fake('public');
    NewsArticle::factory()->create(['slug' => 'with-cover', 'image' => 'news/cover.jpg']);
    $page = (string) $this->get('/media/news/with-cover')->assertOk()->getContent();

    expect(headMeta($page, 'name', 'twitter:card'))->toBe('summary_large_image')
        ->and($page)->not->toContain('og:image:width');
});

it('keeps the description of a programme and a competition flat and within 160 characters', function () {
    $long = "<p>سطر أول</p>\n\n".str_repeat('نص طويل للوصف ', 20);
    Program::factory()->create(['slug' => 'long-program', 'summary' => $long]);
    Program::factory()->create(['slug' => 'no-summary', 'summary' => null, 'description' => 'وصف البرنامج الكامل']);
    Competition::factory()->create(['slug' => 'long-competition', 'description' => $long]);

    foreach (['/programs/long-program', '/competitions/long-competition'] as $path) {
        $description = headMeta((string) $this->get($path)->assertOk()->getContent(), 'name', 'description');

        expect(mb_strlen((string) $description))->toBeLessThanOrEqual(163)
            ->and($description)->toStartWith('سطر أول نص طويل')
            ->and($description)->not->toContain('<p>')
            ->and($description)->not->toContain("\n");
    }

    expect(headMeta((string) $this->get('/programs/no-summary')->getContent(), 'name', 'description'))->toBe('وصف البرنامج الكامل');
});

it('tells search engines not to index the admin panel, Horizon and signed files, and the whole copy when it is not indexable', function () {
    config(['app.indexable' => true]);

    expect($this->get('/')->headers->get('X-Robots-Tag'))->toBeNull()
        ->and($this->get('/admin')->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($this->get('/'.trim((string) config('horizon.path'), '/'))->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');

    Route::middleware('web')->get('/_test/signed-file', fn () => 'file')->name('test.signed-file')->middleware('signed');
    Route::getRoutes()->refreshNameLookups();
    $this->get(URL::temporarySignedRoute('test.signed-file', now()->addMinute()))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    config(['app.indexable' => false]);
    $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps TrustHosts in the global middleware, so a production request for another host is refused', function () {
    expect(app(Kernel::class)->getGlobalMiddleware())->toContain(TrustHosts::class);

    // لارافيل يطفئه في المحلية والاختبارات وحدهما (shouldSpecifyTrustedHosts)، فيُختبر هنا كما يعمل في الإنتاج
    $production = new class(app()) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts()
        {
            return true;
        }
    };
    $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

    try {
        expect($production->hosts())->toBe(['^(.+\.)?'.preg_quote($host).'$'])
            ->and($production->handle(Request::create('http://'.$host.'/'), fn (Request $request) => $request->getHost()))->toBe($host)
            ->and(fn () => $production->handle(Request::create('http://evil.test/'), fn (Request $request) => $request->getHost()))
            ->toThrow(SuspiciousOperationException::class);
    } finally {
        Request::setTrustedHosts([]);
    }
});

it('keeps a news title with </script> whole and escaped in the JSON-LD, the title and the share tags', function () {
    $title = 'خبر </script><script>alert(1)</script> & "اقتباس"';
    NewsArticle::factory()->create(['slug' => 'script-title', 'title' => $title, 'summary' => 'ملخص <b>الخبر</b>']);

    $html = (string) $this->get('/media/news/script-title')->assertOk()->getContent();

    // لا يُغلق العنوانُ كتلة البيانات ولا يفتح سكربتاً: < و> مهرّبة في JSON_HEX_TAG، وفي الوسوم كيانات HTML
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $blocks);
    $nodes = collect($blocks[1])->flatMap(function (string $json): array {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $data['@graph'] ?? [$data];
    });
    $article = $nodes->firstWhere('@type', 'NewsArticle');

    expect($html)->not->toContain('<script>alert(1)')
        ->and($article)->not->toBeNull()
        ->and($article['headline'])->toBe($title)
        ->and($article['description'])->toBe('ملخص الخبر')
        ->and(collect($blocks[1])->first(fn (string $json): bool => str_contains($json, 'NewsArticle')))
        ->toContain(trim((string) json_encode('</script>', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), '"'))->not->toContain('</script>')
        ->and($html)->toContain('<title>'.e($title).' | '.__('site.short_name').'</title>')
        ->and(headMeta($html, 'property', 'og:title'))->toBe($title);
});

it('answers 404 for a page number after the last one, in the public lists and the account lists', function () {
    NewsArticle::factory()->count(13)->create();

    $this->get('/media/news?page=2')->assertOk();
    $this->get('/media/news?page=3')->assertNotFound();
    $this->get('/media/news?page=99')->assertNotFound();

    // ولا يُرسم رقم صفحة غير موجودة في عنوانها
    $this->actingAs(User::factory()->create());

    foreach (['/account/programs', '/account/requests'] as $path) {
        $this->get($path)->assertOk();
        $this->get($path.'?page=1')->assertOk();
        $this->get($path.'?page=2')->assertNotFound();
    }
});
