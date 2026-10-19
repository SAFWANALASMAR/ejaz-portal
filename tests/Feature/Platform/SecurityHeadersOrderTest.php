<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

// رؤوس الحماية على كل رد من مجموعة web، ومنه ما يُرسم قبل المتحكم: تجاوز الحد (ThrottleRequests) والربط الضمني
// للنموذج (SubstituteBindings). فـ SecurityHeaders أول المجموعة (bootstrap/app.php — docs/decisions.md)

/**
 * رؤوس الحماية على الرد، ورمز الطلب في سياسته هو رمز سكربتات الصفحة.
 *
 * @param  TestResponse<Response>  $response
 */
function expectProtectedPage(TestResponse $response): void
{
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect(preg_match("/'nonce-([^']+)'/", $csp, $nonce))->toBe(1)
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->getContent())->toContain('nonce="'.$nonce[1].'"');
}

it('protects a page over its rate limit, rendered before the controller', function () {
    Route::middleware(['web', 'throttle:1,1,security-order-test'])->get('/security-order-limited', fn () => 'ok')
        ->name('security-order.limited');

    $this->get('/security-order-limited')->assertOk();

    expectProtectedPage($this->get('/security-order-limited')->assertTooManyRequests());
});

it('protects the not-found page of an implicit model binding', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get('/account/requests/0f4c2a9e-0000-4000-8000-000000000000')
        ->assertNotFound();

    expectProtectedPage($response);
});

it('protects the redirect to the login page, answered before the controller', function () {
    $response = $this->get('/account/requests')->assertRedirect(route('login'));

    expect($response->headers->get('Content-Security-Policy'))->toContain("'nonce-")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('still protects an ordinary page and a page a controller rejects', function () {
    expectProtectedPage($this->get('/')->assertOk());
    expectProtectedPage($this->get('/media/news/no-such-article')->assertNotFound());
});
