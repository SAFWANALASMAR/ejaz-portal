<?php

use App\Providers\AppServiceProvider;

/*
| حارس مشغّل الدفع الوهمي عند الإقلاع (AppServiceProvider::guardPaymentDriver): يُرفض fake في الإنتاج متى فُعّل الدفع،
| ولا يمنع الإقلاع والدفع مؤرشف (PAYMENTS_ENABLED=false)، كما في .env.production.example وفي بناء صورة الإنتاج بلا .env.
*/

function guardPaymentDriverIn(string $environment): void
{
    app()->instance('env', $environment);

    (fn () => $this->guardPaymentDriver())->call(new AppServiceProvider(app()));
}

afterEach(fn () => app()->instance('env', 'testing'));

it('refuses the fake payment driver in production once payments are enabled', function () {
    config(['payments.enabled' => true, 'payments.driver' => 'fake']);

    expect(fn () => guardPaymentDriverIn('production'))->toThrow(RuntimeException::class, 'PAYMENT_DRIVER=fake');
});

it('boots in production with payments archived, as the production template and the image build do', function () {
    config(['payments.enabled' => false, 'payments.driver' => 'fake']);

    expect(fn () => guardPaymentDriverIn('production'))->not->toThrow(RuntimeException::class);
});

it('allows moyasar in production and the fake driver outside it', function () {
    config(['payments.enabled' => true, 'payments.driver' => 'moyasar']);
    expect(fn () => guardPaymentDriverIn('production'))->not->toThrow(RuntimeException::class);

    config(['payments.driver' => 'fake']);
    expect(fn () => guardPaymentDriverIn('local'))->not->toThrow(RuntimeException::class);
});

it('keeps the production template on archived payments', function () {
    $template = (string) file_get_contents(base_path('.env.production.example'));

    expect($template)->toMatch('/^PAYMENTS_ENABLED=false$/m');
});
