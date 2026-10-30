{{--
    مقاطع النص القصيرة المتتالية (التصور النهائي — Final-About: «الرؤية» و«الرسالة» و«الأهداف» بطاقات متجاورة): بطاقة بيضاء لكل
    مقطع، في رأسها مربع أيقونة متدرج بلونه من موضعه بجانب العنوان، ثم النص. والأيقونة من العنوان (الرؤية والرسالة)، وإلا رمز
    عام. والمقاطع من ContentBlocks::grouped، فلا نص هنا.
--}}
@php
    $icons = [__('ui.identity.vision') => 'eye', __('ui.identity.mission') => 'paper-airplane'];
    $count = count($data['items']);
@endphp

<section class="mx-auto max-w-[82.5rem] px-4 pt-7 pb-11 sm:px-7">
    <ol @class(['grid gap-5', 'md:grid-cols-2' => $count >= 2, 'lg:grid-cols-3' => $count >= 3 && $count !== 4]) data-stack data-reveal-group>
        @foreach ($data['items'] as $index => $item)
            <li class="card-lift group flex flex-col gap-2.5 px-6.5 py-6" data-stack-card>
                <h2 class="flex items-center gap-3 text-[1.625rem] font-black text-primary-dark">
                    <x-icon-tile :icon="$icons[trim($item['heading'])] ?? 'sparkles'" :index="$index" size="lg" />
                    {{ $item['heading'] }}
                </h2>
                <div class="prose-ar text-[17px] leading-[1.8] text-[var(--concept-text)]">{{ \App\Support\RichText::toHtml(is_string($item['body'] ?? null) ? $item['body'] : null) }}</div>
            </li>
        @endforeach
    </ol>
</section>
