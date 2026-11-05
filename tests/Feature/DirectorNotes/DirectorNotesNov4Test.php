<?php

use App\Actions\Content\BuildHomePage;
use App\Http\Controllers\ProgramController;
use App\Models\Page;
use App\Models\Program;
use App\Models\ProgramType;
use App\Support\ContentCache;
use App\Support\MenuLink;
use App\Support\MenuSections;
use Database\Seeders\AboutMenuAnchorsSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\ProgramTypesSeeder;

/*
| ملاحظات المدير التنفيذي على رابط العرض، ليلة 4 نوفمبر 2026، المجموعة أ (مدخلات-المدير/ملاحظات-المدير-2026-11-04-ليلاً.md):
| شريط «الدورات والبرامج» الواحد، و«المبادرات» في «عن الجمعية» لا في الخدمات، ونص «تواصل معنا» بلا تكرار، والمخطوطة أكبر قليلاً،
| وبطاقة الدخول والتسجيل أقصر على الحاسوب. وما في المتصفح لقطات docs/design/screenshots/fix-director-nov4-ui/.
*/

it('draws one icon bar on the programs page: each type jumps to its section on the page, and competitions comes last', function () {
    $this->seed(ProgramTypesSeeder::class);
    $types = ProgramType::query()->ordered()->get();
    Program::factory()->for($types[0], 'type')->create(['title' => 'دورة أولى']);
    Program::factory()->for($types[1], 'type')->create(['title' => 'مجلس أول']);
    Program::factory()->unpublished()->for($types[2], 'type')->create(['title' => 'كتاب غير منشور']);

    $html = (string) $this->get('/programs')->assertOk()->getContent();
    $bar = (string) str($html)->after('data-programs-bar')->before('</nav>');

    // الشريط: مرساة لكل نوع فيه برنامج منشور بترتيب اللوحة، بلا تحميل صفحة (لا wire:navigate)، ثم المسابقات إلى صفحتها
    preg_match_all('/data-programs-bar-item="([^"]+)"/', $bar, $items);
    expect($items[1])->toBe([$types[0]->slug, $types[1]->slug, 'competitions'])
        ->and($bar)->toContain('href="#programs-'.$types[0]->slug.'"')
        ->not->toMatch('/href="#[^"]*"[^>]*wire:navigate/')
        ->toContain('href="'.route('competitions.index').'"')
        // والشريطان السابقان (التبويبات وبطاقات المحور) لم يبقيا
        ->and($html)->not->toContain('class="tab-pill"')->not->toContain(route('programs.index', ['type' => $types[0]->slug]));

    // مقطع لكل نوع فيه برنامج منشور بمعرّف مرساته، فيه برامجه وحدها، والنوع الفارغ (أو بغير المنشور وحده) لا يظهر (مراجعة المشرف على #181، L3)
    $first = (string) str($html)->after('data-programs-section="'.$types[0]->slug.'"')->before('</section>');
    expect($html)->toContain('id="programs-'.$types[0]->slug.'"')->toContain('id="programs-'.$types[1]->slug.'"')
        ->not->toContain('id="programs-'.$types[2]->slug.'"')->not->toContain($types[2]->name)
        ->and($first)->toContain('دورة أولى')->not->toContain('مجلس أول');
});

it('shows one empty state, and the competitions tile alone, when no type has a published programme', function () {
    $this->seed(ProgramTypesSeeder::class);

    $html = (string) $this->get('/programs')->assertOk()->getContent();
    preg_match_all('/data-programs-bar-item="([^"]+)"/', (string) str($html)->after('data-programs-bar')->before('</nav>'), $items);

    expect($items[1])->toBe(['competitions'])
        ->and($html)->not->toContain('data-programs-section')->toContain(__('programs.list.empty'));
});

it('shows up to six programmes per section with «view all» to the type list, which keeps its pagination and bar', function () {
    $type = ProgramType::factory()->create(['slug' => 'many', 'name' => 'كثيرة']);
    Program::factory()->count(ProgramController::PER_SECTION + 1)->for($type, 'type')->create();
    $exact = ProgramType::factory()->create(['slug' => 'exact', 'name' => 'بعدد المقطع']);
    Program::factory()->count(ProgramController::PER_SECTION)->for($exact, 'type')->create();

    $html = (string) $this->get('/programs')->assertOk()->getContent();
    $section = (string) str($html)->after('data-programs-section="many"')->before('</section>');
    $full = (string) str($html)->after('data-programs-section="exact"')->before('</section>');

    // «عرض الكل» باسم نوعه لقارئ الشاشة (L4)، ولا «عرض الكل» لنوع بعدد المقطع بالضبط (L7)
    expect(substr_count($section, '<article class="tone-card'))->toBe(ProgramController::PER_SECTION)
        ->and($section)->toContain(route('programs.index', ['type' => 'many']))->toContain('<span class="sr-only">كثيرة</span>')
        ->and(substr_count($full, '<article class="tone-card'))->toBe(ProgramController::PER_SECTION)
        ->and($full)->not->toContain(route('programs.index', ['type' => 'exact']))->not->toContain(__('site.view_all'));

    // قائمة النوع: برامجه كلها، والشريط يعود إلى مقاطع الصفحة الأم، والنوع النشط معلَّم
    $list = (string) $this->get('/programs?type=many')->assertOk()->getContent();
    expect($list)->toContain('href="'.route('programs.index').'#programs-many"')->toContain('aria-current="page"');

    // والترقيم القديم للصفحة كلها تحويل دائم إليها (L1)، وبطاقة المسابقات بالتنقّل السريع القائم (@navigate — L4)
    $this->get('/programs?page=2')->assertStatus(301)->assertRedirect(route('programs.index'));
    $this->get('/programs?type[]=many')->assertRedirect(route('programs.index'));
    expect($html)->toMatch('/href="'.preg_quote(route('competitions.index'), '/').'" wire:navigate data-programs-bar-item="competitions"/');
});

it('moves initiatives out of the e-services (page, dropdown and home strip) into the About dropdown, once and last', function () {
    expect(array_column(MenuSections::availableServices(), 'route'))->not->toContain('initiatives.index')
        ->and(array_column(MenuSections::for('/services'), 'url'))->not->toContain('/initiatives')
        ->and(BuildHomePage::SHORTCUTS)->not->toHaveKey('initiatives')
        // مكانها في «عن الجمعية» من بذرة مراسيها وحدها (AboutMenuAnchorsSeeder — #177)، لا قسم مولَّد (مراجعة المشرف على #181، C1)
        ->and(MenuSections::for('/about'))->toBe([])
        ->and(MenuLink::CURRENT_IN['about'])->toBe(['initiatives'])
        ->and(MenuLink::CURRENT_IN['services'])->not->toContain('initiatives');

    // بطاقات الخدمات (والتذييل يبقى فيه رابط المبادرات)
    expect((string) str((string) $this->get('/services')->assertOk()->getContent())->after('data-service-grid')->before('</ul>'))
        ->not->toContain(route('initiatives.index'));
    expect((string) str((string) $this->get('/')->getContent())->after('id="home-services-track"')->before('</section>'))
        ->not->toContain(route('initiatives.index'));

    // القائمة المعروضة بالترتيب النهائي، و«المبادرات» مرة واحدة آخر مراسي «عن الجمعية»، على الحاسوب وفي قائمة الجوال
    (new MenuSeeder)->run();
    (new AboutMenuAnchorsSeeder)->run();
    Page::query()->where('slug', Page::ABOUT)->exists() || Page::factory()->create(['slug' => Page::ABOUT]);
    ContentCache::flush();
    $html = (string) $this->get('/')->assertOk()->getContent();

    foreach ([(string) str($html)->after('data-main-nav')->before('id="mobile-menu"'), (string) str($html)->after('id="mobile-menu"')->before('</nav>')] as $menu) {
        $positions = array_map(fn (array $item): int|false => strpos($menu, ltrim($item[1], '/').'"'), AboutMenuAnchorsSeeder::ITEMS);
        $sorted = $positions;
        sort($sorted);

        expect($positions)->not->toContain(false)->toBe($sorted)
            ->and(substr_count($menu, 'المبادرات'))->toBe(1)
            ->and($menu)->not->toContain('href="'.route('initiatives.index').'"');
    }
});

it('shows the inquiries intro once on the contact page, since it is the second hero paragraph', function () {
    $second = 'يسعد جمعية إعجاز للدراسات والبحوث العلمية تلقي استفساراتكم واقتراحاتكم حول برامجها ومبادراتها وخدماتها، وتصل إلى الفريق المختص عبر نموذج «الاستفسارات والاقتراحات» في صفحة التواصل، ليتابعها ويرد عليها.';
    Page::factory()->create(['slug' => 'inquiries', 'is_published' => true, 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>'.$second.'</p>']]]]);
    ContentCache::flush();

    $html = (string) $this->get('/contact')->assertOk()->getContent();

    expect(substr_count($html, $second))->toBe(1)
        ->and((string) str($html)->after('data-page-hero')->before('</section>'))->toContain($second);

    // ونص آخر في المقدمة يبقى ظاهراً تحت الرأس
    Page::query()->where('slug', 'inquiries')->update(['blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص آخر للاستفسارات</p>']]]]);
    ContentCache::flush();
    $this->get('/contact')->assertOk()->assertSee('نص آخر للاستفسارات');
});

it('draws the inner hero calligraphy a little larger', function () {
    $css = (string) file_get_contents(resource_path('css/concept.css'));

    expect($css)->toMatch('/\.page-hero-calligraphy \{\n    width: min\(88vw, 25rem\);/')
        ->toMatch('/@media \(min-width: 64rem\) \{\n    \.page-hero-calligraphy \{\n        width: 35rem;/');
});

it('keeps the login card short on desktop and lays registration in two columns', function () {
    $login = (string) $this->get('/login')->assertOk()->getContent();
    $register = (string) $this->get('/register')->assertOk()->getContent();

    // صورة أسفل البطاقة للجوال وحده، ومسافات أقل على الحاسوب (وأقل منها لتدخل بطاقة التسجيل في 1366×768 — register-fit.spec.mjs)
    expect($login)->toContain('lg:pt-4 lg:pb-4')->toContain('object-cover lg:hidden')
        ->and($register)->toContain('lg:max-w-[58rem]')->toContain('data-register-columns')->toContain('lg:grid-cols-2')
        // الحقول بأسمائها، بلا اسم مستخدم للحساب الجديد (الدخول بالجوال، #180)
        ->and($register)->not->toContain('name="username"')
        ->and($register)->toContain('name="name"')->toContain('name="email"')->toContain('name="phone"')
        ->toContain('name="password"')->toContain('name="password_confirmation"')->toContain('name="privacy"');
});
