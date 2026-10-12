{{--
    «تواصل معنا»: بيانات التواصل من «إعدادات الموقع» (ما لم يُدخل لا يظهر، ولا خريطة مضمّنة)، ونموذج
    «الاستفسارات والاقتراحات». لا تُخزَّن الصفحة، لأن فيها رمز الحماية وختم الوقت (ContactController).
--}}
@php
    use App\Enums\InquiryType;
    use App\Rules\SafeUrl;

    $email = is_string($contact['email'] ?? null) ? $contact['email'] : null;
    $phone = is_string($contact['phone'] ?? null) ? $contact['phone'] : null;
    $address = is_string($contact['address'] ?? null) ? $contact['address'] : null;
    $map = is_string($contact['map_url'] ?? null) && SafeUrl::isSafe($contact['map_url'], allowInternal: false) ? $contact['map_url'] : null;
    $hasInfo = filled($email) || filled($phone) || filled($address) || $map !== null;
    // بلا بيانات تبقى البطاقة بعلامة 【ناقص】 في التطوير، ولا تظهر فارغة للزوار في الإنتاج
    $showInfo = $hasInfo || ! app()->isProduction();
@endphp

<x-layouts.app :title="__('contact.title')">
    <x-page-hero :title="__('contact.title')" />
    <x-page-intro slug="contact" />
    <x-page-intro slug="inquiries" />

    <section class="py-10 sm:py-14">
        <div @class(['container-page grid gap-8 lg:items-start', 'lg:grid-cols-[1fr_2fr]' => $showInfo])>
            @if ($showInfo)
            <aside class="rounded-2xl border border-line/70 bg-surface p-6" aria-labelledby="contact-info">
                <h2 id="contact-info" class="text-xl font-bold text-primary">{{ __('contact.info.title') }}</h2>

                @if ($hasInfo)
                    <dl class="mt-5 space-y-4">
                        @if (filled($email))
                            <div>
                                <dt class="text-sm text-muted">{{ __('contact.info.email') }}</dt>
                                <dd class="mt-1 font-semibold"><a href="mailto:{{ $email }}" dir="ltr" class="text-primary hover:underline">{{ $email }}</a></dd>
                            </div>
                        @endif
                        @if (filled($phone))
                            <div>
                                <dt class="text-sm text-muted">{{ __('contact.info.phone') }}</dt>
                                <dd class="mt-1 font-semibold"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" dir="ltr" class="text-primary hover:underline">{{ $phone }}</a></dd>
                            </div>
                        @endif
                        @if (filled($address))
                            <div>
                                <dt class="text-sm text-muted">{{ __('contact.info.address') }}</dt>
                                <dd class="mt-1 leading-7 text-ink">{{ $address }}</dd>
                            </div>
                        @endif
                        @if ($map !== null)
                            <div>
                                <a href="{{ $map }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">
                                    {{ svg('heroicon-o-map-pin', 'size-5 text-accent', ['aria-hidden' => 'true']) }}
                                    {{ __('contact.info.map') }}
                                    <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                                </a>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="mt-4"><x-missing :what="__('contact.info.missing')" /></p>
                @endif
            </aside>
            @endif

            <div class="rounded-2xl border border-line/70 bg-white p-6 shadow-xs sm:p-8">
                <h2 class="text-xl font-bold text-primary">{{ __('contact.form.title') }}</h2>
                <p class="mt-2 text-muted">{{ __('contact.form.intro') }}</p>

                <x-flash class="mt-6" />

                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-5" novalidate>
                    @csrf
                    <input type="hidden" name="form_token" value="{{ $token }}">

                    {{-- فخ للروبوتات: مخفي عن العين (sr-only)، وعن القارئات الصوتية (aria-hidden) ولوحة المفاتيح --}}
                    <div class="sr-only" aria-hidden="true">
                        <label for="contact-extra">{{ __('contact.form.website') }}</label>
                        <input id="contact-extra" name="{{ \App\Http\Requests\ContactRequest::TRAP }}" type="text" tabindex="-1" autocomplete="off">
                    </div>

                    @error('form_token')
                        <x-alert type="error">{{ $message }}</x-alert>
                    @enderror

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="name" :label="__('contact.form.name')" icon="user" required maxlength="150" autocomplete="name" :value="$user?->name" />
                        <x-field name="email" type="email" :label="__('contact.form.email')" icon="envelope" dir="ltr" required maxlength="255" autocomplete="email" :value="$user?->email" />
                        <x-field name="phone" type="tel" :label="__('contact.form.phone')" icon="phone" dir="ltr" :hint="__('contact.form.phone_hint')" maxlength="20" autocomplete="tel" :value="$user?->phone" />
                        <x-field name="type" type="select" :label="__('contact.form.type')" required>
                            @foreach (InquiryType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(old('type', InquiryType::Inquiry->value) === $type->value)>{{ $type->getLabel() }}</option>
                            @endforeach
                        </x-field>
                    </div>

                    <x-field name="subject" :label="__('contact.form.subject')" required maxlength="200" />
                    <x-field name="message" type="textarea" :label="__('contact.form.message')" required maxlength="5000" rows="7" />

                    {{-- غرض الجمع عند نقطة الجمع دائماً، والرابط متى وُجدت صفحة سياسة الخصوصية (كالتذييل) --}}
                    <p class="text-sm text-muted">
                        {{ __('contact.form.privacy') }}
                        @if (Route::has('privacy'))
                            {{ __('contact.form.privacy_more') }}
                            <a href="{{ route('privacy') }}" class="text-primary underline decoration-accent underline-offset-4">{{ __('contact.form.privacy_link') }}</a>.
                        @endif
                    </p>

                    <x-button>{{ __('contact.form.submit') }}</x-button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
