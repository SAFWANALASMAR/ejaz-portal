<?php

use App\Enums\RoleName;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
| CSRF وCORS على كل مسار يكتب (الاختبار الأمني 0.14.0): لارافيل يتخطى فحص رمز الحماية في الاختبارات، فيُستبدل هنا بنسخة
| تفحص كما في الإنتاج. وكل مسار غير GET في الموقع واللوحة وLivewire وFortify يُطلب من أصل آخر بلا رمز، بحساب مسجّل
| الدخول (فلا يرد الدخول قبل الفحص)، فيُرفض بـ 419. والمستثنى بالاسم وحده: إشعارا الدفع وواتساب، ويُفحص توقيعهما في
| إجرائهما (docs/security.md).
*/

const CSRF_EXEMPT = ['webhooks/payments', 'webhooks/whatsapp'];

// رفع لارافيل المحلي إلى القرص الخاص: خارج مجموعة web، ولا يقبل إلا رابطاً موقّعاً مؤقتاً (ReceiveFile)، فيُرفض بلا توقيع
const SIGNED_ONLY = ['storage/{path}'];

beforeEach(function () {
    $this->app->singleton(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
});

/**
 * @return list<array{0: string, 1: string, 2: array<string, string>}>
 */
function writeRoutes(): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->flatMap(fn (Route $route) => collect($route->methods())
            ->reject(fn (string $method) => in_array($method, ['GET', 'HEAD', 'OPTIONS'], true))
            ->map(fn (string $method) => [$method, $route->uri(), $route->wheres]))
        ->values()
        ->all();
}

// كل معامل بأول قيمة تطابق قيده (رقم، أو uuid، أو رمز استبيان من 32 حرفاً)، فيبلغ الطلب مساره لا 405
function writeRouteUri(string $uri, array $wheres = []): string
{
    return '/'.ltrim((string) preg_replace_callback('/\{([^}?]+)\??\}/', function (array $m) use ($wheres): string {
        foreach (['1', (string) Str::uuid(), str_repeat('a', 32)] as $value) {
            if (! isset($wheres[$m[1]]) || preg_match('#^'.$wheres[$m[1]].'$#', $value)) {
                return $value;
            }
        }

        return '1';
    }, $uri), '/');
}

it('refuses every write route from another origin without the token, signed in, except the two signed webhooks', function () {
    $routes = writeRoutes();
    // ومسارات اللوحة وHorizon بمسؤول تقني، فلا يرد منع الدخول إليها قبل فحص الرمز
    $member = userWithRole(RoleName::Member);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $wrong = [];

    foreach ($routes as [$method, $uri, $wheres]) {
        if (in_array($uri, CSRF_EXEMPT, true)) {
            continue;
        }

        $user = str_starts_with($uri, 'admin') || str_starts_with($uri, 'horizon') ? $admin : $member;
        $response = $this->actingAs($user)->call($method, writeRouteUri($uri, $wheres), server: [
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
        ]);

        if (in_array($uri, SIGNED_ONLY, true) ? $response->status() !== 403 : $response->status() !== 419) {
            $wrong[$method.' '.$uri] = $response->status();
        }
    }

    // كل مسار يكتب مرّ، لا قائمة فارغة: الموقع وFortify وLivewire، ومسابقة الطلاب (التسجيل وبدء المرحلة وحفظها وتسليمها)
    expect(count($routes))->toBeGreaterThan(40)
        ->and(collect($routes)->pluck(1))->toContain('login', 'logout', 'contact', 'competitions/{slug}/register',
            'competitions/{slug}/rounds/{round}/start', 'competitions/{slug}/rounds/{round}/units/{number}', 'competitions/{slug}/rounds/{round}/submit')
        ->and(collect($routes)->pluck(1)->contains(fn (string $uri) => str_contains($uri, 'livewire') && str_ends_with($uri, 'update')))->toBeTrue()
        ->and($wrong)->toBe([]);
});

it('refuses a write from a sibling subdomain too: same-site is not same-origin', function () {
    $this->actingAs(userWithRole(RoleName::Member))
        ->call('POST', '/logout', server: ['HTTP_SEC_FETCH_SITE' => 'same-site'])
        ->assertStatus(419);
});

it('accepts the same write with the session token, so the refusal above is the token check', function () {
    $user = userWithRole(RoleName::Member);

    $this->actingAs($user)->withSession(['_token' => 'known-token'])
        ->post('/logout', ['_token' => 'known-token'])
        ->assertRedirect();

    $this->assertGuest();
});

it('keeps the exempt list to the two signed webhooks', function () {
    $middleware = app(PreventRequestForgery::class);

    expect($middleware->getExcludedPaths())->toEqualCanonicalizing(CSRF_EXEMPT);
});

it('sends no CORS header to a preflight or a write from another origin on any write route', function () {
    $wrong = [];

    foreach (writeRoutes() as [$method, $uri]) {
        foreach (['OPTIONS', $method] as $verb) {
            $response = $this->call($verb, writeRouteUri($uri), server: [
                'HTTP_ORIGIN' => 'https://attacker.example',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            ]);

            foreach (['Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials'] as $header) {
                if ($response->headers->has($header)) {
                    $wrong[] = $verb.' '.$uri.' '.$header;
                }
            }
        }
    }

    expect($wrong)->toBe([]);
});
