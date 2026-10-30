{{--
    مكتبة ملفات الأعضاء: بلاطات التصنيفات (نمط الحوكمة)، ثم قائمة الملفات، لكل ملف أيقونة PDF وعنوانه
    وتصنيفه ورابط تنزيل مؤقت موقّع. وملفات المجلس لمجلس الإدارة وحده.
--}}
@php
    use App\Enums\AssemblyFileAudience;
    use App\Enums\AssemblyFileCategory;
    use App\Support\Dates;
@endphp

<x-layouts.app :title="__('assembly.files.title')" noindex>
    <section class="bg-surface/60 py-12 sm:py-16">
        <div class="container-page max-w-5xl">
            <span class="block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
            <h1 class="mt-3 text-3xl font-bold text-primary sm:text-4xl">{{ __('assembly.files.title') }}</h1>

            <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <li>
                    <a href="{{ route('assembly.files.index') }}" wire:navigate @if ($active === null) aria-current="true" @endif
                        class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-5 text-center shadow-xs transition hover:border-primary/40 aria-[current=true]:border-accent aria-[current=true]:ring-1 aria-[current=true]:ring-accent">
                        <span class="text-accent" aria-hidden="true">{{ \App\Support\Icon::svg('folder', 'size-8') }}</span>
                        <span class="font-semibold text-ink">{{ __('assembly.files.all') }}</span>
                    </a>
                </li>
                @foreach (AssemblyFileCategory::cases() as $category)
                    <li>
                        <a href="{{ route('assembly.files.index', ['category' => $category->value]) }}" wire:navigate @if ($active === $category) aria-current="true" @endif
                            class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-5 text-center shadow-xs transition hover:border-primary/40 aria-[current=true]:border-accent aria-[current=true]:ring-1 aria-[current=true]:ring-accent">
                            <span class="text-accent" aria-hidden="true">{{ \App\Support\Icon::svg($category->icon(), 'size-8') }}</span>
                            <span class="font-semibold text-ink">{{ $category->getLabel() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($files->isEmpty())
                <x-empty-state icon="folder-open" :message="__('assembly.files.empty')" class="mt-10" />
            @else
                <ul class="mt-8 divide-y divide-line/70 overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs">
                    @foreach ($files as $file)
                        <li>
                            <a href="{{ $file->downloadUrl() }}" class="flex items-start gap-4 p-4 transition hover:bg-surface/60 sm:p-5">
                                <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-primary-soft text-primary" aria-hidden="true">
                                    {{ \App\Support\Icon::svg('document-text', 'size-6') }}
                                    <span class="text-[10px] font-bold">{{ __('assembly.files.pdf') }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $file->title }}</span>
                                    <span class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                                        <span>{{ $file->category->getLabel() }}</span>
                                        <span>{{ Dates::display($file->created_at) }}</span>
                                        @if ($file->audience === AssemblyFileAudience::Board)
                                            <span class="font-semibold text-accent-dark">{{ __('assembly.files.board_only') }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="self-center text-sm font-semibold text-primary">{{ __('assembly.files.download') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{ $files->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
