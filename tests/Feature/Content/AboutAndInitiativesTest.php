<?php

use App\Models\BoardMember;
use App\Models\Initiative;
use App\Models\Page;
use App\Models\Setting;
use App\Support\ContentCache;

/**
 * @param  array<string, mixed>  $attributes
 */
function aboutPage(array $attributes = []): Page
{
    return Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية', ...$attributes]);
}

it('shows the published about page with its blocks and the visible board members in order', function () {
    aboutPage([
        'summary' => 'ملخص الصفحة',
        'blocks' => [
            ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>نص <strong>تعريفي</strong></p>']],
            ['type' => 'cards', 'data' => ['heading' => 'القيم', 'items' => [
                ['icon' => 'heart', 'title' => 'قيمة أولى', 'text' => 'وصفها'],
                ['icon' => '../../evil', 'title' => 'قيمة ثانية', 'text' => ''],
            ]]],
            ['type' => 'image', 'data' => ['image' => 'pages/photo.jpg', 'caption' => 'وصف الصورة']],
            ['type' => 'unknown', 'data' => ['x' => 'y']],
        ],
    ]);
    BoardMember::factory()->create(['name' => 'العضو الثاني', 'sort' => 2]);
    BoardMember::factory()->create(['name' => 'العضو الأول', 'sort' => 1]);
    BoardMember::factory()->create(['name' => 'عضو مخفي', 'is_visible' => false]);

    $this->get('/about')->assertOk()
        ->assertSeeInOrder(['<h1', 'عن الجمعية'], false)
        ->assertSee('ملخص الصفحة')
        ->assertSee('<strong>تعريفي</strong>', false)
        ->assertSee('قيمة أولى')->assertSee('قيمة ثانية')
        ->assertSee('/storage/pages/photo.jpg', false)->assertSee('وصف الصورة')
        ->assertSeeInOrder(['العضو الأول', 'العضو الثاني'])
        ->assertDontSee('عضو مخفي');
});

it('answers 404 for an unpublished or missing about page', function () {
    $this->get('/about')->assertNotFound();

    aboutPage(['is_published' => false]);

    $this->get('/about')->assertNotFound();
});

it('cleans the rich text of anything but the basic text elements, even if it reaches the database', function () {
    aboutPage(['blocks' => [['type' => 'text', 'data' => ['body' => <<<'HTML'
        <p style="position:fixed" onclick="steal()">نص <a href="javascript:alert(1)">ضار</a> <a href="https://ejaz.sa">سليم</a></p>
        <script>alert(2)</script><img src="x" onerror="alert(3)"><iframe src="https://evil.example"></iframe>
        HTML]]]]);

    $html = $this->get('/about')->assertOk()->getContent();

    expect($html)
        ->not->toContain('javascript:alert(1)')
        ->not->toContain('alert(2)')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('position:fixed')
        ->not->toContain('evil.example')
        ->toContain('<a href="https://ejaz.sa" rel="noopener noreferrer">سليم</a>');
});

it('lists the published initiatives in their order and opens each one', function () {
    $second = Initiative::factory()->create(['slug' => 'tatweer', 'name' => 'تطوير', 'sort' => 2]);
    Initiative::factory()->create(['slug' => 'qiyam', 'name' => 'قيم', 'sort' => 1, 'summary' => 'ملخص قيم']);
    Initiative::factory()->unpublished()->create(['slug' => 'kunooz', 'name' => 'كنوز']);

    $this->get('/initiatives')->assertOk()
        ->assertSeeInOrder(['قيم', 'تطوير'])
        ->assertSee('ملخص قيم')
        ->assertDontSee('كنوز')
        ->assertSee(route('initiatives.show', 'qiyam'), false);

    $second->update([
        'goals' => ['الهدف الأول', 'الهدف الثاني'],
        'gallery' => ['initiatives/one.jpg', 'initiatives/two.jpg'],
        'image' => 'initiatives/cover.jpg',
    ]);

    $this->get('/initiatives/tatweer')->assertOk()
        ->assertSeeInOrder(['<h1', 'تطوير'], false)
        ->assertSeeInOrder(['الهدف الأول', 'الهدف الثاني'])
        ->assertSee('/storage/initiatives/one.jpg', false)
        ->assertSee('/storage/initiatives/cover.jpg', false);
});

it('answers 404 for an unpublished, unknown or malformed initiative link', function () {
    Initiative::factory()->unpublished()->create(['slug' => 'kunooz']);

    $this->get('/initiatives/kunooz')->assertNotFound();
    $this->get('/initiatives/unknown')->assertNotFound();
    $this->get('/initiatives/Bad_Slug')->assertNotFound();
});

it('shows a change to the content at once', function () {
    $initiative = Initiative::factory()->create(['slug' => 'qiyam', 'name' => 'الاسم القديم']);
    $this->get('/initiatives')->assertSee('الاسم القديم');
    $this->get('/initiatives/qiyam')->assertSee('الاسم القديم');

    $initiative->update(['name' => 'الاسم الجديد']);

    $this->get('/initiatives')->assertSee('الاسم الجديد')->assertDontSee('الاسم القديم');
    $this->get('/initiatives/qiyam')->assertSee('الاسم الجديد');

    $initiative->update(['is_published' => false]);

    $this->get('/initiatives/qiyam')->assertNotFound();
});

it('shows the published initiatives on the home page unless the section is switched off', function () {
    $this->get('/')->assertDontSee(route('initiatives.index'), false);

    Initiative::factory()->create(['slug' => 'qiyam', 'name' => 'قيم']);

    $this->get('/')->assertSee('قيم')->assertSee(route('initiatives.show', 'qiyam'), false)->assertSee(route('initiatives.index'), false);

    Setting::put('home', ['show_initiatives' => false]);

    $this->get('/')->assertDontSee(route('initiatives.show', 'qiyam'), false);
});

it('marks the missing board members in development, and shows nothing of them in production', function () {
    aboutPage();

    $this->get('/about')->assertSee(__('content.about.board_missing'));

    $this->app['env'] = 'production';
    ContentCache::flush();

    $this->withoutVite()->get('/about')->assertOk()->assertDontSee(__('content.about.board_missing'));
});
