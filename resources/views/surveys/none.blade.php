{{--
    صفحة «قياس الرضا» بلا استبيان عام مفتوح (SurveyController::index): بطاقة «قياس الرضا» في الخدمات ظاهرة دائماً (إضافة توجيه المدير،
    البند ١٧)، فتصل إلى هنا برسالة أن لا استبيان الآن بدل صفحة غير موجودة. والبطاقة في المنتصف كصفحة الاستبيان نفسها (الصورة 04)
--}}
<x-layouts.auth :title="__('services.tiles.surveys')" :intro="__('surveys.public.intro')" :image="false">
    <x-alert type="info" data-surveys-none>{{ __('surveys.public.none') }}</x-alert>
</x-layouts.auth>
