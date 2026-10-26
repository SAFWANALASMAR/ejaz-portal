{{--
    التحدي ضد الآليات (Turnstile) في نماذج الزائر: الدخول والتسجيل والاستعادة (VerifyBotChallenge، قرار صفوان 20 أكتوبر
    2026)، كالاستبيان. لا يظهر إلا متى ضُبط مفتاح الموقع (BotChallenge::siteKey)، والمشغّل الوهمي في التطوير والاختبارات
    بلا مفتاح. والخادم يتحقق من رمزه، وسكربته مرة واحدة في الصفحة ولو تعددت النماذج.
--}}
@props(['action', 'signedIn' => false])

@php($siteKey = auth()->guest() || $signedIn ? app(\App\Contracts\BotChallenge::class)->siteKey() : null)

@if ($siteKey)
    <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-action="{{ $action }}" data-language="ar"></div>
    @once
        <p class="text-xs text-muted">{{ __('account.bot_challenge.notice') }}</p>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
    @endonce
@endif

@error('bot')
    @once
        <p role="alert" class="text-sm font-medium text-red-700">{{ $message }}</p>
    @endonce
@enderror
