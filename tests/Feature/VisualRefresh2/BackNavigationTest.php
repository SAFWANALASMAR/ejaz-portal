<?php

use App\Models\Document;
use App\Models\LibraryItem;
use App\Models\Program;
use App\Support\BackNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/*
| شريط العودة x-back-nav (طلب المدير التنفيذي — قائمة العمل، البند ٣): الأم المنطقية من جدول واحد باسم المسار.
*/

function routeFor(string $uri): Illuminate\Routing\Route
{
    return Route::getRoutes()->match(Request::create($uri));
}

it('maps every parent in the table to an existing route', function () {
    foreach (BackNavigation::PARENTS as $name => [$parent]) {
        expect(Route::has($name))->toBeTrue("المسار {$name}")
            ->and(Route::has($parent))->toBeTrue("الأم {$parent}");
    }
});

it('takes the parent parameters from the page itself', function () {
    expect(BackNavigation::parentUrl(routeFor('/library/books/some-book')))->toBe(route('library.section', 'books'))
        ->and(BackNavigation::parentUrl(routeFor('/governance/some-document')))->toBe(route('governance.index'))
        ->and(BackNavigation::parentUrl(routeFor('/programs/some-program')))->toBe(route('programs.index'))
        ->and(BackNavigation::parentUrl(routeFor('/media/news/some-news')))->toBe(route('media.news.index'));
});

it('falls back to the browser history for a page outside the table, and shows nothing on the home page', function () {
    expect(BackNavigation::parentUrl(routeFor('/surveys/abc')))->toBeNull()
        ->and(BackNavigation::shows(routeFor('/')))->toBeFalse()
        ->and(BackNavigation::shows(routeFor('/governance')))->toBeTrue()
        ->and(BackNavigation::shows(null))->toBeFalse();
});

it('shows the back links on a governance document and a program, and none on the home page', function () {
    $document = Document::factory()->create(['is_published' => true]);
    $program = Program::factory()->create(['is_published' => true]);

    $this->get(route('governance.show', $document->slug))->assertOk()
        ->assertSee('data-back-nav', false)
        ->assertSee('href="'.route('governance.index').'"', false)
        ->assertSee(__('ui.back_nav.parent'))
        ->assertSee(__('ui.back_nav.home'));

    $this->get(route('programs.show', $program->slug))->assertOk()
        ->assertSee('href="'.route('programs.index').'"', false);

    $this->get('/')->assertOk()->assertDontSee('data-back-nav', false);
});

it('goes back from a library item to its own section', function () {
    $item = LibraryItem::factory()->create(['is_published' => true]);

    $this->get(route('library.show', [$item->section->value, $item->slug]))->assertOk()
        ->assertSee('href="'.route('library.section', $item->section->value).'"', false);
});

it('marks a page without a parent for the history fallback, and keeps the home page as its link', function () {
    $route = routeFor('/surveys/some-token');
    request()->setRouteResolver(fn () => $route);

    $html = Blade::render('<x-back-nav />');

    expect($html)->toContain('data-history-back')->toContain('href="'.route('home').'"');
});
