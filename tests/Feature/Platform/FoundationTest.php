<?php

use App\Enums\RoleName;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;

it('serves an Arabic right-to-left home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee(__('site.name'));
});

it('sends the security headers on every web response', function () {
    $response = $this->get('/');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("'nonce-")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'");

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
});

it('sends the security headers on the admin panel too, so its screens cannot be framed', function () {
    $this->get('/admin')->assertRedirect()->assertHeader('X-Frame-Options', 'DENY');

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    foreach (['/admin', '/admin/users'] as $path) {
        $response = $this->get($path)->assertOk();

        expect((string) $response->headers->get('Content-Security-Policy'))
            ->toContain("frame-ancestors 'none'")
            ->toContain("'nonce-");

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

it('sends HSTS only over https', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
});

it('shows only visible menu items and refreshes the cached menu on change', function () {
    // منشورة، وإلا أخفت القائمة رابطها (UnpublishedPageLinksTest)
    Page::factory()->create(['slug' => Page::ABOUT]);
    $about = MenuItem::create(['label' => 'عن الجمعية', 'url' => '/about', 'sort' => 1]);
    MenuItem::create(['label' => 'مخفي', 'url' => '/hidden', 'sort' => 2, 'is_visible' => false]);
    MenuItem::create(['parent_id' => $about->id, 'label' => 'الرؤية', 'url' => '/about#vision']);

    $this->get('/')->assertSee('عن الجمعية')->assertSee('الرؤية')->assertDontSee('مخفي');

    $about->update(['label' => 'من نحن']);

    $this->get('/')->assertSee('من نحن')->assertDontSee('عن الجمعية');
});

it('shows contact details from the site settings', function () {
    Setting::put('contact', ['email' => 'contact@example.test', 'address' => 'مكة المكرمة']);

    $this->get('/')->assertSee('contact@example.test')->assertSee('مكة المكرمة');
});

it('never renders an unsafe social link even if one reaches the database', function () {
    Setting::put('contact', ['social' => [
        'x' => 'javascript:alert(1)',
        'instagram' => 'https://ejaz.sa@evil.example',
        'youtube' => 'https://www.youtube.com/@example',
    ]]);

    $this->get('/')
        ->assertDontSee('javascript:alert(1)', false)
        ->assertDontSee('evil.example', false)
        ->assertSee('https://www.youtube.com/@example', false);
});

it('never renders an unsafe menu link even if one reaches the database', function () {
    MenuItem::create(['label' => 'رابط ضار', 'url' => 'javascript:alert(1)']);

    $this->get('/')->assertSee('رابط ضار')->assertDontSee('javascript:alert(1)', false);
});
