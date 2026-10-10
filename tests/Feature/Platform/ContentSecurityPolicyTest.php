<?php

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

it('embeds YouTube only from the privacy-enhanced domain', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'frame-src'))
        ->toContain('https://www.youtube-nocookie.com')
        ->not->toContain('https://www.youtube.com');
});

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
