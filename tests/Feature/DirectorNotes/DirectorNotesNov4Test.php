<?php

use App\Actions\Content\BuildHomePage;
use App\Http\Controllers\ProgramController;
use App\Models\Page;
use App\Models\Program;
use App\Models\ProgramType;
use App\Support\ContentCache;
use App\Support\MenuLink;
use App\Support\MenuSections;
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

    $html = (string) $this->get('/programs')->assertOk()->getContent();
    $bar = (string) str($html)->after('data-programs-bar')->before('</nav>');

    // الشريط: مرساة لكل نوع بترتيب اللوحة، بلا تحميل صفحة (لا wire:navigate)، ثم المسابقات إلى صفحتها
    preg_match_all('/data-programs-bar-item="([^"]+)"/', $bar, $items);
    expect($items[1])->toBe([...$types->pluck('slug')->all(), 'competitions'])
        ->and($bar)->toContain('href="#programs-'.$types[0]->slug.'"')
        ->not->toMatch('/href="#[^"]*"[^>]*wire:navigate/')
        ->toContain('href="'.route('competitions.index').'"')
        // والشريطان السابقان (التبويبات وبطاقات المحور) لم يبقيا
        ->and($html)->not->toContain('class="tab-pill"')->not->toContain(route('programs.index', ['type' => $types[0]->slug]));

    // مقطع لكل نوع بمعرّف مرساته، فيه برامجه وحدها، والفارغ برسالته
    foreach ($types as $type) {
        expect($html)->toContain('id="programs-'.$type->slug.'"');
    }
    $first = (string) str($html)->after('data-programs-section="'.$types[0]->slug.'"')->before('</section>');
    expect($first)->toContain('دورة أولى')->not->toContain('مجلس أول')
        ->and((string) str($html)->after('data-programs-section="'.$types[2]->slug.'"')->before('</section>'))->toContain(__('programs.hub.empty'));
});

it('shows up to six programmes per section with «view all» to the type list, which keeps its pagination and bar', function () {
    $type = ProgramType::factory()->create(['slug' => 'many', 'name' => 'كثيرة']);
    Program::factory()->count(ProgramController::PER_SECTION + 1)->for($type, 'type')->create();

    $html = (string) $this->get('/programs')->assertOk()->getContent();
    $section = (string) str($html)->after('data-programs-section="many"')->before('</section>');

    expect(substr_count($section, '<article class="tone-card'))->toBe(ProgramController::PER_SECTION)
        ->and($section)->toContain(route('programs.index', ['type' => 'many']));

    // قائمة النوع: برامجه كلها، والشريط يعود إلى مقاطع الصفحة الأم، والنوع النشط معلَّم
    $list = (string) $this->get('/programs?type=many')->assertOk()->getContent();
    expect($list)->toContain('href="'.route('programs.index').'#programs-many"')->toContain('aria-current="page"');

    $this->get('/programs?page=2')->assertNotFound();
    $this->get('/programs?type[]=many')->assertRedirect(route('programs.index'));
});

it('moves initiatives out of the e-services (page, dropdown and home strip) into the About dropdown', function () {
    expect(array_column(MenuSections::availableServices(), 'route'))->not->toContain('initiatives.index')
        ->and(array_column(MenuSections::for('/services'), 'url'))->not->toContain('/initiatives')
        ->and(BuildHomePage::SHORTCUTS)->not->toHaveKey('initiatives')
        ->and(MenuSections::for('/about'))->toBe([['label' => __('content.initiatives.title'), 'url' => '/initiatives']])
        ->and(MenuLink::CURRENT_IN['about'])->toBe(['initiatives'])
        ->and(MenuLink::CURRENT_IN['services'])->not->toContain('initiatives');

    // بطاقات الخدمات (والتذييل يبقى فيه رابط المبادرات)
    expect((string) str((string) $this->get('/services')->assertOk()->getContent())->after('data-service-grid')->before('</ul>'))
        ->not->toContain(route('initiatives.index'));
    expect((string) str((string) $this->get('/')->getContent())->after('id="home-services-track"')->before('</section>'))
        ->not->toContain(route('initiatives.index'));

    // في القائمة المنسدلة لعنصر «عن الجمعية» بعد الصفحة الأم، وعناصر المحرر بعدها كما هي
    $children = MenuSections::children(['label' => 'عن الجمعية', 'url' => '/about', 'children' => [
        ['id' => 3, 'label' => 'عن الجمعية', 'url' => '/about', 'opens_in_new_tab' => false],
        ['id' => 4, 'label' => 'شهادة ترخيص الجمعية', 'url' => '/governance/license-certificate', 'opens_in_new_tab' => false],
    ]]);
    expect(array_column($children, 'url'))->toBe(['/about', '/initiatives', '/governance/license-certificate']);
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

    // صورة أسفل البطاقة للجوال وحده، ومسافات أقل على الحاسوب
    expect($login)->toContain('lg:pt-6 lg:pb-6')->toContain('object-cover lg:hidden')
        ->and($register)->toContain('lg:max-w-[58rem]')->toContain('data-register-columns')->toContain('lg:grid-cols-2')
        // الحقول كلها باقية، بأسمائها
        ->and($register)->toContain('name="name"')->toContain('name="username"')->toContain('name="email"')->toContain('name="phone"')
        ->toContain('name="password"')->toContain('name="password_confirmation"')->toContain('name="privacy"');
});
