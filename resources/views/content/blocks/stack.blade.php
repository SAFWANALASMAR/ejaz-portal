{{--
    مقاطع النص القصيرة المتتالية (التصور النهائي — Final-About: «الرؤية» و«الرسالة» و«الأهداف» بطاقات متجاورة): بطاقة ملونة لكل
    مقطع تتعاقب درجتها (tone-card)، في رأسها مربع الأيقونة بجانب العنوان، ثم النص. والأيقونة من العنوان (الرؤية والرسالة)، وإلا رمز
    عام. والمقاطع من ContentBlocks::grouped، فلا نص هنا.
--}}
@php
    $icons = [__('ui.identity.vision') => 'eye', __('ui.identity.mission') => 'paper-airplane'];
    $count = count($data['items']);
@endphp

<section class="mx-auto max-w-[82.5rem] px-4 pt-7 pb-8 sm:px-7">
    <ol @class(['grid gap-4', 'md:grid-cols-2' => $count >= 2, 'lg:grid-cols-3' => $count >= 3 && $count !== 4]) data-stack data-reveal-group>
        @foreach ($data['items'] as $index => $item)
            <li class="tone-card group flex flex-col gap-2.5 px-6 py-5.5" data-stack-card @if ($anchor = \App\Support\ContentBlocks::anchor($item['heading'] ?? null)) id="{{ $anchor }}" @endif>
                <h2 class="flex items-center gap-3 text-[1.5rem] font-black">
                    <x-icon-tile :icon="$icons[trim($item['heading'])] ?? 'sparkles'" size="md" />
                    {{ $item['heading'] }}
                </h2>
                <div class="prose-ar tone-card-muted text-[17px] leading-[1.8]">{{ \App\Support\RichText::toHtml(is_string($item['body'] ?? null) ? $item['body'] : null) }}</div>
            </li>
        @endforeach
    </ol>
</section>
