{{--
    صفحة الفرصة: رأس القسم بعنوانها، ثم تفاصيلها وموعد إغلاقها، وزر التقديم: للزائر رابط الدخول،
    ولمن قدّم رابط طلبه، ولغيره نموذج التقديم ما دام التقديم مفتوحاً.
--}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.app :title="$opening->title" :description="$opening->summary">
    <x-page-hero :title="$opening->title" :summary="$opening->summary" />

    <section class="py-10 sm:py-14">
        <div class="container-page grid gap-8 lg:grid-cols-[1fr_20rem]">
            <x-card class="leading-8 text-ink">
                @if (filled($opening->description))
                    <div class="whitespace-pre-line">{{ $opening->description }}</div>
                @endif
            </x-card>

            <aside class="space-y-4">
                <x-card>
                    <p class="text-sm text-muted">{{ $opening->kind->getLabel() }}</p>
                    <p class="mt-2 flex items-center gap-2 text-ink">
                        {{ \App\Support\Icon::svg('calendar', 'size-5 text-accent') }}
                        @if ($opening->closes_at)
                            {{ __('services.listing.closes_at') }}: {{ Dates::display($opening->closes_at, withTime: true) }}
                        @else
                            {{ __('services.listing.open_ended') }}
                        @endif
                    </p>

                    <div class="mt-5">
                        @if ($existing)
                            <p class="mb-3 text-sm text-muted">{{ __('services.opening.already_applied', ['reference' => $existing->reference]) }}</p>
                            <x-button variant="outline" class="w-full" :href="route('account.requests.show', $existing)">{{ __('services.opening.view_request') }}</x-button>
                        @elseif (! $opening->acceptsApplications())
                            <p class="text-sm text-muted">{{ __('services.opening.closed') }}</p>
                        @elseif (auth()->check())
                            <x-button class="w-full" :href="route('services.apply', $opening->slug)" arrow>{{ __('services.opening.apply') }}</x-button>
                        @else
                            <x-button class="w-full" :href="route('services.apply', $opening->slug)" arrow>{{ __('services.opening.login_to_apply') }}</x-button>
                        @endif
                    </div>
                </x-card>

                <a href="{{ route($opening->kind->route()) }}" wire:navigate class="inline-block text-sm text-primary underline decoration-accent underline-offset-4">
                    {{ __('services.opening.back', ['kind' => $opening->kind->getLabel()]) }}
                </a>
            </aside>
        </div>
    </section>
</x-layouts.app>
