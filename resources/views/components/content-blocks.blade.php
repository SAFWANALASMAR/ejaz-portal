{{--
    المقاطع المرنة من محرر اللوحة (App\Filament\Forms\ContentFields::blocks): نص، وبطاقات، وصورة.
    الأنواع من قائمة معروفة (ContentBlocks::normalize)، فلا يُبنى اسم قالب من مدخل حر.
--}}
@props(['blocks'])

@foreach (\App\Support\ContentBlocks::normalize($blocks) as $block)
    @include('content.blocks.'.$block['type'], ['data' => $block['data']])
@endforeach
