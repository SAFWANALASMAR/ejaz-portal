<?php

use App\Actions\Accounts\BuildParticipationStats;
use App\Actions\Competitions\StartAttempt;
use App\Enums\DocumentCategory;
use App\Enums\InquiryType;
use App\Enums\LibrarySection;
use App\Http\Requests\ContactRequest;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\Inquiry;
use App\Models\LibraryItem;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Support\ContentBlocks;
use App\Support\ContentCache;
use App\Support\FormTimer;
use App\Support\MenuSections;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| ملاحظات المدير التنفيذي على رابط العرض (1 نوفمبر 2026 — docs/decisions/2026-11-01-director-notes.md): ما يُختبر من الخادم.
| ووثائق الحوكمة «للاطلاع فقط» (البند ٢٢) في GovernanceViewOnlyTest، وما في المتصفح في tests/browser/director-notes-nov1.spec.mjs.
*/

/**
 * @param  array<string, mixed>  $attributes
 */
function nov1MenuItem(string $label, string $url, array $attributes = []): MenuItem
{
    return MenuItem::query()->create(['label' => $label, 'url' => $url, 'is_visible' => true, 'sort' => 0, 'opens_in_new_tab' => false, ...$attributes]);
}

it('opens every header dropdown with the parent page itself, then its real sections (items 3 and 4)', function () {
    nov1MenuItem('مكتبة إعجاز', '/library');
    $media = nov1MenuItem('المركز الإعلامي', '/media');
    nov1MenuItem('الأخبار', '/media/news', ['parent_id' => $media->id, 'sort' => 1]);
    nov1MenuItem('صفحة أضافها المحرر', '/competitions', ['parent_id' => $media->id, 'sort' => 2]);
    nov1MenuItem('الحوكمة', '/governance');
    nov1MenuItem('الخدمات الإلكترونية', '/services');
    Document::factory()->create(['category' => DocumentCategory::AnnualReports]);
    Document::factory()->unpublished()->create(['category' => DocumentCategory::Official]);

    $html = $this->get('/contact')->assertOk()->getContent();

    // المكتبة: الأم ثم أقسامها الأربعة
    expect($html)->toContain('data-nav-dropdown')->not->toContain('aria-haspopup')
        ->and(MenuSections::for('/library'))->toHaveCount(4)
        ->and(array_column(MenuSections::for('/library'), 'url'))->toBe(array_map(fn (LibrarySection $section): string => '/library/'.$section->value, LibrarySection::cases()));

    // المركز الإعلامي: كل أقسامه و«قياس الرضا» دائماً (قرار صفوان، 11 نوفمبر 2026)، وعنصر المحرر بعدها مرة واحدة، والأخبار لا تتكرر
    $mediaChildren = MenuSections::children(['label' => 'المركز الإعلامي', 'url' => '/media', 'children' => [
        ['id' => 9, 'label' => 'الأخبار', 'url' => '/media/news', 'opens_in_new_tab' => false],
        ['id' => 10, 'label' => 'صفحة أضافها المحرر', 'url' => '/competitions', 'opens_in_new_tab' => false],
    ]]);
    expect(array_column($mediaChildren, 'url'))->toBe(['/media', '/media/news', '/media/announcements', '/media/albums', '/library/videos', '/media/partners', '/media/social', '/surveys', '/competitions'])
        ->and($mediaChildren[0]['label'])->toBe('المركز الإعلامي');

    // الحوكمة: تصنيفاتها الخمسة، فيها وثيقة أو لا (قرار صفوان، 11 نوفمبر 2026 — docs/decisions/2026-11-11-fix-show-empty-sections.md)
    expect(array_column(MenuSections::for('/governance'), 'url'))->toBe(array_map(fn (DocumentCategory $category): string => '/governance?category='.$category->value, DocumentCategory::cases()));

    // الخدمات: ما في صفحة الخدمات نفسها، ثم «متجر الجمعية» إلى صفحة المتجر (ملاحظات المدير، 4 نوفمبر 2026 ليلاً)
    expect(array_column(MenuSections::for('/services'), 'label'))->toBe([...array_map(fn (array $tile): string => __($tile['label']), MenuSections::availableServices()), __('store.menu.services_item')]);

    // وفي الرأس: أول عنصر في قائمة المركز الإعلامي رابط الصفحة الأم
    preg_match('/<ul id="menu-'.$media->id.'"[^>]*>(.*?)<\/ul>/s', $html, $dropdown);
    preg_match_all('/href="([^"]+)"/', $dropdown[1], $links);
    expect($links[1][0])->toBe(url('/media'))->and($links[1])->toContain(url('/competitions'));
});

it('marks only the parent page current on the parent itself, not on its sections', function () {
    $governance = nov1MenuItem('الحوكمة', '/governance');
    Document::factory()->create(['category' => DocumentCategory::Policies]);

    $html = $this->get('/governance?category=policies')->assertOk()->getContent();
    preg_match('/<ul id="menu-'.$governance->id.'"[^>]*>(.*?)<\/ul>/s', $html, $dropdown);

    expect(substr_count($dropdown[1], 'aria-current="page"'))->toBe(1)
        ->and($dropdown[1])->toMatch('/href="[^"]*category=policies"[^>]*aria-current="page"/s');
});

it('links each home identity card to its section of the about page when the page is published (item 8)', function () {
    // بلا «عن الجمعية» منشورة لا رابط إلى مقاطعها، وبطاقة «مبادراتنا» الفارغة إلى صفحة المبادرات (قرار صفوان، 11 نوفمبر 2026 — docs/decisions/2026-11-11-fix-show-empty-sections.md)
    $this->get('/')->assertOk()->assertDontSee('/about#about-', false);

    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true]);
    Cache::flush();

    $html = $this->get('/')->assertOk()->getContent();

    foreach (['vision', 'goals', 'principles'] as $section) {
        expect($html)->toContain('href="'.route('about').'#about-'.$section.'"');
    }

    // ومراسي «عن الجمعية» من عناوين مقاطعها
    expect(ContentBlocks::anchor(__('ui.identity.vision')))->toBe('about-vision')
        ->and(ContentBlocks::anchor('الأهداف'))->toBe('about-goals')
        ->and(ContentBlocks::anchor('قيم الجمعية'))->toBe('about-values')
        ->and(ContentBlocks::anchor('عنوان آخر'))->toBeNull();
});

// رابط العرض (5 نوفمبر 2026): عنوان «أهداف الجمعية» كان بلا مرساة، فتفتح «الأهداف» في القائمة رأس الصفحة. والمطابقة الكلمة وحدها
// أو متبوعة بلاحقة معتمدة («الجمعية»)، لا كل عنوان أوله الكلمة (قرار المشرف على طلب الدمج 183، المنخفضة 1)
it('anchors an about section whose heading is the word alone or followed by an approved suffix', function () {
    expect(ContentBlocks::anchor('أهداف الجمعية'))->toBe('about-goals')
        ->and(ContentBlocks::anchor('الأهداف'))->toBe('about-goals')
        ->and(ContentBlocks::anchor('القيم'))->toBe('about-values')
        ->and(ContentBlocks::anchor('رؤية الجمعية'))->toBe('about-vision')
        ->and(ContentBlocks::anchor('رسالة الجمعية'))->toBe('about-mission')
        ->and(ContentBlocks::anchor('مبادئ الجمعية'))->toBe('about-principles')
        // علامات الترقيم الختامية والمسافات، وتوحيد الهمزة
        ->and(ContentBlocks::anchor('  أهداف   الجمعية: '))->toBe('about-goals')
        ->and(ContentBlocks::anchor('الأهداف.'))->toBe('about-goals')
        ->and(ContentBlocks::anchor('اهداف الجمعية'))->toBe('about-goals')
        ->and(ContentBlocks::anchor('الاهداف'))->toBe('about-goals')
        // لا كل عنوان أوله الكلمة، ولا الكلمة بعضاً ولا في الوسط
        ->and(ContentBlocks::anchor('رؤية 2030'))->toBeNull()
        ->and(ContentBlocks::anchor('رسالة رئيس المجلس'))->toBeNull()
        ->and(ContentBlocks::anchor('القيم المضافة'))->toBeNull()
        ->and(ContentBlocks::anchor('الأهداف الاستراتيجية'))->toBeNull()
        ->and(ContentBlocks::anchor('قيمة مضافة'))->toBeNull()
        ->and(ContentBlocks::anchor('من أهداف الجمعية'))->toBeNull()
        ->and(ContentBlocks::anchor(null))->toBeNull()
        ->and(ContentBlocks::anchor(''))->toBeNull()
        ->and(ContentBlocks::anchor('..'))->toBeNull();

    // وفي الصفحة: مقطع «أهداف الجمعية» يحمل id المرساة التي تفتحها القائمة
    $page = Page::query()->where('slug', Page::ABOUT)->first() ?? Page::factory()->create(['slug' => Page::ABOUT]);
    $page->forceFill(['is_published' => true, 'blocks' => [['type' => 'text', 'data' => ['heading' => 'أهداف الجمعية', 'body' => '<p>هدف</p>']]]])->save();
    ContentCache::flush();

    expect($this->get(route('about'))->assertOk()->getContent())->toMatch('/\sid="about-goals"/');
});

// المنخفضة 2: عنوانان متطابقان في الصفحة، فالمرسى لأولهما وحده ولا يتكرر المعرّف
it('gives the anchor to the first matching heading on the page only', function () {
    $page = Page::query()->where('slug', Page::ABOUT)->first() ?? Page::factory()->create(['slug' => Page::ABOUT]);
    $page->forceFill(['is_published' => true, 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'أهداف الجمعية', 'body' => '<p>الأول</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الأهداف', 'body' => '<p>الثاني</p>']],
    ]])->save();
    ContentCache::flush();

    $html = (string) $this->get(route('about'))->assertOk()->getContent();

    expect(substr_count($html, 'id="about-goals"'))->toBe(1)
        ->and((string) str($html)->after('id="about-goals"')->before('</section>'))->toContain('الأول');
});

it('rotates every published initiative in the initiatives card, linked to the initiatives page (items 8 and 9)', function () {
    foreach (['قيم', 'تطوير', 'كنوز', 'ابتكار', 'ارتقاء'] as $sort => $name) {
        Initiative::factory()->create(['name' => $name, 'sort' => $sort, 'is_published' => true]);
    }
    Initiative::factory()->create(['name' => 'مسودة لا تظهر', 'is_published' => false]);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/data-identity-card="initiatives".*?<\/x-carousel>|data-identity-card="initiatives"(.*?)data-identity-card|data-identity-card="initiatives"(.*)/s', $html, $card);
    $card = $card[0];

    expect(substr_count($card, 'data-initiative-slide'))->toBeGreaterThanOrEqual(5)
        ->and($card)->toContain('data-autoplay-always')
        ->and($card)->toContain('href="'.route('initiatives.index').'"')
        ->and($card)->not->toContain('مسودة لا تظهر');
});

it('keeps the YouTube and books strips when no thumbnail or cover was fetched (item 24)', function () {
    LibraryItem::factory()->video('https://www.youtube.com/watch?v=abcdefghijk')->create(['title' => 'مقطع بلا صورة', 'thumbnail' => null]);
    LibraryItem::factory()->create(['title' => 'كتاب بلا غلاف', 'thumbnail' => null]);
    LibraryItem::factory()->create(['title' => 'بحث بلا غلاف', 'section' => LibrarySection::Research, 'thumbnail' => null]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-media-strip="videos"')->toContain('data-media-strip="books"')
        ->toContain('مقطع بلا صورة')->toContain('كتاب بلا غلاف')
        // والأبحاث في قسمها بالمكتبة لا في الرئيسية (ملاحظات المدير ليلة 5 نوفمبر 2026، البند 3)
        ->not->toContain('بحث بلا غلاف')
        ->toContain('data-thumb-placeholder');
});

it('shows the partners strip that the 26 October migration switched off, once, and restores it on rollback (item 12)', function () {
    $migration = require database_path('migrations/2026_11_01_120000_show_home_partners_once.php');
    Setting::put('home', ['show_partners' => false, 'show_latest' => true]);
    Partner::factory()->create(['name' => 'شريك منشور']);
    DB::table('settings')->where('key', 'home_partners_before')->delete();

    $migration->up();
    Cache::flush();

    expect(Setting::group('home')['show_partners'])->toBeTrue()
        ->and(Setting::group('home')['show_latest'])->toBeTrue();
    $this->get('/')->assertOk()->assertSee('شريك منشور');

    // ومرة واحدة: إطفاؤه من اللوحة بعدها يبقى
    Setting::put('home', ['show_partners' => false]);
    $migration->up();
    Cache::flush();
    expect(Setting::group('home')['show_partners'])->toBeFalse();

    $migration->down();
    Cache::flush();
    expect(Setting::group('home')['show_partners'])->toBeFalse()
        ->and(DB::table('settings')->where('key', 'home_partners_before')->exists())->toBeFalse();
});

it('counts the dashboard competition entries from the same source as my entries, for a Brahin student with a submitted attempt (item 17)', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);
    $competition = Competition::factory()->student()->create(['slug' => 'students']);
    $round = $competition->rounds()->firstOrFail();
    studentQuestion($round);
    $student = registeredStudent($competition);
    Notification::fake();
    app(StartAttempt::class)->handle($student, $round);

    $this->actingAs($student)->post('/competitions/students/rounds/'.$round->uuid.'/submit')->assertRedirect();

    $listed = CompetitionSubmission::query()->ownedBy($student)->count();
    expect($listed)->toBe(1);

    $this->actingAs($student)->get(route('account.competitions'))->assertOk()->assertSee($competition->title);
    $dashboard = $this->actingAs($student)->get('/account')->assertOk()->getContent();

    preg_match('/'.preg_quote(__('portals.member_entries'), '/').'<\/span><\/dt>\s*<dd[^>]*>(\d+)<\/dd>/u', $dashboard, $count);
    expect($count[1] ?? null)->toBe((string) $listed);
});

it('returns the inquiry as JSON with «تم تسجيل طلبك» and the request number, without a page reload (item 23)', function () {
    Notification::fake();
    Setting::put('contact', ['email' => 'contact@example.test']);
    $session = Str::random(40);
    $form = [
        'name' => 'زائر', 'email' => 'visitor@example.com', 'type' => InquiryType::Inquiry->value,
        'subject' => 'سؤال', 'message' => 'نص الرسالة', 'form_token' => FormTimer::token($session),
    ];
    $this->travel(5)->seconds();

    $response = $this->withCookie(config('session.cookie'), $session)->withCredentials()->postJson('/contact', $form)->assertCreated();

    $inquiry = Inquiry::sole();
    expect($response->json('reference'))->toBe($inquiry->reference)
        ->and($response->json('message'))->toContain('تم تسجيل طلبك')->toContain($inquiry->reference);

    // الختم لمرة واحدة: الإرسال الثاني بخطأ JSON، ولا رسالة ثانية
    $this->withCookie(config('session.cookie'), $session)->withCredentials()->postJson('/contact', $form)->assertStatus(422)->assertJsonValidationErrors('form_token');
    expect(Inquiry::count())->toBe(1);
});

it('answers JSON validation errors, and a trap with the same fake success and nothing saved (item 23)', function () {
    Notification::fake();
    $session = Str::random(40);
    $token = FormTimer::token($session);
    $this->travel(5)->seconds();

    $this->withCookie(config('session.cookie'), $session)
        ->withCredentials()->postJson('/contact', ['form_token' => $token, 'name' => '', 'email' => 'bad'])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

    $trap = $this->withCookie(config('session.cookie'), $session)
        ->withCredentials()->postJson('/contact', ['form_token' => FormTimer::token($session), ContactRequest::TRAP => 'bot'])
        ->assertCreated();

    expect($trap->json('message'))->toContain('تم تسجيل طلبك')
        ->and(Inquiry::count())->toBe(0);
});

it('shows the success panel in place of the form after the no-script redirect (item 23)', function () {
    $this->withSession(['inquiry_reference' => 'INQ-ABCDEFGH'])->get('/contact')->assertOk()
        ->assertSee('data-contact-success', false)
        ->assertSee(__('contact.success.title'))
        ->assertSee('INQ-ABCDEFGH')
        ->assertSee('data-async-form', false);

    // بلا إرسال: النموذج ظاهر واللوحة مخفية
    $this->flushSession();
    $html = $this->get('/contact')->assertOk()->getContent();
    expect($html)->toMatch('/data-contact-success[^>]*hidden/s');
});

it('sends every back link through the browser history with the parent or home as the fallback (item 19)', function () {
    Document::factory()->create(['slug' => 'a-policy']);

    $html = $this->get('/governance/a-policy')->assertOk()->getContent();
    preg_match('/<a href="([^"]+)" data-history-back/', $html, $back);

    // صفحة لها أم منطقية: الرجوع بالتاريخ، والأم بديل لمن فتحها مباشرة (لا wire:navigate يسبق الرجوع)
    expect($back[1] ?? null)->toBe(route('governance.index'));
    expect($html)->not->toMatch('/data-history-back[^>]*wire:navigate|wire:navigate[^>]*data-history-back/');
});

it('puts the big calligraphy under every inner page title and under the transition logo (items 13 and 14)', function () {
    $html = $this->get('/contact')->assertOk()->getContent();

    expect($html)->toContain('data-page-calligraphy')->toContain('data-transition-calligraphy');
});

it('shows a compact one-row how-to on the programs page and the full one on a program page (item 20)', function () {
    $program = Program::factory()->create();

    expect($this->get('/programs')->assertOk()->getContent())->toMatch('/data-programs-how-to\s+data-compact/');
    expect($this->get('/programs/'.$program->slug)->assertOk()->assertSee('data-programs-how-to', false)->getContent())->not->toMatch('/data-programs-how-to\s+data-compact/');
});

it('shows the full licence number centred in the header licence box (item 1)', function () {
    $this->get('/contact')->assertOk()->assertSeeInOrder(['data-header-licence', '1000723700'], false);
});

it('keeps every editor item of a dropdown, even one sharing the parent link, and puts the parent first', function () {
    $children = MenuSections::children(['label' => 'عن الجمعية', 'url' => '/about', 'opens_in_new_tab' => false, 'children' => [
        ['id' => 1, 'label' => 'عن الجمعية', 'url' => '/about', 'opens_in_new_tab' => false],
        ['id' => 2, 'label' => 'التوجه الاستراتيجي', 'url' => '/about', 'opens_in_new_tab' => false],
        ['id' => 3, 'label' => 'اللوائح والسياسات', 'url' => '/governance?category=policies', 'opens_in_new_tab' => false],
    ]]);

    expect(array_column($children, 'label'))->toBe(['عن الجمعية', 'التوجه الاستراتيجي', 'اللوائح والسياسات'])
        ->and($children[0]['is_parent'] ?? false)->toBeTrue();
});

it('marks a single top-level item current when a generated section repeats another item\'s link', function () {
    nov1MenuItem('مكتبة إعجاز', '/library', ['sort' => 1]);
    nov1MenuItem('المركز الإعلامي', '/media', ['sort' => 2]);
    $about = nov1MenuItem('عن الجمعية', '/about', ['sort' => 0]);
    nov1MenuItem('اللوائح', '/governance?category=policies', ['parent_id' => $about->id]);
    nov1MenuItem('الحوكمة', '/governance', ['sort' => 3]);
    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true]);
    Document::factory()->create(['category' => DocumentCategory::Policies]);

    foreach (['/library/videos', '/governance?category=policies'] as $path) {
        preg_match('/<nav data-main-nav.*?<\/nav>/s', $this->get($path)->assertOk()->getContent(), $nav);
        expect(substr_count($nav[0], 'data-current'))->toBe(1, $path);
    }
});

it('answers the daily mailbox limit with a fake success indistinguishable from a real one, storing and sending nothing (item 23)', function () {
    Notification::fake();
    $session = Str::random(40);
    $token = FormTimer::token($session);
    $this->travel(5)->seconds();
    $form = ['name' => 'زائر', 'email' => 'limit@example.com', 'type' => InquiryType::Inquiry->value, 'subject' => 'سؤال', 'message' => 'نص', 'form_token' => $token];

    $key = 'contact-email:'.hash('sha256', 'limit@example.com');
    foreach (range(1, 5) as $i) {
        RateLimiter::hit($key, 86400);
    }

    $response = $this->withCookie(config('session.cookie'), $session)->withCredentials()->postJson('/contact', $form)->assertCreated();

    // الشكل نفسه للنجاح الحقيقي: الحالة والمفتاحان والرسالة ورقم بالصيغة نفسها، بلا كلمة عن الحد
    expect(array_keys($response->json()))->toBe(['message', 'reference'])
        ->and($response->json('reference'))->toMatch('/^INQ-[A-HJ-NP-Z2-9]{8}$/')
        ->and($response->json('message'))->toBe(__('contact.success.registered', ['reference' => $response->json('reference')]))
        ->and($response->getContent())->not->toContain(__('contact.form.daily_limit'))
        ->and(Inquiry::count())->toBe(0);
    Notification::assertNothingSent();

    // والختم استُهلك كالنجاح الحقيقي: إعادة الإرسال نفسه «أُرسلت من قبل»
    $this->withCookie(config('session.cookie'), $session)->withCredentials()->postJson('/contact', $form)->assertStatus(422)->assertJsonValidationErrors('form_token');
});

it('refuses a JSON inquiry without the CSRF token (security review, low 2)', function () {
    $session = Str::random(40);
    $token = FormTimer::token($session);
    $this->travel(5)->seconds();

    // لارافيل يتخطى فحص الرمز في الاختبارات، فيُستبدل بنسخة تفحص كما في الإنتاج (كما في CsrfCorsTest)
    $this->app->singleton(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->withCookie(config('session.cookie'), $session)->withCredentials()
        ->postJson('/contact', ['name' => 'زائر', 'email' => 'v@example.com', 'type' => InquiryType::Inquiry->value, 'subject' => 'س', 'message' => 'ن', 'form_token' => $token])
        ->assertStatus(419);

    expect(Inquiry::count())->toBe(0);
});

it('does not count another user\'s competition entries on the dashboard (security review, low 1)', function () {
    $mine = User::factory()->create();
    $other = User::factory()->create();
    CompetitionSubmission::factory()->for($other)->submitted()->create();
    CompetitionSubmission::factory()->for($other)->create();

    expect(CompetitionSubmission::query()->ownedBy($mine)->count())->toBe(0)
        ->and(app(BuildParticipationStats::class)->handle($mine)['entries'])->toBe(0)
        ->and(app(BuildParticipationStats::class)->handle($other)['entries'])->toBe(2);
});

it('never sends reading progress from the governance view-only reader', function () {
    Storage::fake(Document::disk());
    $document = Document::factory()->viewOnly()->create(['slug' => 'no-progress']);
    Storage::disk(Document::disk())->put((string) $document->file, pdfContents());

    $html = $this->get('/governance/no-progress')->assertOk()->getContent();

    expect($html)->toContain('data-pdf-reader')->not->toContain('data-progress');
});
