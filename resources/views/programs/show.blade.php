{{--
    صفحة البرنامج: رأس القسم بعنوانه وصورته، ثم التفاصيل، وبجانبها بطاقة البيانات والتسجيل.
    رابط الاجتماع للمسجل المؤكد وحده.
--}}
@php
    use App\Enums\ProgramStatus;
    use App\Enums\RegistrationStatus;
    use App\Support\Dates;
    use App\Support\StructuredData;

    $status = $program->status();
    $confirmed = $program->confirmedCount();
    $image = $program->image ? Storage::disk('public')->url($program->image) : null;
@endphp

<x-layouts.app :title="$program->title" :description="$program->summary" :image="$image">
    {{-- البرنامج فعاليةً ومسار التنقل إليه (StructuredData) --}}
    @push('head')
        {{ StructuredData::script(array_values(array_filter([
            StructuredData::program($program),
            StructuredData::breadcrumbs($program->title, route('programs.show', $program->slug), [[__('programs.title'), route('programs.index')]]),
        ]))) }}
    @endpush
    <x-page-hero :title="$program->title" :summary="$program->summary" :image="$image" />

    <section class="py-10 sm:py-14">
        <div class="container-page grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <x-flash />

                @if (filled($program->description))
                    <x-card>
                        <div class="leading-8 whitespace-pre-line text-ink">{{ $program->description }}</div>
                    </x-card>
                @endif

                @if (filled($program->conditions))
                    <x-card>
                        <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('programs.show.conditions') }}</h2>
                        <p class="mt-3 leading-8 whitespace-pre-line text-ink">{{ $program->conditions }}</p>
                    </x-card>
                @endif

                <x-programs.how-to :program="$program" :registration="$registration" :status="$status" :confirmed="$confirmed" />
            </div>

            <aside class="space-y-4">
                <x-card>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-muted">{{ $program->type->name }}</span>
                        @include('programs.badge', ['status' => $status])
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center gap-3">
                            <dt class="shrink-0"><x-icon-tile icon="calendar" size="sm" :index="0" /><span class="sr-only">{{ __('programs.show.starts_at') }}</span></dt>
                            <dd class="text-ink">{{ Dates::display($program->starts_at, withTime: true) }}</dd>
                        </div>
                        <div class="flex items-center gap-3">
                            <dt class="shrink-0"><x-icon-tile icon="computer-desktop" size="sm" :index="1" /><span class="sr-only">{{ __('programs.show.delivery') }}</span></dt>
                            <dd class="text-ink">{{ $program->delivery->getLabel() }}</dd>
                        </div>
                        @if (filled($program->location))
                            <div class="flex items-center gap-3">
                                <dt class="shrink-0"><x-icon-tile icon="map-pin" size="sm" :index="2" /><span class="sr-only">{{ __('programs.show.location') }}</span></dt>
                                <dd class="text-ink">{{ $program->location }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center gap-3">
                            <dt class="shrink-0"><x-icon-tile icon="user-group" size="sm" :index="3" /><span class="sr-only">{{ __('programs.show.seats') }}</span></dt>
                            <dd class="text-ink">
                                {{ $program->seats === null ? __('programs.show.unlimited') : __('programs.show.seats_left', ['count' => number_format(max(0, $program->seats - $confirmed)), 'total' => number_format($program->seats)]) }}
                            </dd>
                        </div>
                        @if (filled($program->audience))
                            <div class="flex items-center gap-3">
                                <dt class="shrink-0"><x-icon-tile icon="users" size="sm" :index="4" /><span class="sr-only">{{ __('programs.show.audience') }}</span></dt>
                                <dd class="text-ink">{{ $program->audience }}</dd>
                            </div>
                        @endif
                        @if ($program->initiative)
                            <div class="flex items-center gap-3">
                                <dt class="shrink-0"><x-icon-tile icon="sparkles" size="sm" :index="5" /><span class="sr-only">{{ __('programs.show.initiative') }}</span></dt>
                                <dd><a href="{{ route('initiatives.show', $program->initiative->slug) }}" wire:navigate class="text-primary underline decoration-accent underline-offset-4">{{ $program->initiative->name }}</a></dd>
                            </div>
                        @endif
                    </dl>

                    @if ($status === ProgramStatus::Soon && $program->registration_opens_at)
                        <p class="mt-4 text-sm text-muted">{{ __('programs.show.opens_at', ['date' => Dates::display($program->registration_opens_at, withTime: true)]) }}</p>
                    @elseif ($status === ProgramStatus::Open && $program->registration_closes_at)
                        <p class="mt-4 text-sm text-muted">{{ __('programs.show.closes_at', ['date' => Dates::display($program->registration_closes_at, withTime: true)]) }}</p>
                    @endif

                    <div class="mt-5">
                        @if ($registration)
                            <p class="mb-3 text-sm font-semibold text-primary">{{ __('programs.show.your_registration', ['status' => $registration->status->getLabel()]) }}</p>
                            <x-button variant="outline" class="w-full" :href="route('account.programs.index')">{{ __('programs.show.manage') }}</x-button>
                        @elseif ($status === ProgramStatus::Open)
                            <x-button class="w-full" :href="route('programs.register', $program->slug)" arrow>
                                {{ auth()->check() ? ($program->hasFreeSeat($confirmed) ? __('programs.show.register') : __('programs.show.join_waitlist')) : __('programs.show.login_to_register') }}
                            </x-button>
                        @else
                            <p class="text-sm text-muted">{{ __('programs.show.closed') }}</p>
                        @endif
                    </div>
                </x-card>

                {{-- برنامج المكتبة: عنصره، وللمسجل البدء والتقدّم و«أتممت» (docs/decisions/2026-11-02-reading-program.md) --}}
                @if ($program->libraryKind() !== null && $program->libraryItem !== null)
                    <x-programs.library-panel :program="$program" :registration="$registration" />
                @endif

                @if ($registration?->status === RegistrationStatus::Confirmed && $program->delivery->isOnline() && ! $program->isLibraryProgram())
                    <x-card>
                        <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('programs.show.meeting') }}</h2>
                        @if (filled($program->meeting_url))
                            <a href="{{ $program->meeting_url }}" rel="noopener noreferrer" target="_blank" dir="ltr" class="mt-2 block break-all text-primary underline decoration-accent underline-offset-4">{{ $program->meeting_url }}</a>
                            <p class="mt-2 text-sm text-muted">{{ __('programs.show.meeting_hint') }}</p>
                        @else
                            <p class="mt-2 text-sm text-muted">{{ __('programs.show.meeting_pending') }}</p>
                        @endif
                    </x-card>
                @endif
            </aside>
        </div>
    </section>
</x-layouts.app>
