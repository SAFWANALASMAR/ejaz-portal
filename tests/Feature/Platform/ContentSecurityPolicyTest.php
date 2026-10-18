<?php

use App\Enums\RoleName;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Models\User;
use Filament\Facades\Filament;

function cspDirective(string $csp, string $name): string
{
    foreach (explode(';', $csp) as $directive) {
        $directive = trim($directive);

        if (str_starts_with($directive, $name.' ')) {
            return $directive;
        }
    }

    return '';
}

it('allows images only from the site itself unless an origin is configured', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'img-src'))
        ->toBe("img-src 'self' data: blob:")
        ->not->toContain('https:');
});

it('adds the configured storage and CDN origins to img-src', function () {
    config(['security.csp.img_src' => ['https://cdn.ejaz.example', 'https://files.ejaz.example']]);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'img-src'))
        ->toBe("img-src 'self' data: blob: https://cdn.ejaz.example https://files.ejaz.example");
});

it('keeps a mistaken environment value from widening img-src, connect-src and the other configured lists', function () {
    config([
        'security.csp.img_src' => ['*', 'https:', "'unsafe-inline'", ' HTTPS://Files.Ejaz.Example ', 'http://plain.example', 'https://cdn.example/path', 'https://*.cdn.example'],
        'security.csp.connect_src' => ['https://bucket.s3.example:443/', 'wss://live.example', 'data:', 'https://*', 'https://*.objectstorage.example'],
        'security.csp.media_src' => ['blob:', 'https://media.example'],
        'security.csp.form_action' => ['javascript:alert(1)', 'https://pay.example', 'https://*.pay.example'],
    ]);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'img-src'))->toBe("img-src 'self' data: blob: https://files.ejaz.example https://*.cdn.example")
        ->and(cspDirective($csp, 'connect-src'))->toBe("connect-src 'self' https://bucket.s3.example:443 wss://live.example")
        ->and(cspDirective($csp, 'media-src'))->toBe("media-src 'self' blob: https://media.example")
        ->and(cspDirective($csp, 'form-action'))->toBe("form-action 'self' https://pay.example");
});

it('reads the storage and upload origins from the environment variables', function () {
    expect(file_get_contents(config_path('security.php')))
        ->toContain("env('CSP_IMG_SRC'")
        ->toContain("env('CSP_CONNECT_SRC'");

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'connect-src'))->toBe("connect-src 'self'");
});

it('embeds YouTube only from the privacy-enhanced domain', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'frame-src'))
        ->toContain('https://www.youtube-nocookie.com')
        ->not->toContain('https://www.youtube.com');
});

// ملاحظات التجربة الشاملة (الخلل 3): أداة رفع الصور في اللوحة ترسم المعاينة في عامل من blob:
it('lets the admin forms draw image previews in a blob worker, without widening script-src', function (string $path) {
    $csp = (string) $this->actingAs(userWithRole(RoleName::SuperAdmin))->get($path)->assertOk()
        ->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'worker-src'))->toBe("worker-src 'self' blob:")
        ->and(cspDirective($csp, 'script-src'))->not->toContain('blob:')->not->toContain('unsafe-inline');
})->with([
    'dashboard' => '/admin',
    'news form' => fn () => NewsArticleResource::getUrl('create'),
]);

it('keeps workers out of the public site and the account', function (string $path) {
    $user = User::factory()->create();
    $response = str_starts_with($path, '/account') ? $this->actingAs($user)->get($path) : $this->get($path);
    $csp = (string) $response->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('worker-src')
        ->and(cspDirective($csp, 'script-src'))->not->toContain('blob:');
})->with(['/', '/register', '/contact', '/account/profile']);

it('draws the admin avatar locally instead of calling an outside service', function () {
    Filament::setCurrentPanel('admin');

    $user = User::factory()->create(['name' => 'سارة <script>alert(1)</script> أحمد']);

    $url = Filament::getUserAvatarUrl($user);

    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->not->toContain('ui-avatars.com');

    $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')));

    expect($svg)->toStartWith('<svg')
        ->not->toContain('<script')
        ->toContain('س');
});
