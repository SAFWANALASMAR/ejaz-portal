<?php

use App\Enums\LibrarySection;
use App\Models\Album;
use App\Models\BoardMember;
use App\Models\Competition;
use App\Models\Document;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Setting;
use App\Support\HomeContent;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Support\Facades\Storage;

/*
| الصفحات الداخلية على التصور النهائي، النسخة 63 (المرحلة ج من #111 — docs/decisions/2026-10-31-visual-refresh-2.md، القسم ٢٠ فما
| بعده): بالمكونات المشتركة نفسها، وبلا تغيير في المنطق ولا الصلاحيات ولا القاعدة.
*/

it('shows the about page with the society image, the two speeches, the rationale and the board as a moving strip', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية']);
    BoardMember::factory()->count(2)->create();

    $html = (string) $this->get('/about')->assertOk()->getContent();

    expect($html)
        // صورة البطل صورة المبررات من «محتوى الرئيسية» لا الرسم المؤقت (البندان P1 وN5)
        ->toContain('src="'.HomeContent::resolved()['rationale']['image'].'"')
        ->toContain('id="about-king"')->toContain('id="about-chairman"')->toContain('id="about-rationale"')
        ->toContain('data-rationale-calligraphy')
        // بلا صورتي الكلمتين (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٤): الصورتان في شرائح البطل وحدها
        ->toContain('data-chairman-card')->not->toContain('data-image-slot="chairman"')
        // الاسم الموحد (البند N4) بلا الشرطة المائلة
        ->toContain('الأستاذ الدكتور عبدالله بن عبدالعزيز المصلح')
        ->not->toContain('الأستاذ الدكتور / عبدالله')
        // مجلس الإدارة شريط متحرك (البند R16): نسختان، والثانية مخفية عن قارئ الشاشة
        ->toContain('id="about-board-strip"')
        ->and(substr_count($html, 'data-person-card'))->toBe(4);
});

it('shows the two speeches on the about page without the photos or their approved image slots (item 24)', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية']);

    $html = (string) $this->get('/about')->assertOk()->getContent();
    $king = (string) str($html)->after('aria-labelledby="about-king"')->before('</section>');
    $chairman = (string) str($html)->after('aria-labelledby="about-chairman"')->before('</section>');

    expect($king)->not->toContain('<img')->toContain(HomeContent::resolved()['king']['signature_name'])
        ->and($chairman)->not->toContain('<img')->toContain('data-chairman-name')
        ->and($html)->not->toContain(e(__('ui.inner.image_slot_chairman')))->not->toContain(e(__('ui.inner.image_slot_king')));
});

it('draws the programs bar on a pale panel floating over the hero edge', function () {
    $html = (string) $this->get('/programs')->assertOk()->getContent();

    // والكبسولات صارت مربعات أيقونات بمراسٍ (ملاحظات المدير التنفيذي، 4 نوفمبر 2026 — DirectorNotesNov4Test)
    expect($html)->toContain('tabs-bar')->toContain('data-programs-bar')
        // «كيف تسجّل» صف مضغوط بخطوات ملونة (ملاحظات المدير، 1 نوفمبر 2026، البند ٢٠)
        ->toContain('id="programs-how-to-title"')
        ->toContain('tone-card how-to-compact-step');
});

it('shows the Brahin hero with its image, the register button twice for desktop and mobile, and less spacing', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    (new BrahinCompetitionSeeder)->run();
    Competition::query()->where('slug', 'brahin-1448')->sole()
        ->forceFill(['is_published' => true, 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek()])->save();

    Storage::fake('public');
    Setting::put(HomeContent::KEY, ['hero_slides' => ['brahin' => 'settings/brahin.jpg']]);

    $html = (string) $this->get('/competitions/brahin-1448')->assertOk()->getContent();

    expect($html)->toContain('src="'.Storage::disk('public')->url('settings/brahin.jpg').'"')
        ->toMatch('/data-page-hero class="page-hero-light" data-shade="[1-4]"\s+data-compact/')
        ->toContain('data-at="desktop"')->toContain('data-at="mobile"')
        ->toContain(__('ui.inner.register_now'))
        // على الجوال: العنوان ثم بطاقة الجوائز ثم الزر بعرض كامل
        ->and(strpos($html, 'data-prize-total'))->toBeLessThan(strpos($html, 'data-at="mobile"'))
        ->and(substr_count($html, 'href="'.route('competitions.student.register', 'brahin-1448').'"'))->toBeGreaterThanOrEqual(4);
});

it('hides the register button once registration is closed, and marks a missing season', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    (new BrahinCompetitionSeeder)->run();
    $competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $content = (array) $competition->page_content;
    unset($content['season']);
    $competition->forceFill(['is_published' => true, 'page_content' => $content, 'opens_at' => now()->subWeek(), 'closes_at' => now()->subMinute()])->save();

    $this->get('/competitions/brahin-1448')->assertOk()
        ->assertDontSee('data-student-register', false)
        ->assertSee(__('ui.inner.season_missing'));
});

it('splits the competitions page into the current one, upcoming without registration, and a past archive with results', function () {
    $current = Competition::factory()->create(['title' => 'المسابقة المفتوحة']);
    Competition::factory()->upcoming()->create(['title' => 'مسابقة لاحقة']);
    Competition::factory()->closed()->create(['title' => 'مسابقة منتهية', 'results_approved_at' => now()]);

    $html = (string) $this->get('/competitions')->assertOk()->getContent();

    expect($html)->toContain('data-competitions-current')->toContain('data-competitions-upcoming')->toContain('data-competitions-past')
        ->toContain(__('competitions.statuses.upcoming'))->toContain('data-results-approved')
        ->and(strpos($html, 'المسابقة المفتوحة'))->toBeLessThan(strpos($html, 'مسابقة لاحقة'))
        ->and(strpos($html, 'مسابقة لاحقة'))->toBeLessThan(strpos($html, 'مسابقة منتهية'))
        // لا تسجيل في صفحة المسابقات: التفاصيل وحدها
        ->and($html)->not->toContain('/register"')
        ->toContain('href="'.route('competitions.show', $current->slug).'"');
});

it('shows the four library sections in order as colored tiles and a numbered moving strip for each filled section', function () {
    LibraryItem::factory()->video()->count(2)->create();
    LibraryItem::factory()->create(['section' => LibrarySection::Books, 'title' => 'كتاب في الشريط']);

    $html = (string) $this->get('/library')->assertOk()->getContent();
    $labels = array_map(fn (LibrarySection $section): string => $section->getLabel(), LibrarySection::cases());

    expect($labels)->toBe([__('library.sections.videos'), __('library.sections.audio'), __('library.sections.books'), __('library.sections.research')])
        ->and(str($html)->after('data-library-tiles')->before('</ul>')->toString())->toContain('tone-card')
        ->and($html)->toContain('data-library-strip="videos"')->toContain('data-library-strip="books"')
        // القسم الفارغ لا شريط له
        ->not->toContain('data-library-strip="research"')->not->toContain('data-library-strip="audio"')
        ->toContain('id="library-strip-videos"')->toContain('كتاب في الشريط')
        // المقاطع مرقّمة في الشريط
        ->toContain('data-video-number');

    $this->get('/library')->assertSeeInOrder(array_map(fn (string $label): string => e($label), $labels), false);
});

it('fills the book reader frame with the page', function () {
    $item = LibraryItem::factory()->create(['is_downloadable' => false]);
    Storage::disk('local')->put((string) $item->file, pdfContents());

    $this->get(route('library.show', [$item->section->value, $item->slug]))->assertOk()
        ->assertSee('class="pdf-stage"', false)
        ->assertDontSee('class="h-[75vh] max-h-[60rem]', false);
});

it('shows the media centre with colored tiles, the news as a moving strip of separate cards and the photo gallery as a strip', function () {
    NewsArticle::factory()->count(3)->create();
    Album::factory()->create(['title' => 'ألبوم في الشريط']);

    $html = (string) $this->get('/media')->assertOk()->getContent();

    expect(str($html)->after('data-media-tiles')->before('</ul>')->toString())->toContain('tone-card')
        ->and($html)->toContain('id="media-news-strip"')->toContain('data-direction="vertical"')
        ->toContain('id="media-albums-strip"')->toContain('ألبوم في الشريط')->toContain('data-gallery-tile');
});

it('lays the governance categories and documents as adjacent colored tiles, and merges the document page with its data', function () {
    $document = Document::factory()->create(['slug' => 'bylaws']);
    Storage::disk('local')->put($document->file, pdfContents());

    $index = (string) $this->get('/governance')->assertOk()->getContent();

    expect($index)->toContain('data-documents-grid')
        ->and(str($index)->after('data-documents-grid')->before('</ul>')->toString())->toContain('tone-card');

    $this->get('/governance/bylaws')->assertOk()
        ->assertSeeInOrder(['data-document-meta', __('governance.fields.category'), 'data-document-viewer'], false);
});

it('opens the e-services page with programs, competitions, initiatives and inquiries as style «أ» cards', function () {
    $html = (string) $this->get('/services')->assertOk()->getContent();

    // بطاقات النمط «أ» (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٩ — x-service-card)
    expect($html)->toContain('class="service-card group"')
        ->and(strpos($html, 'href="'.route('programs.index').'"'))->toBeLessThan(strpos($html, 'href="'.route('services.jobs').'"'))
        ->and($html)->toContain('href="'.route('competitions.index').'"')
        ->toContain('href="'.route('initiatives.index').'"')
        ->toContain('href="'.route('contact').'"');
});

it('puts the contact form on a light blue gradient panel with colored contact cards', function () {
    Setting::put('contact', ['email' => 'info@ejaz.sa']);

    $this->get('/contact')->assertOk()
        // وإزاحة الرأس من scroll-padding-top على الصفحة وحدها، لا scroll-mt على النموذج (رابط العرض، 5 نوفمبر 2026)
        ->assertSee('class="form-sky p-6 sm:p-8" data-contact-form', false)
        ->assertSee('tone-card group grid', false);
});

it('shows the full official logo on the login, register and password pages', function (string $url) {
    $html = (string) $this->get($url)->assertOk()->getContent();
    $card = (string) str($html)->after('data-auth-card')->before('</h1>');

    expect($card)->toContain(HomeContent::brand('mark'))->toContain(HomeContent::brand('calligraphy'))
        ->toContain('data-logo-calligraphy');
})->with(['/login', '/register', '/forgot-password']);

it('lets the section heading icon float only while on screen', function () {
    $html = (string) $this->get('/services')->assertOk()->getContent();

    expect($html)->toMatch('/class="[^"]*section-heading-icon[^"]*"[^>]*data-loop/');
});
