{{-- رد طلب تنقّل سريع وجهته تُحمَّل تحميلاً كاملاً (FullPageLoadOnNavigate): يضعه Livewire مكان الصفحة، فيحمّل المتصفح الوجهة --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $url }}">
    <title>{{ __('site.full_page_load') }}</title>
</head>
<body>
    <p><a href="{{ $url }}">{{ __('site.full_page_load') }}</a></p>
</body>
</html>
