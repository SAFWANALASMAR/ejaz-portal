{{-- رسائل الجلسة: نجاح العملية أو حالتها --}}
@if (session('status'))
    <x-alert type="success" {{ $attributes }}>{{ session('status') }}</x-alert>
@endif

@if (session('error'))
    <x-alert type="error" {{ $attributes }}>{{ session('error') }}</x-alert>
@endif
