<?php

use App\Models\Initiative;
use App\Models\Redirect;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegacyRedirectsSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

/**
 * المسار كما يرسله المتصفح: كل جزء بترميزه.
 */
function browserPath(string $path): string
{
    return implode('/', array_map(rawurlencode(...), explode('/', $path)));
}

it('redirects every link of the current site permanently to its new place, as a browser sends it', function () {
    $this->seed([ContentSeeder::class, LegacyRedirectsSeeder::class]);

    foreach (LegacyRedirectsSeeder::MAP as $old => $new) {
        $bare = rtrim($old, '/');

        foreach ([browserPath($bare), browserPath($bare).'/', browserPath($bare).'?page=2'] as $path) {
            $this->get($path)->assertStatus(301)->assertRedirect($new);
        }
    }
});

it('sends each old link to a page that works, or to a service that awaits its item', function () {
    $this->seed([ContentSeeder::class, LegacyRedirectsSeeder::class]);
    Initiative::query()->update(['is_published' => true]);

    // الخدمات الإلكترونية في المرحلة 3 (roadmap.md)، وتعمل تحويلاتها حين تُدمج
    $awaiting = ['/services/assembly-membership', '/services/volunteering', '/services/jobs'];

    foreach (array_unique(LegacyRedirectsSeeder::MAP) as $new) {
        $route = app(Router::class)->getRoutes()->match(Request::create($new));

        if ($route->isFallback) {
            expect($new)->toBeIn($awaiting);
        } else {
            $this->get($new)->assertOk();
        }
    }
});

it('seeds only what is missing, keeping what the editor changed', function () {
    $this->seed(LegacyRedirectsSeeder::class);
    Redirect::query()->where('source', 'التطوع')->sole()->update(['target' => '/contact']);

    $this->seed(LegacyRedirectsSeeder::class);

    expect(Redirect::count())->toBe(count(LegacyRedirectsSeeder::MAP))
        ->and(Redirect::query()->where('source', 'التطوع')->value('target'))->toBe('/contact');
});

it('answers an unknown path with the Arabic not-found page and the security headers', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('errors.404.title'))
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('leaves a path the portal serves alone, even if a redirect for it reached the database', function () {
    Redirect::factory()->create(['source' => 'contact', 'target' => '/library']);

    $this->get('/contact')->assertOk();
});

it('follows only active redirects, with the status of their type', function () {
    Redirect::factory()->create(['source' => 'old-news', 'target' => '/media/news']);
    Redirect::factory()->temporary()->create(['source' => 'old-event', 'target' => '/media']);
    Redirect::factory()->inactive()->create(['source' => 'old-page', 'target' => '/about']);

    $this->get('/old-news')->assertStatus(301)->assertRedirect('/media/news');
    $this->get('/old-event')->assertStatus(302)->assertRedirect('/media');
    $this->get('/old-page')->assertNotFound();
});

it('shows a change to a redirect at once', function () {
    $redirect = Redirect::factory()->create(['source' => 'old-news', 'target' => '/media/news']);
    $this->get('/old-news')->assertRedirect('/media/news');

    $redirect->update(['target' => '/media']);
    $this->get('/old-news')->assertRedirect('/media');

    $redirect->delete();
    $this->get('/old-news')->assertNotFound();
});

it('never redirects to another site, even if such a target reached the database', function (string $target) {
    Redirect::factory()->create(['source' => 'old-page', 'target' => $target]);

    $this->get('/old-page')->assertNotFound();
})->with(['//evil.example', '/\\evil.example', 'https://evil.example', '/path with space']);

it('redirects only reads, not forms sent to an old path', function () {
    Redirect::factory()->create(['source' => 'old-form', 'target' => '/contact']);

    // ويُرد بالصفحة العربية ولو لم يمر الطلب بمجموعة web
    $this->post('/old-form')->assertStatus(405)->assertSee('dir="rtl"', false)->assertSee(__('errors.client.title'));
});
