<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Models\Initiative;
use App\Models\Page;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\HomeShowcase;

/*
| البطاقات تحت البطل بلا نص ظاهر (docs/decisions/2026-11-10-fix-empty-identity-cards.md): على الخادم «عن الجمعية» مسودة ولم تصل
| الأهداف ولا المبادئ، فظهرت «رؤيتنا ورسالتنا» و«أهدافنا» و«مبادئنا» بعناوينها وحدها. البطاقة بلا نص ظاهر لا تُعرض، و«أهدافنا»
| و«مبادئنا» تُؤخذان من مقطعيهما في «عن الجمعية» المنشورة كالرؤية والرسالة.
*/

/**
 * البطاقات الظاهرة في النسخة الأولى من الصف (بلا نسخ الشريط المتحرك) بدرجاتها.
 *
 * @return array{cards: list<string>, tones: list<string>, html: string}
 */
function identityCards(string $page): array
{
    $html = (string) str($page)->after('id="home-identity"')->before('</section>');
    $first = (string) str($html)->before('data-marquee-copy');
    preg_match_all('/data-tone="(\w+)" data-identity-card="(\w+)"/', $first, $matches);

    return ['cards' => $matches[2], 'tones' => $matches[1], 'html' => $html];
}

it('drops the vision, goals and principles cards instead of leaving them blank when the markers are hidden', function () {
    config(['app.hide_missing_markers' => true]);
    Initiative::factory()->create(['name' => 'مبادرة ظاهرة', 'is_published' => true]);

    $page = $this->get('/')->assertOk()->getContent();
    ['cards' => $cards, 'tones' => $tones, 'html' => $html] = identityCards($page);

    expect($cards)->toBe(['values', 'initiatives'])
        // الدرجات تتعاقب بترتيب الظاهرة، والأعمدة على الحاسوب بعددها
        ->and($tones)->toBe(['dark', 'light'])
        ->and($html)->toContain('--home-cards-count: 2')
        ->and($html)->not->toContain('【')
        // ولا في نسخ الشريط المتحرك
        ->and($html)->not->toContain('data-identity-card="vision"')
        ->and($html)->not->toContain('data-identity-card="goals"')
        ->and($html)->not->toContain('data-identity-card="principles"');
});

it('takes the goals and principles from the published about page, its goals cards joined', function () {
    config(['app.hide_missing_markers' => true]);
    Page::factory()->create([
        'slug' => Page::ABOUT,
        'is_published' => true,
        'blocks' => [
            ['type' => 'text', 'data' => ['heading' => 'الرؤية', 'body' => '<p>رؤية من الصفحة</p>']],
            ['type' => 'cards', 'data' => ['heading' => 'أهداف الجمعية', 'items' => [['title' => 'الهدف الأول', 'text' => null], ['title' => 'الهدف الثاني.', 'text' => null]]]],
            ['type' => 'text', 'data' => ['heading' => 'المبادئ', 'body' => '<p>مبدأ من الصفحة</p>']],
        ],
    ]);

    expect(HomeShowcase::identityTexts(HomeContent::resolved()))->toBe([
        'goals' => 'الهدف الأول'.__('home.cards.separator').'الهدف الثاني',
        'principles' => 'مبدأ من الصفحة',
    ]);

    ['cards' => $cards, 'tones' => $tones, 'html' => $html] = identityCards($this->get('/')->assertOk()->getContent());

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles'])
        ->and($tones)->toBe(['dark', 'light', 'dark', 'light'])
        ->and($html)->toContain('رؤية من الصفحة')
        ->and($html)->toContain('الهدف الأول'.__('home.cards.separator').'الهدف الثاني')
        ->and($html)->toContain('مبدأ من الصفحة')
        ->and($html)->toContain('--home-cards-count: 4');
});

it('prefers the panel goals over the about page, and ignores a draft about page', function () {
    Page::factory()->create([
        'slug' => Page::ABOUT,
        'is_published' => false,
        'blocks' => [['type' => 'text', 'data' => ['heading' => 'الأهداف', 'body' => '<p>هدف في المسودة</p>']]],
    ]);

    // المسودة لا تُقرأ، فيبقى الأصل 【ناقص】
    expect(HomeShowcase::identityTexts(HomeContent::resolved()))->toBe([
        'goals' => HomeContent::defaultText('identity', 'goals'),
        'principles' => HomeContent::defaultText('identity', 'principles'),
    ]);

    app(UpdateHomeContent::class)->handle(userWithRole(RoleName::ContentEditor), [
        'identity' => ['goals' => 'هدف من اللوحة'],
        'values' => ['items' => array_fill(0, 4, [])],
    ]);

    expect(HomeShowcase::identityTexts(HomeContent::resolved())['goals'])->toBe('هدف من اللوحة');
});

it('keeps the marker texts in their cards for whoever sees the markers', function () {
    // التطوير بلا الإعداد: العلامات للجميع
    config(['app.hide_missing_markers' => false]);

    ['cards' => $cards, 'html' => $html] = identityCards($this->get('/')->assertOk()->getContent());

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles'])
        ->and($html)->toContain(__('home.cards.vision_missing'))
        ->and($html)->toContain(HomeContent::defaultText('identity', 'goals'))
        ->and($html)->toContain(HomeContent::defaultText('identity', 'principles'));

    // ومع الإعداد: لمن يدخل لوحة الإدارة وحده
    config(['app.hide_missing_markers' => true]);

    ['cards' => $cards] = identityCards($this->actingAs(userWithRole(RoleName::ContentEditor))->get('/')->assertOk()->getContent());

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles']);
});

it('renders no moving row when only the vision card is left, and no section when nothing is', function () {
    config(['app.hide_missing_markers' => true]);
    $data = [
        'texts' => ['goals' => HomeContent::defaultText('identity', 'goals'), 'principles' => ''],
        'initiatives' => collect(),
        'content' => HomeContent::resolved(),
        'band' => 1,
    ];

    $html = view('home.partials.identity', [...$data, 'identity' => [['kind' => 'vision', 'title' => 'الرؤية', 'text' => 'رؤية معتمدة', 'icon' => 'eye']]])->render();

    expect($html)->toContain('data-identity-card="vision"')
        ->and($html)->toContain('رؤية معتمدة')
        ->and($html)->not->toContain('home-cards-track')
        ->and($html)->not->toContain('data-marquee')
        ->and($html)->toContain('--home-cards-count: 1');

    expect(trim(view('home.partials.identity', [...$data, 'identity' => []])->render()))->not->toContain('home-identity');
});

it('repeats a short moving row so it fills the phone width without a gap', function () {
    config(['app.hide_missing_markers' => true]);

    $html = view('home.partials.identity', [
        'identity' => [['kind' => 'value', 'title' => 'قيمة', 'text' => '', 'icon' => 'star']],
        'texts' => ['goals' => 'هدف معتمد', 'principles' => ''],
        'initiatives' => collect(),
        'content' => HomeContent::resolved(),
        'band' => 1,
    ])->render();

    // بطاقتان في الشريط: يتكرر المسار ضعفين (أربع نسخ، الأولى ظاهرة)
    expect(substr_count($html, 'data-marquee-copy'))->toBe(3)
        ->and(substr_count($html, 'data-identity-card="goals"'))->toBe(4);
});

/*
| والمشكلة نفسها في «عن الجمعية»: مقطع «التوجه الاستراتيجي» بلا نص ظاهر كان شريطاً داكناً بعنوانه وحده.
*/

/**
 * @param  list<array<string, mixed>>  $blocks
 */
function publishedAbout(array $blocks): void
{
    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true, 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>تعريف الجمعية</p>']],
        ...$blocks,
    ]]);
}

it('drops the strategic direction section from the about page when it has no visible text', function (array $blocks) {
    config(['app.hide_missing_markers' => true]);
    publishedAbout($blocks);

    $this->get(route('about'))->assertOk()
        ->assertSee('تعريف الجمعية')
        ->assertDontSee('data-about-strategy', false)
        ->assertDontSee('id="about-strategy"', false)
        ->assertDontSee('نص التوجه');
})->with([
    'no section' => [[]],
    'a marker as its text' => [[['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p>【ناقص: نص التوجه】</p>']]]],
    'an empty text' => [[['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p> </p>']]]],
]);

it('shows the strategic direction section with its approved text, and its marker to whoever sees the markers', function () {
    config(['app.hide_missing_markers' => true]);
    publishedAbout([['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p>توجه معتمد</p>']]]);

    expect((string) str($this->get(route('about'))->assertOk()->getContent())->after('data-about-strategy')->before('</section>'))
        ->toContain('توجه معتمد');

    // بلا مقطع: يراه المحرر بعلامته
    Page::query()->where('slug', Page::ABOUT)->first()?->forceFill(['blocks' => [['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>تعريف</p>']]]])->save();
    ContentCache::flush();

    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(route('about'))->assertOk()
        ->assertSee('id="about-strategy"', false)
        ->assertSee(__('content.about.strategy_missing'));
});
