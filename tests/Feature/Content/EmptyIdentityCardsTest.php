<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Models\Initiative;
use App\Models\Page;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\HomeShowcase;

/*
| البطاقات تحت البطل بلا نص ظاهر (docs/decisions/2026-11-10-fix-empty-identity-cards.md): «أهدافنا» و«مبادئنا» تُؤخذان من مقطعيهما
| في «عن الجمعية» المنشورة كالرؤية والرسالة. والبطاقة بلا نص ظاهر ولا عنصر باقية بسطر «يُضاف قريباً» (قرار صفوان، 11 نوفمبر 2026 —
| docs/decisions/2026-11-11-fix-show-empty-sections.md، ويحل محل إخفائها).
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

it('keeps all five cards, the ones without visible text with a coming-soon line instead of the hidden marker', function () {
    config(['app.hide_missing_markers' => true]);
    Initiative::factory()->create(['name' => 'مبادرة ظاهرة', 'is_published' => true]);

    $page = $this->get('/')->assertOk()->getContent();
    ['cards' => $cards, 'tones' => $tones, 'html' => $html] = identityCards($page);
    $vision = (string) str($html)->after('data-identity-card="vision"')->before('data-identity-card="goals"');
    $goals = (string) str($html)->after('data-identity-card="goals"')->before('data-identity-card="values"');

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles', 'initiatives'])
        ->and($tones)->toBe(['dark', 'light', 'dark', 'light', 'dark'])
        ->and($html)->toContain('--home-cards-count: 5')
        ->and($html)->not->toContain('【')
        ->and($vision)->toContain(__('home.cards.soon'))
        ->and($goals)->toContain(__('home.cards.soon'))
        ->and($html)->toContain(__('home.cards.principles_soon'))
        ->and($html)->toContain('مبادرة ظاهرة');
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

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles', 'initiatives'])
        ->and($tones)->toBe(['dark', 'light', 'dark', 'light', 'dark'])
        ->and($html)->toContain('رؤية من الصفحة')
        ->and($html)->toContain('الهدف الأول'.__('home.cards.separator').'الهدف الثاني')
        ->and($html)->toContain('مبدأ من الصفحة')
        ->and($html)->toContain('--home-cards-count: 5');
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

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles', 'initiatives'])
        ->and($html)->toContain(__('home.cards.vision_missing'))
        ->and($html)->toContain(HomeContent::defaultText('identity', 'goals'))
        ->and($html)->toContain(HomeContent::defaultText('identity', 'principles'));

    // ومع الإعداد: لمن يدخل لوحة الإدارة وحده
    config(['app.hide_missing_markers' => true]);

    ['cards' => $cards] = identityCards($this->actingAs(userWithRole(RoleName::ContentEditor))->get('/')->assertOk()->getContent());

    expect($cards)->toBe(['vision', 'goals', 'values', 'principles', 'initiatives']);
});

it('keeps the empty values, principles and initiatives cards in the moving row with a coming-soon line', function () {
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
        ->and($html)->toContain('home-cards-track')
        ->and($html)->toContain('data-identity-card="principles"')
        ->and($html)->toContain(__('home.cards.principles_soon'))
        ->and(str($html)->after('data-identity-card="values"')->before('data-identity-card="principles"')->toString())->toContain(__('home.cards.soon'))
        ->and(str($html)->after('data-identity-card="initiatives"')->before('</div>')->toString())->not->toContain('data-initiative-slide')
        ->and($html)->toContain('--home-cards-count: 5');
});

it('fills the phone width with the four cards of the moving row, so it no longer needs repeating', function () {
    config(['app.hide_missing_markers' => true]);

    $html = view('home.partials.identity', [
        'identity' => [['kind' => 'value', 'title' => 'قيمة', 'text' => '', 'icon' => 'star']],
        'texts' => ['goals' => 'هدف معتمد', 'principles' => ''],
        'initiatives' => collect(),
        'content' => HomeContent::resolved(),
        'band' => 1,
    ])->render();

    // البطاقات الأربع في الشريط دائماً منذ قرار 11 نوفمبر: نسختان، الأولى ظاهرة
    expect(substr_count($html, 'data-marquee-copy'))->toBe(1)
        ->and(substr_count($html, 'data-identity-card="goals"'))->toBe(2);
});

/*
| و«التوجه الاستراتيجي» في «عن الجمعية» بلا نص ظاهر: باقٍ بسطر «يُضاف قريباً» (قرار صفوان، 11 نوفمبر 2026)، والعلامة لمن يراها.
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

it('keeps the strategic direction section on the about page with a coming-soon line when it has no visible text', function (array $blocks) {
    config(['app.hide_missing_markers' => true]);
    publishedAbout($blocks);

    $this->get(route('about'))->assertOk()
        ->assertSee('تعريف الجمعية')
        ->assertSee('id="about-strategy"', false)
        ->assertSee('data-about-empty="strategy"', false)
        ->assertDontSee('نص التوجه');
})->with([
    'no section' => [[]],
    // والعلامة 【ناقص】 لا تصل إلى صفحة منشورة أصلاً (Publishable يرفض نشرها)، فحالتها حالة «بلا مقطع» أعلاه
    'an empty text' => [[['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p> </p>']]]],
    'a non-breaking space only' => [[['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p>&nbsp;</p>']]]],
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
