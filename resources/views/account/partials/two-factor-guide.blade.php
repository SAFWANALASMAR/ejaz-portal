{{--
    شرح التحقق الثنائي بتطبيق المصادقة في صفحة «الأمان» (طلب صفوان، نوفمبر 2026): «كيف أفعّله؟» بخطوات مرقمة بأسماء أزرار
    الصفحة نفسها، و«إن تعطّل التطبيق أو ضاع الجوال». يظهر في كل حالات الصفحة، فلوحة الإدارة تحوّل إليها من لم يفعّله
    (EnsureTwoFactorIsEnabled)، ولا صفحة تفعيل غيرها. والصندوقان بنمط «ما يحتاج تصرفك» في رئيسية اللوحة. والشرح الكامل بصوره
    في docs/guides/two-factor.md.
--}}
@php
    $replace = [
        'enable' => __('account.security.enable'),
        'confirm' => __('account.security.confirm'),
        'disable' => __('account.security.disable'),
        'regenerate' => __('account.security.regenerate'),
        'use_recovery' => __('account.two_factor_challenge.use_recovery'),
    ];
@endphp

<div class="mt-5 grid gap-4 lg:grid-cols-2" data-two-factor-guide>
    <section aria-labelledby="two-factor-how" class="rounded-[1.125rem] border border-[var(--concept-pale)] bg-primary-soft p-5 text-ink" data-two-factor-how>
        <h3 id="two-factor-how" class="flex items-center gap-3 text-[19px] font-black text-primary-dark">
            <x-icon-tile icon="shield-check" shade="dark" size="sm" />
            {{ __('account.two_factor_guide.how.title') }}
        </h3>
        {{-- role="list": سفاري يُسقط دلالة القائمة عن ما بلا علامات --}}
        <ol role="list" aria-label="{{ __('account.two_factor_guide.how.steps_label') }}" class="mt-4 space-y-3">
            @foreach (['app', 'enable', 'scan', 'code', 'recovery'] as $key)
                <li class="flex gap-3 leading-7">
                    <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-black text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span>
                        <span class="sr-only">{{ __('account.two_factor_guide.how.step', ['number' => $loop->iteration]) }}:</span>
                        {{ __('account.two_factor_guide.how.steps.'.$key, $replace) }}
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <section aria-labelledby="two-factor-lost" class="rounded-[1.125rem] border border-[var(--concept-pale)] bg-primary-soft p-5 text-ink" data-two-factor-lost>
        <h3 id="two-factor-lost" class="flex items-center gap-3 text-[19px] font-black text-primary-dark">
            <x-icon-tile icon="lifebuoy" shade="medium" size="sm" />
            {{ __('account.two_factor_guide.lost.title') }}
        </h3>
        <ul role="list" class="mt-4 space-y-3">
            @foreach (['recovery', $user->requiresTwoFactor() ? 'new_phone_required' : 'new_phone', 'lost_all', 'sync'] as $key)
                <li class="flex gap-3 leading-7">
                    <span class="mt-3 size-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                    @if ($key === 'lost_all')
                        {{-- النص مهرَّب، ثم يُوضع رابط «تواصل معنا» مكان :contact --}}
                        <span>{!! str_replace(':contact', '<a href="'.e(route('contact')).'" class="font-bold text-primary-dark underline underline-offset-4" data-two-factor-contact>'.e(__('contact.title')).'</a>', e(__('account.two_factor_guide.lost.items.lost_all'))) !!}</span>
                    @else
                        <span>{{ __('account.two_factor_guide.lost.items.'.$key, $replace) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
