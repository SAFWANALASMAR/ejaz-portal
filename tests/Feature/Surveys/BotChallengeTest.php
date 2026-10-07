<?php

use App\Contracts\BotChallenge;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\BotChallenge\FakeBotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
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
        ->push(['success' => true])
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
        ->push(['success' => true], 500)
        ->pushFailedConnection(),
    ]);

    expect($turnstile->passes('good'))->toBeTrue();
    Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'good' && ! isset($request['remoteip']));

    expect($turnstile->passes('bad'))->toBeFalse()
        ->and($turnstile->passes('server-error'))->toBeFalse()
        ->and($turnstile->passes('down'))->toBeFalse();

    // بلا رمز لا طلب أصلاً
    expect($turnstile->passes(''))->toBeFalse();
    Http::assertSentCount(4);
});

it('refuses the fake challenge in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new FakeBotChallenge)->passes('x'))->toThrow(RuntimeException::class);
});
