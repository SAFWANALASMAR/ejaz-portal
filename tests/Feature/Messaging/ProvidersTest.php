<?php

use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ProviderHttp;
use App\Services\Sms\MsegatSmsGateway;
use App\Services\Sms\TaqnyatSmsGateway;
use App\Services\Sms\UnifonicSmsGateway;
use App\Services\WhatsApp\CloudWhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const PROVIDER_TEST_KEY = 'sk-live-very-secret-123';

/**
 * يلتقط استثناء المزود ليُفحص سببه، وأن رسالته لا تحمل المفتاح ولا النص ولا الرقم.
 */
function providerFailure(Closure $send): MessagingException
{
    try {
        $send();
    } catch (MessagingException $exception) {
        expect($exception->getMessage())->not->toContain(PROVIDER_TEST_KEY)
            ->not->toContain('123456')
            ->not->toContain('500000001');

        return $exception;
    }

    throw new RuntimeException('Expected a MessagingException.');
}

describe('Taqnyat', function () {
    it('sends the documented request and returns the message id', function () {
        Http::fake(['api.taqnyat.sa/*' => Http::response(['statusCode' => 201, 'messageId' => 5452899970], 201)]);

        $id = (new TaqnyatSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', 'رمز التحقق: 123456');

        expect($id)->toBe('5452899970');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.taqnyat.sa/v1/messages'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.PROVIDER_TEST_KEY)
            && $request['recipients'] === [966500000001]
            && $request['body'] === 'رمز التحقق: 123456'
            && $request['sender'] === 'EJAZ');
    });

    it('fails without retry on a provider rejection, and retries on server errors', function () {
        Http::fakeSequence('api.taqnyat.sa/*')
            ->push(['statusCode' => 401, 'message' => 'invalid credentials information'], 401)
            ->push('', 503);

        $gateway = new TaqnyatSmsGateway(PROVIDER_TEST_KEY, 'EJAZ');

        $rejected = providerFailure(fn () => $gateway->send('+966500000001', '123456'));
        $down = providerFailure(fn () => $gateway->send('+966500000001', '123456'));

        expect($rejected->reason)->toBe('http_401')->and($rejected->retryable)->toBeFalse()
            ->and($down->reason)->toBe('http_503')->and($down->retryable)->toBeTrue();
    });
});

describe('Unifonic', function () {
    it('sends the documented form request and returns the message id', function () {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => true, 'errorCode' => 'ER-00', 'data' => ['MessageID' => 3000012345]])]);

        $id = (new UnifonicSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', 'رمز التحقق: 123456');

        expect($id)->toBe('3000012345');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://el.cloud.unifonic.com/rest/SMS/messages'
            && $request->isForm()
            && $request['AppSid'] === PROVIDER_TEST_KEY
            && $request['SenderID'] === 'EJAZ'
            && $request['Recipient'] === '966500000001'
            && $request['Body'] === 'رمز التحقق: 123456');
    });

    it('omits an empty sender so the account default is used', function () {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => true, 'data' => []])]);

        (new UnifonicSmsGateway(PROVIDER_TEST_KEY, null))->send('+966500000001', 'x');

        Http::assertSent(fn (Request $request) => ! array_key_exists('SenderID', $request->data()));
    });

    it('reports the provider error code', function () {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => false, 'message' => 'Dest num is too short', 'errorCode' => 'ER-04', 'data' => []], 400)]);

        $failure = providerFailure(fn () => (new UnifonicSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456'));

        expect($failure->reason)->toBe('unifonic:ER-04')->and($failure->retryable)->toBeFalse();
    });

    it('treats a 200 response with success false as a failure', function () {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => 'false', 'errorCode' => 'ER-01'])]);

        expect(providerFailure(fn () => (new UnifonicSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456'))->reason)
            ->toBe('unifonic:ER-01');
    });
});

describe('Msegat', function () {
    it('sends the documented JSON request', function () {
        Http::fake(['www.msegat.com/*' => Http::response(['code' => '1', 'message' => 'Success'])]);

        $id = (new MsegatSmsGateway('ejaz', PROVIDER_TEST_KEY, 'EJAZ'))->send('00966500000001', 'رمز التحقق: 123456');

        expect($id)->toBeNull();
        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.msegat.com/gw/sendsms.php'
            && $request->isJson()
            && $request['userName'] === 'ejaz'
            && $request['apiKey'] === PROVIDER_TEST_KEY
            && $request['numbers'] === '966500000001'
            && $request['userSender'] === 'EJAZ'
            && $request['msg'] === 'رمز التحقق: 123456'
            && $request['msgEncoding'] === 'UTF8');
    });

    it('accepts M0000 and rejects other codes', function () {
        Http::fakeSequence('www.msegat.com/*')
            ->push(['code' => 'M0000', 'message' => 'Success'])
            ->push(['code' => 'M0002', 'message' => 'Invalid login info']);

        $gateway = new MsegatSmsGateway('ejaz', PROVIDER_TEST_KEY, 'EJAZ');
        $gateway->send('+966500000001', '123456');

        expect(providerFailure(fn () => $gateway->send('+966500000001', '123456'))->reason)->toBe('msegat:M0002');
    });
});

it('marks a read timeout as an unknown outcome for every provider', function (Closure $send) {
    // المهلة بعد الاتصال: ربما قبل المزود الرسالة، فلا يُعاد رمز التحقق (SendSms)
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received for https://example.test?key='.PROVIDER_TEST_KEY));

    $failure = providerFailure($send);

    expect($failure->reason)->toBe('timeout_unknown')
        ->and($failure->outcomeUnknown)->toBeTrue();
})->with([
    'taqnyat' => [fn () => (new TaqnyatSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456')],
    'unifonic' => [fn () => (new UnifonicSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456')],
    'msegat' => [fn () => (new MsegatSmsGateway('ejaz', PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456')],
    'whatsapp' => [fn () => (new CloudWhatsAppNotifier('1234567890', PROVIDER_TEST_KEY, 'v23.0', 'ar', ['exam_receipt' => 'exam_receipt']))
        ->send('+966500000001', new WhatsAppMessage('exam_receipt', [], ''))],
]);

it('retries safely when the request never reached the provider', function (string $error) {
    Http::fake(fn () => throw new ConnectionException($error));

    $failure = providerFailure(fn () => (new TaqnyatSmsGateway(PROVIDER_TEST_KEY, 'EJAZ'))->send('+966500000001', '123456'));

    expect($failure->reason)->toBe('connect_failed')
        ->and($failure->retryable)->toBeTrue()
        ->and($failure->outcomeUnknown)->toBeFalse();
})->with([
    'connect timeout' => ['cURL error 28: Connection timed out after 5001 milliseconds'],
    'dns failure' => ['cURL error 6: Could not resolve host: api.taqnyat.sa'],
    'refused' => ['cURL error 7: Failed to connect to api.taqnyat.sa port 443'],
]);

it('uses the configured request timeouts', function () {
    config(['messaging.timeout' => 7, 'messaging.connect_timeout' => 3]);
    expect(ProviderHttp::request()->getOptions())
        ->toMatchArray(['timeout' => 7, 'connect_timeout' => 3]);
});

describe('WhatsApp Cloud API', function () {
    it('sends an approved template with its body parameters in order', function () {
        Http::fake(['graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]])]);

        $notifier = new CloudWhatsAppNotifier('1234567890', PROVIDER_TEST_KEY, 'v23.0', 'ar', ['exam_receipt' => 'ejaz_exam_receipt']);
        $id = $notifier->send('+966500000001', new WhatsAppMessage('exam_receipt', ['مسابقة', '2026-10-19', '10:00', 'جمعية إعجاز'], 'نص'));

        expect($id)->toBe('wamid.ABC');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer '.PROVIDER_TEST_KEY)
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '+966500000001'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'ejaz_exam_receipt'
            && $request['template']['language'] === ['code' => 'ar']
            && $request['template']['components'] === [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'مسابقة'],
                    ['type' => 'text', 'text' => '2026-10-19'],
                    ['type' => 'text', 'text' => '10:00'],
                    ['type' => 'text', 'text' => 'جمعية إعجاز'],
                ],
            ]]);
    });

    it('sends an explicit template without components', function () {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);

        (new CloudWhatsAppNotifier('1234567890', PROVIDER_TEST_KEY, 'v23.0', 'ar', []))
            ->send('+966500000001', new WhatsAppMessage('test', [], '', 'hello_test'));

        Http::assertSent(fn (Request $request) => $request['template']['name'] === 'hello_test' && ! isset($request['template']['components']));
    });

    it('refuses a usage without an approved template, without calling Meta', function () {
        Http::fake();

        $failure = providerFailure(fn () => (new CloudWhatsAppNotifier('1234567890', PROVIDER_TEST_KEY, 'v23.0', 'ar', []))
            ->send('+966500000001', new WhatsAppMessage('exam_receipt', [], '')));

        expect($failure->reason)->toBe('no_template');
        Http::assertNothingSent();
    });

    it('reports the Meta error code', function () {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist', 'type' => 'OAuthException', 'code' => 132001]], 400)]);

        $failure = providerFailure(fn () => (new CloudWhatsAppNotifier('1234567890', PROVIDER_TEST_KEY, 'v23.0', 'ar', ['exam_receipt' => 'x']))
            ->send('+966500000001', new WhatsAppMessage('exam_receipt', [], '')));

        expect($failure->reason)->toBe('meta:132001')->and($failure->retryable)->toBeFalse();
    });
});
