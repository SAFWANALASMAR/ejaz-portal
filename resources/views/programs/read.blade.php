{{--
    قراءة كتاب برنامج «قراءة كتاب» (docs/decisions/2026-11-02-reading-program.md): الكتاب في قارئ الصفحة «للقراءة فقط» دائماً، فتُحصى
    صفحاته (library-progress.js)، ولا يتغير خيار تنزيله في المكتبة. للمسجل المؤكد وحده، ولا تُخزَّن.
--}}
@php
    $route = [$item->section->value, $item->slug];
@endphp

<x-layouts.app :title="$program->title" noindex>
    <x-page-hero :title="$program->title" :summary="$item->title" />

    <section class="py-8 sm:py-12">
        <div class="container-page">
            <p class="text-sm text-muted">{{ __('programs.library.reader_hint') }}</p>
            @include('library.reader', ['url' => route('library.file', $route), 'title' => $item->title, 'progress' => route('library.progress', $route)])
            <div class="mt-6">
                <x-button variant="outline" :href="route('programs.show', $program->slug)">{{ __('programs.library.back_to_program') }}</x-button>
            </div>
        </div>
    </section>
</x-layouts.app>
