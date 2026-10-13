{{-- فاصل «أو» بين خيارين --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-sm text-muted']) }}>
    <span class="h-px flex-1 bg-line"></span>
    <span>{{ __('account.or') }}</span>
    <span class="h-px flex-1 bg-line"></span>
</div>
