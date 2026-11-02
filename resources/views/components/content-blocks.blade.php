{{--
    المقاطع المرنة من محرر اللوحة (App\Filament\Forms\ContentFields::blocks): نص، وبطاقات، وصورة.
    stack: مقاطع النص القصيرة المتتالية بطاقات مرقمة متراكمة (ContentBlocks::grouped — content/blocks/stack)، في «عن الجمعية»
    وحدها كما في التصور. وجسم الخبر والصفحات والمبادرات تبقى نصاً متصلاً.
    الأنواع من قائمة معروفة (ContentBlocks::normalize)، فلا يُبنى اسم قالب من مدخل حر. والمقطع المخفي بمفتاحه في «محرر الصفحات»
    لا يُعرض (ContentBlocks::visible).
--}}
@props(['blocks', 'stack' => false])

@foreach ($stack ? \App\Support\ContentBlocks::grouped(\App\Support\ContentBlocks::visible($blocks)) : \App\Support\ContentBlocks::visible($blocks) as $block)
    @include('content.blocks.'.$block['type'], ['data' => $block['data']])
@endforeach
