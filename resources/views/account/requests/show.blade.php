{{--
    صفحة الطلب لصاحبه: الحالة والرقم المرجعي، وتنبيه المسودة أو الاستكمال مع نموذج التعديل، ثم
    البيانات والمرفقات، ثم سجل الطلب بردود الجمعية (بلا الملاحظات الداخلية).
--}}
@php
    use App\Enums\FormFieldType;
    use App\Enums\ServiceRequestStatus;
    use App\Support\Dates;

    $opening = $serviceRequest->opening;
    $editable = auth()->user()->can('edit', $serviceRequest);
@endphp

<x-layouts.account :title="$opening->title">
    <x-card class="flex flex-wrap items-center justify-between gap-4">
        <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div>
                <dt class="text-muted">{{ __('services.requests.reference') }}</dt>
                <dd class="mt-1 font-semibold text-ink" dir="ltr">{{ $serviceRequest->reference }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ __('services.requests.submitted_at') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $serviceRequest->submitted_at ? Dates::display($serviceRequest->submitted_at) : __('services.requests.not_submitted') }}</dd>
            </div>
        </dl>
        @include('account.requests.status', ['status' => $serviceRequest->status])
    </x-card>

    @if ($editable)
        <x-alert type="warning">{{ $serviceRequest->status === ServiceRequestStatus::Draft ? __('services.requests.draft') : __('services.requests.needs_completion') }}</x-alert>

        @include('account.requests.editor')
    @else
        <x-card>
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('services.requests.details') }}</h2>
            <dl class="grid gap-4 md:grid-cols-2">
                @foreach ($opening->fields() as $field)
                    <div @class(['md:col-span-2' => in_array($field['type'], [FormFieldType::Textarea, FormFieldType::Pdf], true)])>
                        <dt class="text-sm text-muted">{{ $field['label'] }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-ink">
                            @if ($field['type'] === FormFieldType::Pdf)
                                @isset($serviceRequest->files[$field['key']])
                                    <a href="{{ route('account.requests.file', [$serviceRequest, $field['key']]) }}" class="inline-flex items-center gap-2 text-primary underline decoration-gold underline-offset-4">
                                        {{ svg('heroicon-o-arrow-down-tray', 'size-4') }}
                                        {{ __('services.requests.download') }}: {{ $serviceRequest->files[$field['key']]['name'] }}
                                    </a>
                                @else
                                    —
                                @endisset
                            @else
                                {{ filled($serviceRequest->answers[$field['key']] ?? null) ? $serviceRequest->answers[$field['key']] : '—' }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    @endif

    @if ($serviceRequest->events->isNotEmpty())
        <x-card>
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('services.requests.history') }}</h2>
            <ol class="space-y-4 border-s-2 border-line ps-5">
                @foreach ($serviceRequest->events as $event)
                    <li>
                        <p class="flex flex-wrap items-center gap-3">
                            @include('account.requests.status', ['status' => $event->to_status])
                            <span class="text-sm text-muted">{{ Dates::display($event->created_at, withTime: true) }}</span>
                        </p>
                        @if (filled($event->message))
                            <div class="mt-2 rounded-xl bg-sand p-4 text-ink">
                                <p class="text-sm font-semibold text-primary">{{ __('services.requests.reply') }}</p>
                                <p class="mt-1 whitespace-pre-line">{{ $event->message }}</p>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif
</x-layouts.account>
