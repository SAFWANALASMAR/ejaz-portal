{{--
    بطاقة ملخص «بياناتي» (تدقيق المشرف): الاسم واسم المستخدم والبريد والجوال ببلاطات متدرجة بالترتيب، والبريد والجوال مخفيان
    (App\Support\Masked) حتى يضغط صاحب الحساب «إظهار البيانات». الكشف في المتصفح وحده (resources/js/profile-reveal.js)، والقيمة
    الكاملة له وحده: الصفحة صفحته، وهي في نموذج التعديل تحتها. وحالة الزر في aria-expanded.
--}}
@php
    use App\Support\Masked;
    use App\Support\SaudiPhone;

    $rows = array_filter([
        ['key' => 'name', 'icon' => 'user', 'full' => $user->name, 'masked' => null],
        $user->username !== null ? ['key' => 'username', 'icon' => 'at-symbol', 'full' => $user->username, 'masked' => null] : null,
        $user->email !== null ? ['key' => 'email', 'icon' => 'envelope', 'full' => $user->email, 'masked' => Masked::email($user->email)] : null,
        $user->phone !== null ? ['key' => 'phone', 'icon' => 'device-phone-mobile', 'full' => SaudiPhone::display($user->phone), 'masked' => Masked::phone($user->phone)] : null,
    ]);
    $hasMasked = collect($rows)->contains(fn (array $row): bool => $row['masked'] !== null);
@endphp

<x-card data-profile-summary>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('account.summary.title') }}</h2>
        @if ($hasMasked)
            <button type="button" data-reveal-toggle aria-expanded="false" aria-controls="profile-summary-list"
                data-label-show="{{ __('account.summary.show') }}" data-label-hide="{{ __('account.summary.hide') }}"
                class="inline-flex items-center gap-2 rounded-full border border-primary bg-white px-4 py-1.5 text-sm font-bold text-primary transition hover:bg-primary-soft">
                {{ \App\Support\Icon::svg('eye', 'size-4', ['aria-hidden' => 'true']) }}
                <span data-reveal-label>{{ __('account.summary.show') }}</span>
            </button>
        @endif
    </div>

    <dl id="profile-summary-list" class="mt-5 grid gap-4 sm:grid-cols-2" data-revealed="false">
        @foreach (array_values($rows) as $row)
            <div class="grid grid-cols-[auto_1fr] items-center gap-x-3">
                <dt class="contents text-sm text-muted"><x-icon-tile :icon="$row['icon']" size="sm" :index="$loop->index" class="row-span-2" /><span>{{ __('account.summary.'.$row['key']) }}</span></dt>
                <dd class="min-w-0 font-bold break-words text-ink">
                    @if ($row['masked'] !== null)
                        <span data-masked dir="ltr">{{ $row['masked'] }}</span>
                        <span data-full dir="ltr" hidden>{{ $row['full'] }}</span>
                    @else
                        <span @if ($row['key'] !== 'name') dir="ltr" @endif>{{ $row['full'] }}</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</x-card>
