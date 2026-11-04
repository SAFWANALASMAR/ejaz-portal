{{--
    المقاطع المرنة من محرر اللوحة (App\Filament\Forms\ContentFields::blocks): نص، وبطاقات، وصورة.
    stack: مقاطع النص القصيرة المتتالية بطاقات مرقمة متراكمة (ContentBlocks::grouped — content/blocks/stack)، في «عن الجمعية»
    وحدها كما في التصور. وجسم الخبر والصفحات والمبادرات تبقى نصاً متصلاً.
    الأنواع من قائمة معروفة (ContentBlocks::normalize)، فلا يُبنى اسم قالب من مدخل حر. والمقطع المخفي بمفتاحه في «محرر الصفحات»
    لا يُعرض (ContentBlocks::visible).
--}}
@props(['blocks', 'stack' => false])

{{-- كل شريط بطاقات عكس الذي قبله (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢١): الأول من اليمين إلى اليسار («أهداف الجمعية» في «عن
     الجمعية»)، والثاني من اليسار إلى اليمين («قيم الجمعية»)، وهكذا --}}
@php($strip = 0)
@foreach ($stack ? \App\Support\ContentBlocks::grouped(\App\Support\ContentBlocks::visible($blocks)) : \App\Support\ContentBlocks::visible($blocks) as $block)
    @include('content.blocks.'.$block['type'], ['data' => $block['data'], 'strip' => $block['type'] === 'cards' ? $strip++ : $strip])
@endforeach
