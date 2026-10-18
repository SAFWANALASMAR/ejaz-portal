{{-- رسائل الجلسة: نجاح العملية أو حالتها. ورموز Fortify الخام (profile-information-updated وأخواتها) تُعرض بترجمتها
     هنا وحدها، أياً كانت الصفحة التي عاد إليها المستخدم (بياناتي، والأمان، والتوثيق، ولوحته)، فتبقى رسالة عربية واحدة.
     ورمز بلا ترجمة لا يُعرض خاماً --}}
@php
    $fortify = [
        \Laravel\Fortify\Fortify::PROFILE_INFORMATION_UPDATED => 'account.profile.saved',
        \Laravel\Fortify\Fortify::PASSWORD_UPDATED => 'account.profile.password_saved',
        \Laravel\Fortify\Fortify::VERIFICATION_LINK_SENT => 'account.verify_email.sent',
        \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED => 'account.security.status.two-factor-authentication-enabled',
        \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED => 'account.security.status.two-factor-authentication-confirmed',
        \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED => 'account.security.status.two-factor-authentication-disabled',
        \Laravel\Fortify\Fortify::RECOVERY_CODES_GENERATED => 'account.security.status.recovery-codes-generated',
    ];
    $status = session('status');
    $status = is_string($status) && isset($fortify[$status]) ? __($fortify[$status]) : $status;
@endphp

@if (filled($status) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', (string) $status) !== 1)
    <x-alert type="success" {{ $attributes }}>{{ $status }}</x-alert>
@endif

@if (session('error'))
    <x-alert type="error" {{ $attributes }}>{{ session('error') }}</x-alert>
@endif
