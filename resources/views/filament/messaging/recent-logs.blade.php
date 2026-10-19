{{-- آخر الرسائل بحالاتها في «قنوات التواصل»: بلا نص الرسالة، والجوال آخر أربعة أرقام وحدها --}}
@if ($logs->isEmpty())
    <p class="text-sm text-gray-500">{{ __('messaging.logs.empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-start">
            <thead>
                <tr class="text-gray-500">
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.time') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.channel') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.provider') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.usage') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.phone') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('messaging.logs.status') }}</th>
                    <th class="py-2 text-start font-medium">{{ __('messaging.logs.error') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-gray-100 dark:border-white/10">
                        <td class="py-2 pe-4 whitespace-nowrap" dir="ltr">{{ \App\Support\Dates::local($log->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="py-2 pe-4">{{ __('messaging.channels.'.$log->channel) }}</td>
                        <td class="py-2 pe-4" dir="ltr">{{ $log->provider }}</td>
                        <td class="py-2 pe-4">{{ __('messaging.usages.labels.'.$log->usage) }}</td>
                        <td class="py-2 pe-4" dir="ltr">{{ $log->phone_hint }}</td>
                        <td class="py-2 pe-4">{{ $log->status->getLabel() }}</td>
                        <td class="py-2" dir="ltr">{{ $log->error ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
