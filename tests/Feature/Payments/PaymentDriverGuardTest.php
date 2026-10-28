<?php

use App\Providers\AppServiceProvider;

/*
| حارس الدفع عند الإقلاع (AppServiceProvider::guardPaymentDriver): قائمة سماح في الإنتاج متى فُعّل الدفع، فلا يقلع
| إلا بمشغّل moyasar ومفتاحيه السريين. ولا يمنع الإقلاع والدفع مؤرشف (PAYMENTS_ENABLED=false)، كما في
| .env.production.example وفي بناء صورة الإنتاج بلا .env.
*/

function guardPaymentDriverIn(string $environment): void
{
    app()->instance('env', $environment);

    (fn () => $this->guardPaymentDriver())->call(new AppServiceProvider(app()));
}

function enabledMoyasarPayments(array $overrides = []): void
{
    config(array_merge([
        'payments.enabled' => true,
        'payments.driver' => 'moyasar',
        'payments.moyasar.secret_key' => 'sk_live_test',
        'payments.webhook_secret' => 'webhook-secret',
    ], $overrides));
}

afterEach(fn () => app()->instance('env', 'testing'));

it('boots in production with moyasar and both secrets once payments are enabled', function () {
    enabledMoyasarPayments();

    expect(fn () => guardPaymentDriverIn('production'))->not->toThrow(Throwable::class);
});

it('refuses any other driver in production once payments are enabled', function (string $driver) {
    enabledMoyasarPayments(['payments.driver' => $driver]);

    expect(fn () => guardPaymentDriverIn('production'))->toThrow(RuntimeException::class, 'PAYMENT_DRIVER must be moyasar');
})->with(['fake', 'Moyasar', 'stripe', '']);

it('refuses an empty secret in production once payments are enabled', function (string $key, string $variable, string $value) {
    enabledMoyasarPayments([$key => $value]);

    expect(fn () => guardPaymentDriverIn('production'))->toThrow(RuntimeException::class, $variable.' must be set');
})->with([
    'secret key empty' => ['payments.moyasar.secret_key', 'MOYASAR_SECRET_KEY', ''],
    'secret key blank' => ['payments.moyasar.secret_key', 'MOYASAR_SECRET_KEY', '   '],
    'webhook secret empty' => ['payments.webhook_secret', 'PAYMENT_WEBHOOK_SECRET', ''],
    'webhook secret blank' => ['payments.webhook_secret', 'PAYMENT_WEBHOOK_SECRET', '   '],
]);

it('boots in production with payments archived, as the production template and the image build do', function () {
    config([
        'payments.enabled' => false,
        'payments.driver' => 'fake',
        'payments.moyasar.secret_key' => '',
        'payments.webhook_secret' => '',
    ]);

    expect(fn () => guardPaymentDriverIn('production'))->not->toThrow(Throwable::class);
});

it('allows the fake driver without secrets outside production', function () {
    enabledMoyasarPayments(['payments.driver' => 'fake', 'payments.moyasar.secret_key' => '', 'payments.webhook_secret' => '']);

    expect(fn () => guardPaymentDriverIn('local'))->not->toThrow(Throwable::class);
});

it('keeps the production template on archived payments', function () {
    $template = (string) file_get_contents(base_path('.env.production.example'));

    expect($template)->toMatch('/^PAYMENTS_ENABLED=false$/m');
});
