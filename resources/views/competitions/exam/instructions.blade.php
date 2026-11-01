{{-- ثالثًا: صفحة تعليمات الاختبار بالبنود التسعة حرفياً، وزر «بدء الاختبار» معطّل حتى مربع الموافقة --}}
<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    @include('competitions.exam._steps', ['current' => 'instructions'])

    <form method="POST" action="{{ route('competitions.exam.instructions', $competition->slug) }}" data-exam-gate
        class="space-y-5 concept-panel p-6">
        @csrf
        <h2 class="flex items-center gap-2 text-xl font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('competitions.exam.instructions_heading') }}</h2>

        <ul class="list-disc space-y-2 ps-6 leading-8 text-ink">
            @foreach (__('competitions.exam.instructions') as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>

        <div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="accept" value="1" required class="mt-1 accent-primary">
                <span class="font-medium text-ink">{{ __('competitions.exam.instructions_accept') }}</span>
            </label>
            @error('accept')
                <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-button data-exam-gate-submit disabled arrow>{{ __('competitions.exam.begin') }}</x-button>
    </form>
</x-layouts.account>
