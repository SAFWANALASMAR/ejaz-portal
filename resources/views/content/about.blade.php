{{-- «عن الجمعية»: رأس القسم، ثم مقاطع الصفحة، ثم مجلس الإدارة (يُخزَّن نصاً — AboutController) --}}
<x-page-hero :title="$page->title" :summary="$page->summary" />

<x-content-blocks :blocks="$page->blocks" />

@if ($members->isNotEmpty())
    <section class="bg-surface py-12 sm:py-16" aria-labelledby="board">
        <div class="container-page">
            <x-section-heading id="board">{{ __('content.about.board') }}</x-section-heading>

            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($members as $member)
                    <li class="rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs">
                        @if ($member->photo)
                            <img src="{{ Storage::disk('public')->url($member->photo) }}" alt="" loading="lazy" class="mx-auto size-28 rounded-full object-cover">
                        @else
                            <span class="mx-auto flex size-28 items-center justify-center rounded-full bg-primary-soft text-primary" aria-hidden="true">
                                {{ svg('heroicon-o-user', 'size-12') }}
                            </span>
                        @endif
                        <p class="mt-4 font-bold text-ink">{{ $member->name }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $member->position }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@elseif (! app()->isProduction())
    {{-- مجلس الإدارة لا يظهر في الموقع حتى تُدخل أسماؤه بموافقة أصحابها --}}
    <div class="container-page py-8">
        <x-missing :what="__('content.about.board_missing')" />
    </div>
@endif
