{{-- «عن الجمعية»: رأس القسم، ثم مقاطع الصفحة، ثم مجلس الإدارة (يُخزَّن نصاً — AboutController) --}}
<x-page-hero :title="$page->title" :summary="$page->summary" />

<x-content-blocks :blocks="$page->blocks" />

@if ($members->isNotEmpty())
    {{-- مجلس الإدارة على قسم داكن (نمط ٥): بطاقة زجاجية لكل عضو بصورة دائرية بحلقة متوهجة من ألوان الهوية --}}
    <section class="section-dark py-14 sm:py-20" aria-labelledby="board">
        <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="container-page">
            <x-section-heading id="board" center>{{ __('content.about.board') }}</x-section-heading>

            <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group>
                @foreach ($members as $member)
                    <li class="glass group rounded-3xl p-6 text-center transition hover:-translate-y-1" data-person-card>
                        <span class="person-ring mx-auto block size-28">
                            @if ($member->photo)
                                <img src="{{ Storage::disk('public')->url($member->photo) }}" alt="" loading="lazy" width="112" height="112" class="size-full rounded-full object-cover">
                            @else
                                <span class="flex size-full items-center justify-center rounded-full bg-primary-dark text-brand-light" aria-hidden="true">
                                    {{ \App\Support\Icon::svg('user', 'size-12') }}
                                </span>
                            @endif
                        </span>
                        <p class="mt-5 font-bold text-white">{{ $member->name }}</p>
                        <p class="mt-1 text-sm text-white/80">{{ $member->position }}</p>
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
