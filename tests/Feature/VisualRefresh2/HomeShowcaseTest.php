<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Models\Album;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Page;
use App\Models\Program;
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

    $this->get('/')->assertOk()->assertSee('data-prize-total', false)->assertSee('7,700');
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

it('builds the hero slides from the published albums, and a single neutral slide without them', function () {
    expect(HomeShowcase::slides([]))->toHaveCount(1);

    Album::factory()->create(['is_published' => true, 'photos' => ['albums/a.jpg', 'albums/b.jpg', 'albums/c.jpg']]);
    Album::factory()->create(['is_published' => false, 'photos' => ['albums/draft.jpg']]);

    $slides = HomeShowcase::slides(['hero_image' => 'settings/hero.jpg']);

    expect($slides)->toHaveCount(3)
        ->and($slides[0]['src'])->toEndWith('settings/hero.jpg')
        ->and(collect($slides)->pluck('src')->implode(' '))->not->toContain('draft');

    $this->get('/')->assertOk()->assertSee('data-hero-slider', false)->assertSee('data-hero-progress', false);
});

it('lists the latest published updates across modules, newest first', function () {
    Program::factory()->create(['title' => 'برنامج قديم', 'is_published' => true, 'created_at' => now()->subDays(5)]);
    Document::factory()->create(['title' => 'وثيقة حديثة', 'is_published' => true, 'created_at' => now()->subDay()]);
    Program::factory()->create(['title' => 'برنامج مسودة', 'is_published' => false, 'created_at' => now()]);

    $titles = array_column(HomeShowcase::updates(), 'title');

    expect($titles)->toBe(['وثيقة حديثة', 'برنامج قديم']);
    $this->get('/')->assertSee('id="home-updates"', false)->assertSee('وثيقة حديثة');
});
