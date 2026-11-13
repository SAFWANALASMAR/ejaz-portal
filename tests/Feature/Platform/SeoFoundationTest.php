<?php

use App\Enums\AchievementStatus;
use App\Enums\OpeningKind;
use App\Models\Achievement;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Program;
use App\Support\Seo;

/*
| أساس محركات البحث (feat/seo-foundation — قرار صفوان، 11 نوفمبر 2026، docs/decisions/2026-11-11-feat-seo-foundation.md):
| رمزا إثبات الملكية، ووصف فريد لكل صفحة، والعنوان بلا لاحقة إن طال، والقوائم الفارغة خارج الفهرسة وخريطة الموقع.
*/

function seoMeta(string $html, string $name): ?string
{
    return preg_match('/<meta name="'.preg_quote($name, '/').'" content="([^"]*)"/', $html, $match) ? html_entity_decode($match[1]) : null;
}

function seoTitle(string $html): string
{
    preg_match('/<title>([^<]*)<\/title>/', $html, $match);

    return html_entity_decode($match[1] ?? '');
}

/** @return list<string> */
function seoSitemapUrls(mixed $test): array
{
    $xml = simplexml_load_string((string) $test->get('/sitemap.xml')->assertOk()->getContent());

    return collect(iterator_to_array($xml->url, false))->map(fn (SimpleXMLElement $url): string => (string) $url->loc)->all();
}

it('prints the two site verification tags from the environment on public pages', function () {
    config(['services.google.site_verification' => 'google-token-123', 'services.bing.site_verification' => 'bing-token-456']);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(seoMeta($html, 'google-site-verification'))->toBe('google-token-123')
        ->and(seoMeta($html, 'msvalidate.01'))->toBe('bing-token-456');

    // ولا يُطبعان في صفحة غير مفهرسة (الدخول)
    $login = (string) $this->get('/login')->assertOk()->getContent();
    expect($login)->not->toContain('google-site-verification')->and($login)->not->toContain('msvalidate.01');
});

it('prints no verification tag when the environment leaves them empty', function () {
    config(['services.google.site_verification' => null, 'services.bing.site_verification' => '']);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('google-site-verification')->and($html)->not->toContain('msvalidate.01');
});

it('gives every page in the sitemap but the home page its own description', function () {
    Document::factory()->create(['slug' => 'bylaws', 'title' => 'اللائحة الأساسية', 'description' => null]);
    Document::factory()->create(['slug' => 'conflict', 'title' => 'سياسة تعارض المصالح', 'description' => null]);
    Initiative::factory()->create(['slug' => 'qiyam']);
    LibraryItem::factory()->create(['slug' => 'first-book']);
    NewsArticle::factory()->create(['slug' => 'first-news']);
    Album::factory()->create(['slug' => 'first-album']);
    Program::factory()->create(['slug' => 'open-program']);
    Competition::factory()->create(['slug' => 'open-competition']);
    Competition::factory()->student()->create(['slug' => 'student-competition', 'page_content' => ['guide_steps' => ['خطوة']]]);
    Opening::factory()->create(['slug' => 'open-job']);
    Opening::factory()->kind(OpeningKind::Volunteering)->create(['slug' => 'open-volunteering']);
    Announcement::factory()->create();
    Achievement::factory()->published()->create();

    $descriptions = [];

    foreach (seoSitemapUrls($this) as $url) {
        if ($url === url('/')) {
            continue;
        }

        $descriptions[$url] = seoMeta((string) $this->get($url)->assertOk()->getContent(), 'description');
    }

    $duplicates = collect($descriptions)->duplicates()->all();

    expect($duplicates)->toBe([])
        ->and(collect($descriptions)->filter(fn (?string $text): bool => $text === __('site.description'))->keys()->all())->toBe([])
        ->and($descriptions[url('/governance/bylaws')])->toBe('اللائحة الأساسية، من وثائق الحوكمة في جمعية إعجاز.')
        ->and($descriptions[url('/competitions/open-competition')])->toBe(Competition::query()->where('slug', 'open-competition')->value('title').'، من مسابقات جمعية إعجاز.')
        ->and($descriptions[url('/media/news')])->toBe(__('seo.descriptions.media_news_index'))
        ->and($descriptions[url('/library/books')])->toBe(__('seo.descriptions.library_section_books'));
});

it('keeps a description written for the page over the list description', function () {
    Document::factory()->create(['slug' => 'described', 'description' => 'وصف الوثيقة من اللوحة']);

    expect(seoMeta((string) $this->get('/governance/described')->getContent(), 'description'))->toBe('وصف الوثيقة من اللوحة')
        ->and(seoMeta((string) $this->get('/services')->getContent(), 'description'))->toBe(__('services.intro'));
});

it('cuts a long document title so the description stays within 160 characters with its full stop', function () {
    Document::factory()->create(['slug' => 'long', 'title' => str_repeat('وثيقة طويلة ', 15), 'description' => null]);

    $description = seoMeta((string) $this->get('/governance/long')->getContent(), 'description');

    expect(mb_strlen($description))->toBeLessThanOrEqual(Seo::DESCRIPTION_LENGTH)
        ->and($description)->toContain('…')
        ->and($description)->toEndWith('من وثائق الحوكمة في جمعية إعجاز.');
});

it('drops the association suffix from a title it would push past 65 characters', function () {
    $short = 'وثيقة قصيرة';
    $long = 'الدليل الإجرائي لتنظيم العمل في اللجان الدائمة والمؤقتة بالجمعية';
    Document::factory()->create(['slug' => 'short', 'title' => $short]);
    Document::factory()->create(['slug' => 'long-title', 'title' => $long]);

    expect(mb_strlen($long.' | '.__('site.short_name')))->toBeGreaterThan(Seo::TITLE_LENGTH)
        ->and(seoTitle((string) $this->get('/governance/short')->getContent()))->toBe($short.' | '.__('site.short_name'))
        ->and(seoTitle((string) $this->get('/governance/long-title')->getContent()))->toBe($long);
});

// والثالث يسحب العنصر من النشر (مراجعة المشرف على #248: الاتجاه العكسي)
dataset('listings', [
    'albums' => ['/media/albums', fn () => Album::factory()->create(), fn (Album $album) => $album->update(['is_published' => false])],
    'announcements' => ['/media/announcements', fn () => Announcement::factory()->create(), fn (Announcement $announcement) => $announcement->update(['is_published' => false])],
    'achievements' => ['/achievements', fn () => Achievement::factory()->published()->create(), fn (Achievement $achievement) => $achievement->update(['status' => AchievementStatus::Draft])],
    'jobs' => ['/services/jobs', fn () => Opening::factory()->create(), fn (Opening $opening) => $opening->update(['is_published' => false])],
    'volunteering' => ['/services/volunteering', fn () => Opening::factory()->kind(OpeningKind::Volunteering)->create(), fn (Opening $opening) => $opening->update(['is_published' => false])],
    'competitions' => ['/competitions', fn () => Competition::factory()->create(), fn (Competition $competition) => $competition->update(['is_published' => false])],
]);

// والثالث للاختبار التالي، ويُعلن هنا لأن Pest يطابق الإغلاقات في الصف مع معاملات الاختبار بموضعها
it('keeps an empty list page out of search engines and the sitemap until its first published item', function (string $path, Closure $publish, Closure $withdraw) {
    expect((string) $this->get($path)->assertOk()->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and(seoSitemapUrls($this))->not->toContain(url($path));

    $publish();

    expect((string) $this->get($path)->assertOk()->getContent())->not->toContain('noindex')
        ->and(seoSitemapUrls($this))->toContain(url($path));
})->with('listings');

it('puts the list back out of search engines and the sitemap once its last item is withdrawn or deleted', function (string $path, Closure $publish, Closure $withdraw) {
    $withdraw($publish());

    expect((string) $this->get($path)->assertOk()->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and(seoSitemapUrls($this))->not->toContain(url($path));

    // والحذف كذلك: الحفظ والحذف يمسحان ذاكرة المحتوى (FlushesContentCache)
    $item = $publish();
    expect(seoSitemapUrls($this))->toContain(url($path));
    $item->delete();

    expect((string) $this->get($path)->assertOk()->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and(seoSitemapUrls($this))->not->toContain(url($path));
})->with('listings');

it('keeps a list whose only items are unpublished or closed out of the index', function () {
    Album::factory()->unpublished()->create();
    Opening::factory()->closed()->create();

    expect((string) $this->get('/media/albums')->getContent())->toContain('noindex')
        ->and((string) $this->get('/services/jobs')->getContent())->toContain('noindex');
});
