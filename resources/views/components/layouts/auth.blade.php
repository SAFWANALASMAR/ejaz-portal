{{--
    صفحات الدخول والتسجيل والاستعادة والتحقق (التصور النهائي — Final-Login، المرحلة ج): بطاقة بتدرج أزرق فاتح جداً (البند R1،
    والحقول وحدها بيضاء) في المنتصف فوق صورة مموّهة تحت غطاء بالأزرق الداكن، في أعلاها الشعار الرسمي الكامل بالرمز والمخطوطة
    (البند P9)، ثم العنوان في الوسط والوصف، ثم النموذج، وصورة في أسفلها.
--}}
@props(['title', 'heading' => null, 'intro' => null, 'image' => true, 'noindex' => true])

<x-layouts.app :title="$title" :noindex="$noindex">
    <div class="auth-stage relative isolate overflow-hidden py-12 sm:py-17">
        <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="1440" height="960"
            class="absolute inset-0 -z-10 size-full scale-110 object-cover blur-md">
        <div class="absolute inset-0 -z-10 bg-primary-dark/80" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-[28.75rem] px-4">
            <div class="form-sky overflow-hidden rounded-[1.75rem] text-ink shadow-[0_40px_80px_-30px_rgba(0,0,0,0.55)]" data-auth-card>
                <div class="px-6 pt-9 pb-8 sm:px-8.5">
                    <div class="flex flex-col items-center text-center">
                        <x-brand :link="false" class="justify-center" data-auth-logo />
                        <h1 class="mt-4 text-[2.125rem] font-black text-primary-dark">{{ $heading ?? $title }}</h1>
                        @if ($intro)
                            <p class="mt-2 leading-7 text-muted">{{ $intro }}</p>
                        @endif
                    </div>

                    <x-flash class="mt-5" />

                    <div class="mt-6">
                        {{ $slot }}
                    </div>
                </div>

                @if ($image)
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="460" height="110"
                        class="h-[6.875rem] w-full object-cover">
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
