<?php

use App\Models\AssemblyMembership;
use Illuminate\Support\Facades\Blade;

/*
| التنظيف المشترك بلا تغيير سلوك (docs/plans/shared-cleanup.md): قاعدة رقم الصفحة الواحدة في القوائم،
| ومكوّنا «العنوان وعرض الكل» وبلاطة القسم.
*/

it('sends a wrong page number back to the first page of every list', function (string $page) {
    $lists = [
        '/media/news' => route('media.news.index'),
        '/media/albums' => route('media.albums.index'),
        '/governance' => route('governance.index'),
        '/library/videos' => route('library.section', 'videos'),
    ];

    foreach ($lists as $path => $first) {
        $this->get($path.'?page='.$page)->assertRedirect($first);
    }

    $this->actingAs(AssemblyMembership::factory()->create()->user)
        ->get(route('assembly.files.index', ['page' => $page]))
        ->assertRedirect(route('assembly.files.index'));
})->with(['0', '1001', 'abc']);

it('renders a section header with its view-all link, or the title alone without one', function () {
    $html = Blade::render('<x-section-header id="s" title="العنوان" href="/all" />');

    expect($html)->toMatch('/<div id="s">\s*<span[^>]*bg-accent/')
        ->toContain('العنوان')
        ->toContain('href="/all"')
        ->toContain(__('site.view_all'));

    expect(Blade::render('<x-section-header id="s" title="العنوان" href="/videos" link-label="كل المقاطع" />'))
        ->toContain('كل المقاطع')
        ->not->toContain(__('site.view_all'));

    expect(Blade::render('<x-section-header id="s" title="العنوان" />'))
        ->toContain('العنوان')
        ->not->toContain('href=')
        ->not->toContain(__('site.view_all'));
});

it('renders a section tile with its icon, label and link', function () {
    $html = Blade::render('<x-section-tile href="/news" icon="newspaper" label="الأخبار" />');

    expect($html)->toContain('href="/news"')
        ->toContain('الأخبار')
        ->toContain('<svg')
        ->toContain('bg-primary-soft');

    expect(Blade::render('<x-section-tile href="/x" icon="user" label="س" tone="primary" size="size-10" class="justify-center" />'))
        ->toContain('text-primary')
        ->toContain('size-10')
        ->toContain('justify-center');
});
