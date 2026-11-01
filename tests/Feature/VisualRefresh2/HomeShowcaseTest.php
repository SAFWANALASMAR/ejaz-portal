<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Models\Album;
use App\Models\Competition;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Setting;
use App\Support\HomeContent;
use App\Support\HomeShowcase;
use Illuminate\Validation\ValidationException;

/*
| بنية الرئيسية من مرجع المدير التنفيذي (plans/2026-10-30-director-home-reference.md): العارض، وشرائح الهوية، ومجموع جوائز
| البراهين، والمستجدات — من بيانات البوابة وحدها.
*/

function publishedAboutWith(array $sections): Page
{
    return Page::factory()->create([
        'slug' => Page::ABOUT,
        'is_published' => true,
        'blocks' => array_map(fn (string $heading, string $body): array => ['type' => 'text', 'data' => ['heading' => $heading, 'body' => '<p>'.$body.'</p>']], array_keys($sections), $sections),
    ]);
}

it('sums the published Brahin prize table for both genders', function () {
    $competition = Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'prize_table' => [['male' => 2000, 'female' => 2000], ['male' => 1700, 'female' => 1700], ['male' => 300, 'female' => 0]],
    ]);

    expect(HomeShowcase::prizeTotal($competition))->toBe(7700)
        ->and(HomeShowcase::prize())->toMatchArray(['total' => 7700, 'url' => route('competitions.show', $competition->slug)]);

    // في بطاقة المسابقة بالإعلانات، يعدّ تصاعدياً حتى المجموع (البند 4 من المرحلة ب)
    $this->get('/')->assertOk()->assertSee('data-prize-total', false)->assertSee('data-count-to="7700">7,700', false);
});

// بطاقة «نحو فهم أعمق» بجانب «خدماتنا» (البند H4): ظاهرة دائماً، ورابطها إلى «عن الجمعية» متى نُشرت وحدها
it('shows the deeper-understanding card beside the services, linking to the about page only once published', function () {
    $card = fn (): string => (string) str($this->get('/')->assertOk()->getContent())->after('aria-labelledby="home-shortcuts"')->before('</section>');

    expect($card())->toContain('data-side-card')
        ->and($card())->toContain(__('home.sections_final.identity.side_title'))
        ->and($card())->not->toContain('href="'.route('about').'"');

    publishedAboutWith(['الرؤية' => 'نص']);

    expect($card())->toContain('href="'.route('about').'"');
});

it('shows no prize card for an unpublished competition or an empty prize table', function () {
    Competition::factory()->create(['student_mode' => true, 'is_published' => false, 'prize_table' => [['male' => 100, 'female' => 100]]]);
    expect(HomeShowcase::prize())->toBeNull();

    Competition::factory()->create(['student_mode' => true, 'is_published' => true, 'prize_table' => []]);
    expect(HomeShowcase::prize())->toBeNull();

    $this->get('/')->assertOk()->assertDontSee('data-prize-card', false);
});

it('takes the vision and mission from the published about page, then the four values with their icons', function () {
    publishedAboutWith(['الرؤية' => 'نص رؤية الاختبار', 'الرسالة' => 'نص رسالة الاختبار']);

    $slides = HomeShowcase::identity(HomeContent::resolved());

    expect(array_column($slides, 'kind'))->toBe(['vision', 'mission', 'value', 'value', 'value', 'value'])
        ->and($slides[0]['text'])->toBe('نص رؤية الاختبار')
        ->and($slides[1]['text'])->toBe('نص رسالة الاختبار')
        ->and(array_column(array_slice($slides, 2), 'icon'))->toBe(HomeContent::VALUE_ICONS);
});

it('prefers the panel vision over the about page and hides an empty mission', function () {
    publishedAboutWith(['الرؤية' => 'رؤية من الصفحة']);

    app(UpdateHomeContent::class)->handle(userWithRole(RoleName::ContentEditor), [
        'identity' => ['vision' => 'رؤية من اللوحة'],
        'values' => ['items' => [['title' => 'قيمة واحدة', 'icon' => 'star'], ['title' => ''], ['title' => ''], ['title' => '']]],
    ]);

    $slides = HomeShowcase::identity(HomeContent::resolved());

    expect(array_column($slides, 'kind'))->not->toContain('mission')
        ->and($slides[0]['text'])->toBe('رؤية من اللوحة')
        ->and(collect($slides)->firstWhere('title', 'قيمة واحدة')['icon'])->toBe('star');
});

it('hides the vision slide when neither the panel nor a published about page has it', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => false, 'blocks' => [['type' => 'text', 'data' => ['heading' => 'الرؤية', 'body' => '<p>مسودة</p>']]]]);

    expect(array_column(HomeShowcase::identity(HomeContent::resolved()), 'kind'))->not->toContain('vision');
});

it('rejects a value icon outside the allowed list', function () {
    expect(fn () => app(UpdateHomeContent::class)->handle(userWithRole(RoleName::ContentEditor), [
        'values' => ['items' => [['icon' => '../../etc/passwd'], [], [], []]],
    ]))->toThrow(ValidationException::class);
});

it('renders the identity slider as an accessible carousel with one active slide', function () {
    publishedAboutWith(['الرؤية' => 'نص رؤية الاختبار']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-identity-slider')
        ->and($html)->toContain('aria-roledescription="'.__('ui.slider.carousel').'"')
        ->and($html)->toContain('aria-live="polite"')
        ->and($html)->toContain('data-autoplay')
        ->and(preg_match_all('/data-carousel-item\s+data-active/', $html))->toBeGreaterThanOrEqual(1)
        ->and($html)->toContain('نص رؤية الاختبار');
});

// أمر التصور النهائي، النسخة 63 (البند H1): الترتيب تعريف الجمعية، ثم خادم الحرمين الشريفين، ثم رئيس الجمعية، ثم البراهين متى
// نُشرت، ثم المكتبة. والصور من «محتوى الرئيسية»، وإلا بدائلها
it('builds the hero slides in the approved order, with the Brahin slide only for a published student competition', function () {
    $hero = ['title' => __('site.name'), 'text' => 'تعريف الجمعية', 'button' => null];
    $content = HomeContent::resolved();

    $slides = HomeShowcase::slides([], $content, $hero);
    expect(array_column($slides, 'key'))->toBe(['about', 'king', 'chairman', 'library'])
        ->and($slides[0]['title'])->toBe(__('site.short_name'))
        ->and($slides[0]['subtitle'])->not->toBeEmpty()
        ->and($slides[0]['src'])->toContain('images/placeholders/architecture.svg')
        ->and($slides[1]['src'])->toBe($content['king']['image'])
        ->and($slides[1]['title'])->toBe(__('home.sections.king.signature_title'))
        ->and($slides[2]['title'])->toBe(__('home.sections_final.chairman.name'))
        ->and($slides[3]['button']['url'])->toBe(route('library.index'));

    Competition::factory()->create(['student_mode' => true, 'is_published' => false, 'title' => 'مسودة']);
    $competition = Competition::factory()->create(['student_mode' => true, 'is_published' => true, 'title' => 'البراهين العلمية']);
    Album::factory()->create(['is_published' => true, 'photos' => ['albums/a.jpg']]);

    $slides = HomeShowcase::slides([], HomeContent::resolved(), $hero);
    expect(array_column($slides, 'key'))->toBe(['about', 'king', 'chairman', 'brahin', 'library'])
        ->and($slides[0]['src'])->toEndWith('albums/a.jpg')
        ->and($slides[3]['title'])->toBe('البراهين العلمية')
        ->and($slides[3]['button']['url'])->toBe(route('competitions.show', $competition->slug));

    // صورة البطل من الإعدادات تتقدم على الألبوم، وصورة الشريحة من «محتوى الرئيسية» تتقدم على بديلها
    Setting::put(HomeContent::KEY, ['hero_slides' => ['king' => 'settings/king-wide.jpg']]);
    $slides = HomeShowcase::slides(['hero_image' => 'settings/hero.jpg'], HomeContent::resolved(), $hero);
    expect($slides[0]['src'])->toEndWith('settings/hero.jpg')
        ->and($slides[1]['src'])->toEndWith('settings/king-wide.jpg');
});

it('renders the hero slides with the first title as the page heading, the first image eager and the rest lazy', function () {
    $html = $this->get('/')->assertOk()->assertSee('data-hero-slider', false)->assertSee('data-hero-progress', false)->getContent();
    preg_match_all('/<figure data-hero-slide="(\w+)"/', $html, $keys);
    preg_match_all('/<figure data-hero-slide.*?<img[^>]*>/s', $html, $images);

    expect($keys[1])->toBe(['about', 'king', 'chairman', 'library'])
        ->and($images[0][0])->toContain('fetchpriority="high"')->not->toContain('loading="lazy"')
        ->and($images[0][1])->toContain('loading="lazy"')
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('data-carousel-toggle');
});

it('saves a hero slide image from the home content screen and cleans it with the other images', function () {
    expect(HomeContent::imagePaths(['hero_slides' => ['library' => 'settings/library.jpg']]))->toBe(['settings/library.jpg']);
});

it('refreshes the cached home when the competition is saved or deleted', function () {
    $competition = Competition::factory()->create(['student_mode' => true, 'is_published' => true, 'prize_table' => [['male' => 1000, 'female' => 1000]]]);
    $this->get('/')->assertOk()->assertSee('2,000');

    $competition->forceFill(['prize_table' => [['male' => 3000, 'female' => 3000]]])->save();
    $this->get('/')->assertOk()->assertSee('6,000')->assertDontSee('2,000');

    $competition->delete();
    $this->get('/')->assertOk()->assertDontSee('data-prize-total', false);
});

it('stacks the short text sections on the about page only, and keeps a news body as running text', function () {
    $blocks = [
        ['type' => 'text', 'data' => ['heading' => 'الأول', 'body' => '<p>نص قصير</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الثاني', 'body' => '<p>نص قصير آخر</p>']],
    ];
    publishedAboutWith(['الأول' => 'نص قصير', 'الثاني' => 'نص قصير آخر']);
    $article = NewsArticle::factory()->create(['is_published' => true, 'blocks' => $blocks]);

    $this->get('/about')->assertOk()->assertSee('data-stack', false);
    $this->get(route('media.news.show', $article->slug))->assertOk()->assertSee('نص قصير آخر')->assertDontSee('data-stack', false);
});
