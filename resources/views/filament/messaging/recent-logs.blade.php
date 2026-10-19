{{-- آخر الرسائل بحالاتها في «قنوات التواصل»: بلا نص الرسالة، والجوال آخر أربعة أرقام وحدها. والمسافات بأنماط مضمّنة
     كصفحة تحليل الإحصائيات، لأن أنماط اللوحة لا تحوي أصناف Tailwind من القوالب المضافة --}}
@if ($logs->isEmpty())
    <p class="text-sm text-gray-500">{{ __('messaging.logs.empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-start">
            <thead>
                <tr class="text-gray-500">
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.time') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.channel') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.provider') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.usage') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.phone') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.status') }}</th>
                    <th style="padding: 0.5rem; text-align: start; font-weight: 500;">{{ __('messaging.logs.error') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-gray-100 dark:border-white/10">
                        <td style="padding: 0.5rem; white-space: nowrap;" dir="ltr">{{ \App\Support\Dates::local($log->created_at)->format('Y-m-d H:i') }}</td>
                        <td style="padding: 0.5rem;">{{ __('messaging.channels.'.$log->channel) }}</td>
                        <td style="padding: 0.5rem;" dir="ltr">{{ $log->provider }}</td>
                        <td style="padding: 0.5rem;">{{ __('messaging.usages.labels.'.$log->usage) }}</td>
                        <td style="padding: 0.5rem;" dir="ltr">{{ $log->phone_hint }}</td>
                        <td style="padding: 0.5rem;">{{ $log->status->getLabel() }}</td>
                        <td style="padding: 0.5rem;" dir="ltr">{{ $log->error ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
