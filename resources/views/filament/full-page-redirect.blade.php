{{-- تحويل إلى خارج اللوحة أثناء التنقّل فيها (LeavePanelWithFullPageLoad): يضعه Livewire مكان الصفحة، فيحمّل المتصفح الوجهة تحميلاً كاملاً --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $url }}">
    <title>{{ __('admin.leaving_panel') }}</title>
</head>
<body>
    <p><a href="{{ $url }}">{{ __('admin.leaving_panel') }}</a></p>
</body>
</html>
