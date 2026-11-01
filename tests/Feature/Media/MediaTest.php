<?php

use App\Enums\SurveyAudience;
use App\Models\Album;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// المرحلة ج من التصور النهائي (Final-Media): الأخبار والإعلانات ثم مكتبة الصور والشركاء بلاطاتٍ ملونة، والمكتبة المرئية في «مكتبة إعجاز»
it('shows the media center tiles, news, announcements, albums and partners, and the latest news', function () {
    NewsArticle::factory()->create(['title' => 'خبر منشور']);
    NewsArticle::factory()->unpublished()->create(['title' => 'خبر غير منشور']);

    $response = $this->get('/media')->assertOk()
        ->assertSeeInOrder(['<h1', __('media.title')], false)
        ->assertSee('href="'.route('media.news.index').'"', false)
        ->assertSee('href="'.route('media.announcements').'"', false)
        ->assertSee('href="'.route('media.albums.index').'"', false)
        ->assertSee('href="'.route('media.partners').'"', false)
        ->assertDontSee('href="'.route('library.section', 'videos').'"', false)
        ->assertSee('خبر منشور')
        ->assertDontSee('خبر غير منشور');

    // «قياس الرضا» لا يظهر بلا استبيان عام مفتوح، فلا رابط إلى 404
    $response->assertDontSee(__('media.tiles.surveys'));
});

it('shows the satisfaction survey tile while a general survey is open', function () {
    Survey::factory()->create(['audience' => SurveyAudience::General]);

    $this->get('/media')->assertOk()->assertSee('href="'.route('surveys.index').'"', false);
    $this->get(route('surveys.index'))->assertRedirect();
});

it('lists the published news newest first, filters by category and searches titles, taking % and _ as plain letters', function () {
    NewsArticle::factory()->create(['title' => 'خبر أقدم', 'published_on' => '2025-01-10', 'category' => 'فعاليات']);
    NewsArticle::factory()->create(['title' => 'خبر أحدث', 'published_on' => '2026-03-01', 'category' => 'إصدارات']);
    NewsArticle::factory()->unpublished()->create(['title' => 'مسودة خبر']);

    $this->get('/media/news')->assertOk()
        ->assertSeeInOrder(['<h1', __('news.title')], false)
        ->assertSeeInOrder(['خبر أحدث', 'خبر أقدم'])
        ->assertDontSee('مسودة خبر')
        ->assertSee('href="'.e(route('media.news.index', ['category' => 'فعاليات'])).'"', false);

    $this->get('/media/news?category=فعاليات')->assertSee('خبر أقدم')->assertDontSee('خبر أحدث');
    $this->get('/media/news?q=أحدث')->assertSee('خبر أحدث')->assertDontSee('خبر أقدم');
    $this->get('/media/news?q=%25')->assertSee(__('news.list.no_results'));
    $this->get('/media/news?q=_')->assertSee(__('news.list.no_results'));
});

it('sends an invalid filter back to the news list', function (string $query) {
    $this->get('/media/news?'.$query)->assertRedirect(route('media.news.index'));
})->with(['q='.str_repeat('x', 101), 'category='.str_repeat('x', 61), 'q[]=x', 'page=0', 'page=abc', 'q=%FF', 'category=x%00y']);

it('pages through the albums, answering 404 after the last page and sending a wrong page back to the first', function () {
    Album::factory()->count(13)->create();

    $this->get('/media/albums?page=2')->assertOk()->assertSee(__('site.page_of', ['page' => 2, 'pages' => 2]));
    $this->get('/media/albums?page=3')->assertNotFound();
    $this->get('/media/albums?page=0')->assertRedirect(route('media.albums.index'));
    $this->get('/media/albums?page=abc')->assertRedirect(route('media.albums.index'));
});

it('limits the requests to the list pages that read the database for every search', function (string $route) {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain('throttle:120,1');
})->with(['governance.index', 'library.section', 'media.news.index', 'media.albums.index']);

it('pages through the news with the validated filter only, and answers 404 after the last page', function () {
    NewsArticle::factory()->count(13)->create(['category' => 'فعاليات']);

    $this->get('/media/news?category=فعاليات')->assertSee(route('media.news.index', ['category' => 'فعاليات', 'page' => 2]));
    $this->get('/media/news?category=فعاليات&page=2')->assertOk()->assertSee(__('site.page_of', ['page' => 2, 'pages' => 2]));
    $this->get('/media/news?page=3')->assertNotFound();

    expect($this->get('/media/news?utm=evil')->assertOk()->getContent())->not->toContain('utm');
});

it('shows an article with its cleaned content, its gallery and share links, and other news without itself', function () {
    NewsArticle::factory()->create([
        'slug' => 'annual-forum',
        'title' => 'الملتقى السنوي',
        'summary' => 'ملخص الخبر',
        'category' => 'فعاليات',
        'blocks' => [['type' => 'text', 'data' => ['heading' => 'تفاصيل', 'body' => '<p>نص الخبر<script>alert(1)</script></p>']]],
        'gallery' => ['news/one.jpg', 'news/two.jpg'],
    ]);
    NewsArticle::factory()->create(['title' => 'خبر آخر منشور']);
    NewsArticle::factory()->unpublished()->create(['title' => 'خبر آخر غير منشور']);

    $url = route('media.news.show', 'annual-forum');

    $response = $this->get('/media/news/annual-forum')->assertOk()
        ->assertSeeInOrder(['<h1', 'الملتقى السنوي'], false)
        ->assertSee('نص الخبر')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('news/two.jpg')
        ->assertSee('href="'.e('https://wa.me/?text='.rawurlencode('الملتقى السنوي '.$url)).'"', false)
        ->assertSee('href="'.e('https://x.com/intent/post?text='.rawurlencode('الملتقى السنوي').'&url='.rawurlencode($url)).'"', false)
        ->assertSee('data-copy-link="'.$url.'"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee(__('news.article.more'))
        ->assertSee('خبر آخر منشور')
        ->assertDontSee('خبر آخر غير منشور');

    // والخبر نفسه ليس في «أخبار أخرى»
    expect(Str::after((string) $response->getContent(), __('news.article.more')))->not->toContain('الملتقى السنوي');
});

it('answers 404 for an unpublished or unknown article or album', function () {
    NewsArticle::factory()->unpublished()->create(['slug' => 'draft-news']);
    Album::factory()->unpublished()->create(['slug' => 'draft-album']);

    $this->get('/media/news/draft-news')->assertNotFound();
    $this->get('/media/news/unknown')->assertNotFound();
    $this->get('/media/news/Bad_Slug')->assertNotFound();
    $this->get('/media/albums/draft-album')->assertNotFound();
    $this->get('/media/albums/unknown')->assertNotFound();
});

it('lists the albums with their cover and photo count, and shows the photos of an album', function () {
    Album::factory()->create(['slug' => 'forum', 'title' => 'ألبوم الملتقى', 'photos' => ['albums/one.jpg', 'albums/two.jpg'], 'description' => 'وصف الألبوم']);
    Album::factory()->unpublished()->create(['title' => 'ألبوم غير منشور']);

    $this->get('/media/albums')->assertOk()
        ->assertSee('ألبوم الملتقى')
        ->assertSee(trans_choice('albums.list.photos', 2, ['count' => 2]))
        ->assertSee('albums/one.jpg')
        ->assertDontSee('ألبوم غير منشور');

    $this->get('/media/albums/forum')->assertOk()
        ->assertSeeInOrder(['<h1', 'ألبوم الملتقى'], false)
        ->assertSee('وصف الألبوم')
        ->assertSee(__('albums.album.photo_alt', ['number' => 2, 'title' => 'ألبوم الملتقى']))
        ->assertSee('albums/two.jpg');
});

it('shows the published partners in their order, linking only to safe addresses', function () {
    Partner::factory()->create(['name' => 'الشريك الثاني', 'sort' => 2, 'url' => 'https://second.example']);
    Partner::factory()->create(['name' => 'الشريك الأول', 'sort' => 1, 'url' => 'javascript:alert(1)']);
    Partner::factory()->unpublished()->create(['name' => 'شريك غير منشور']);

    $response = $this->get('/media/partners')->assertOk()
        ->assertSeeInOrder(['الشريك الأول', 'الشريك الثاني'])
        ->assertSee('href="https://second.example" target="_blank" rel="noopener noreferrer"', false)
        ->assertDontSee('شريك غير منشور');

    // رابط ضار وصل إلى القاعدة لا يُعرض
    expect($response->getContent())->not->toContain('javascript:');
});

it('shows the latest news and the partners on the home page, each hidden from the settings', function () {
    showLegacyHomeSections();
    NewsArticle::factory()->create(['title' => 'خبر في الرئيسية']);
    Partner::factory()->create(['name' => 'شريك في الرئيسية']);

    $this->get('/')
        ->assertSee(__('home.sections_final.tickers.news'))
        ->assertSee('خبر في الرئيسية')
        ->assertSee('شريك في الرئيسية')
        // وبطاقة المركز الإعلامي في الاختصارات بوجود مساره
        ->assertSee(__('home.shortcuts.media.title'))
        ->assertSee('href="'.route('media.index').'"', false);

    showLegacyHomeSections(['show_latest' => false, 'show_partners' => false]);

    $this->get('/')->assertDontSee('خبر في الرئيسية')->assertDontSee('شريك في الرئيسية');
});

it('caches the news list and refreshes it on change, but never caches a search', function () {
    $article = NewsArticle::factory()->create(['title' => 'العنوان الأول']);
    $this->get('/media/news')->assertSee('العنوان الأول');

    DB::table('news_articles')->where('id', $article->id)->update(['title' => 'عنوان لم يُعتمد']);

    $this->get('/media/news')->assertSee('العنوان الأول');
    $this->get('/media/news?q=لم')->assertSee('عنوان لم يُعتمد');

    $article->refresh()->update(['title' => 'العنوان الجديد']);

    $this->get('/media/news')->assertSee('العنوان الجديد');
});
