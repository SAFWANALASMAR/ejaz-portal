{{-- التقديم على فرصة: نموذج الطلب لأول مرة --}}
<x-layouts.account :title="__('services.form.title', ['opening' => $opening->title])">
    @include('account.requests.editor')
</x-layouts.account>
