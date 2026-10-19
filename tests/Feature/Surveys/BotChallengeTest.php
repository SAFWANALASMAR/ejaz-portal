<?php

use App\Contracts\BotChallenge;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\BotChallenge\FakeBotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
| قرار المشرف بتفويض صفوان (مراجعة #5): Cloudflare Turnstile على إرسال غير المسجلين في الاستبيان العام،
| خلف واجهة بتطبيق وهمي، ومفتاحاه فارغان في .env.example.
*/

beforeEach(function () {
    $this->survey = Survey::factory()->withQuestions()->guests()->create();
    [$this->scale, $this->choice] = $this->survey->questions->all();
});

it('refuses a guest whose challenge fails, and leaves signed-in users alone', function () {
    $challenge = new FakeBotChallenge(passes: false);
    app()->instance(BotChallenge::class, $challenge);

    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0], 'cf-turnstile-response' => 'token'])
        ->assertSessionHas('error', __('surveys.public.challenge_failed'));

    $this->actingAs(User::factory()->create())
        ->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0]])
        ->assertSessionHas('status');

    expect(SurveyResponse::count())->toBe(1)->and($challenge->checked)->toBe(['token']);
});

it('shows the widget to guests only when a site key is set', function () {
    app()->instance(BotChallenge::class, new FakeBotChallenge(siteKey: 'site-key-test'));
    $this->get('/surveys/'.$this->survey->token)->assertOk()->assertSee('data-sitekey="site-key-test"', false)->assertSee('challenges.cloudflare.com/turnstile', false);

    app()->instance(BotChallenge::class, new FakeBotChallenge);
    $this->get('/surveys/'.$this->survey->token)->assertOk()->assertDontSee('cf-turnstile', false);
});

it('verifies the Turnstile token on the server, and fails closed', function () {
    $turnstile = new TurnstileChallenge('site', 'secret');

    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
        ->push(['success' => true, 'action' => 'survey'])
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
        ->push(['success' => true, 'action' => 'survey'], 500)
        ->pushFailedConnection(),
    ]);

    expect($turnstile->passes('good', 'survey'))->toBeTrue();
    Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'good' && ! isset($request['remoteip']));

    expect($turnstile->passes('bad', 'survey'))->toBeFalse()
        ->and($turnstile->passes('server-error', 'survey'))->toBeFalse()
        ->and($turnstile->passes('down', 'survey'))->toBeFalse();

    // بلا رمز لا طلب أصلاً
    expect($turnstile->passes('', 'survey'))->toBeFalse();
    Http::assertSentCount(4);
});

it('refuses the fake challenge in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new FakeBotChallenge)->passes('x', 'survey'))->toThrow(RuntimeException::class);
});

it('refuses to resolve the fake challenge in production, so a forgotten driver shows at the first guest', function () {
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(BotChallenge::class);
    config(['services.bot_challenge.driver' => 'fake']);

    expect(fn () => app(BotChallenge::class))->toThrow(RuntimeException::class);
});

it('resolves the challenge for guests only, so a misconfigured one never stops account holders', function () {
    // مراجعة الوكيل على التكامل: الربط يرمي في الإنتاج بلا Turnstile، وحقنه في توقيع الإرسال كان يرفض
    // كل إجابة في كل استبيان
    app()->bind(BotChallenge::class, fn () => throw new RuntimeException('misconfigured'));
    $members = Survey::factory()->withQuestions()->create();
    [$scale, $choice] = $members->questions->all();

    $this->actingAs(User::factory()->create());
    $this->get('/surveys/'.$members->token)->assertOk();
    $this->post('/surveys/'.$members->token, ['answers' => [$scale->id => 4, $choice->id => 1]])->assertSessionHas('status');
    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0]])->assertSessionHas('status');

    expect(SurveyResponse::count())->toBe(2);
});

it('refuses a malformed token cleanly instead of failing on the server', function () {
    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0], 'cf-turnstile-response' => ['x']])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(SurveyResponse::count())->toBe(0);
});

it('accepts a Turnstile token solved on this site only', function () {
    $turnstile = new TurnstileChallenge('site', 'secret', 'ejaz.sa');

    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
        ->push(['success' => true, 'action' => 'survey', 'hostname' => 'ejaz.sa'])
        ->push(['success' => true, 'action' => 'survey', 'hostname' => 'other.example']),
    ]);

    expect($turnstile->passes('here', 'survey'))->toBeTrue()
        ->and($turnstile->passes('elsewhere', 'survey'))->toBeFalse();
});

it('tells a guest that an outside provider checks the form, when the challenge shows', function () {
    app()->instance(BotChallenge::class, new FakeBotChallenge(siteKey: 'site-key-test'));

    $this->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.privacy_challenge'));
});

it('refuses a token solved for another form', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'contact'])]);

    expect((new TurnstileChallenge('site', 'secret'))->passes('token', 'survey'))->toBeFalse();
});

it('refuses Turnstile without both keys, instead of turning every guest away in silence', function () {
    app()->forgetInstance(BotChallenge::class);
    config(['services.bot_challenge.driver' => 'turnstile', 'services.bot_challenge.turnstile_site_key' => 'site', 'services.bot_challenge.turnstile_secret_key' => '']);

    expect(fn () => app(BotChallenge::class))->toThrow(RuntimeException::class);
});

it('opens the CSP to the challenge on the survey page only, and only with the turnstile driver', function () {
    expect($this->get('/surveys/'.$this->survey->token)->headers->get('Content-Security-Policy'))->not->toContain('challenges.cloudflare.com');

    config(['services.bot_challenge.driver' => 'turnstile']);
    app()->instance(BotChallenge::class, new FakeBotChallenge(siteKey: 'site-key-test'));

    expect($this->get('/surveys/'.$this->survey->token)->headers->get('Content-Security-Policy'))->toContain('challenges.cloudflare.com')
        ->and($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('challenges.cloudflare.com');
});

// قرار صفوان (19 أكتوبر 2026): Turnstile لاستبيان الرضا. والمهلة أو انقطاع الشبكة أو خطأ المزود رفض آمن برسالة
// واضحة للضيف، ولا تُحفظ إجابة
it('turns a guest away with a clear message when Cloudflare times out, is unreachable or errs', function (Closure $reply) {
    app()->instance(BotChallenge::class, new TurnstileChallenge('site', 'secret'));
    Http::fake(['challenges.cloudflare.com/*' => $reply]);

    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0], 'cf-turnstile-response' => 'token'])
        ->assertRedirect()
        ->assertSessionHas('error', __('surveys.public.challenge_failed'));

    expect(SurveyResponse::count())->toBe(0);
})->with([
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5000 milliseconds'),
    'unreachable' => fn () => fn () => throw new ConnectionException('cURL error 6: Could not resolve host'),
    'provider error' => fn () => fn () => Http::response(['success' => true, 'action' => 'survey'], 503),
    'not json' => fn () => fn () => Http::response('<html>bad gateway</html>', 200),
]);

it('saves a guest answer once Cloudflare confirms the token for this form and site', function () {
    app()->instance(BotChallenge::class, new TurnstileChallenge('site', 'secret', 'ejaz.sa'));
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'survey', 'hostname' => 'ejaz.sa'])]);

    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0], 'cf-turnstile-response' => 'token'])
        ->assertSessionHas('status');

    expect(SurveyResponse::count())->toBe(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' && $request['response'] === 'token');
});
