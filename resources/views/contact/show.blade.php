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
    // الرابط المدخل صراحة، وإلا المولّد من العنوان الوطني المختصر (NationalAddress)
    $map = is_string($contact['map_url'] ?? null) && SafeUrl::isSafe($contact['map_url'], allowInternal: false)
        ? $contact['map_url']
        : \App\Support\NationalAddress::mapUrl(is_string($contact['short_address'] ?? null) ? $contact['short_address'] : null);
    // زر واتساب من رقمه في الإعدادات (البيانات الرسمية، 26 أكتوبر 2026)
    $whatsapp = \App\Support\WhatsApp::link(is_string($contact['whatsapp'] ?? null) ? $contact['whatsapp'] : null);
    $hasInfo = filled($email) || filled($phone) || filled($address) || $map !== null || $whatsapp !== null;
    // بلا بيانات تبقى البطاقة بعلامة 【ناقص】 في التطوير، ولا تظهر فارغة للزوار في الإنتاج
    $showInfo = $hasInfo || \App\Support\MissingText::visible();
@endphp

<x-layouts.app :title="__('contact.title')">
    {{-- نص الرأس المعتمد بفقرتيه (إضافة توجيه المدير، البند ٣٠)، وما نُشر من «محرر الصفحات» يغلبه --}}
    <x-page-hero :title="__('contact.title')" :summary="__('contact.hero_text')" />
    <x-page-sections />
    {{-- مقدمة صفحة «تواصل معنا» من الموقع السابق (Page::INTROS): إن كان نصها في الرأس (فقرته الأولى نفسها — البند ٣٠) لم تتكرر هنا،
         ونص آخر يظهر كما كان (مراجعة المشرف على #157). وكذلك مقدمة «الاستفسارات والاقتراحات» وهي الفقرة الثانية في الرأس (ملاحظات
         المدير التنفيذي، 4 نوفمبر 2026) --}}
    <x-page-intro slug="contact" :except="__('contact.hero_text')" />
    <x-page-intro slug="inquiries" :except="__('contact.hero_text')" />

    <section class="py-10 sm:py-14">
        <div @class(['container-page grid gap-8 lg:items-start', 'lg:grid-cols-[1fr_2fr]' => $showInfo])>
            @if ($showInfo)
            {{-- بلغة التصور النهائي (Final-Contact): كل وسيلة بطاقة بمربع أيقونة متدرج بلونه من موضعه --}}
            <aside aria-labelledby="contact-info">
                <h2 id="contact-info" class="text-2xl font-black text-primary-dark">{{ __('contact.info.title') }}</h2>

                @if ($hasInfo)
                    <dl class="mt-5 space-y-3.5">
                        @if (filled($email))
                            <div class="tone-card group grid grid-cols-[auto_1fr] items-center gap-x-4 px-5 py-4" data-card>
                                <dt class="contents text-sm"><x-icon-tile icon="envelope" class="row-span-2" /><span class="tone-card-muted">{{ __('contact.info.email') }}</span></dt>
                                <dd class="mt-0.5 min-w-0 text-lg font-black"><a href="mailto:{{ $email }}" dir="ltr" class="hover:underline">{{ $email }}</a></dd>
                            </div>
                        @endif
                        @if (filled($phone))
                            <div class="tone-card group grid grid-cols-[auto_1fr] items-center gap-x-4 px-5 py-4" data-card>
                                <dt class="contents text-sm"><x-icon-tile icon="phone" class="row-span-2" /><span class="tone-card-muted">{{ __('contact.info.phone') }}</span></dt>
                                <dd class="mt-0.5 min-w-0 text-lg font-black"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" dir="ltr" class="hover:underline">{{ $phone }}</a></dd>
                            </div>
                        @endif
                        @if (filled($address))
                            <div class="tone-card group grid grid-cols-[auto_1fr] items-center gap-x-4 px-5 py-4" data-card>
                                <dt class="contents text-sm"><x-icon-tile icon="map-pin" class="row-span-2" /><span class="tone-card-muted">{{ __('contact.info.address') }}</span></dt>
                                <dd class="mt-0.5 min-w-0 leading-7 font-black">{{ $address }}</dd>
                            </div>
                        @endif
                    </dl>
                    {{-- رابط الخريطة خارج قائمة التعريف: لا تحوي إلا أزواج dt وdd (axe: definition-list) --}}
                    @if ($map !== null)
                        <p class="mt-4">
                            <a href="{{ $map }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">
                                {{ \App\Support\Icon::svg('map-pin', 'size-5 text-accent', ['aria-hidden' => 'true']) }}
                                {{ __('contact.info.map') }}
                                @if (\App\Support\NationalAddress::normalize($contact['short_address'] ?? null))
                                    <bdi dir="ltr" class="font-normal text-muted">{{ \App\Support\NationalAddress::normalize($contact['short_address']) }}</bdi>
                                @endif
                                <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </p>
                    @endif
                    @if ($whatsapp !== null)
                        <p class="mt-4">
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" data-whatsapp
                                class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2 font-semibold text-white transition hover:bg-secondary">
                                <x-social-icon network="whatsapp" class="size-5" />
                                {{ __('contact.info.whatsapp') }}
                                <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </p>
                    @endif
                    {{-- رقم الترخيص الذي كان تحت «تواصل عبر الواتساب» حُذف (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٣٠)، ويبقى في مربع
                         الترخيص في الرأس --}}
                @else
                    <p class="mt-4"><x-missing :what="__('contact.info.missing')" /></p>
                @endif
            </aside>
            @endif

            <div id="contact-form" class="form-sky scroll-mt-48 p-6 sm:p-8" data-contact-form>
                <h2 class="section-heading-title flex items-center gap-2.5 font-black leading-tight text-primary-dark"><x-icon-tile icon="chat-bubble-left-right" shade="medium" size="sm" class="section-heading-icon" data-loop />{{ __('contact.form.title') }}</h2>
                <p class="mt-2 text-muted">{{ __('contact.form.intro') }}</p>

                <x-flash class="mt-6" />

                {{-- لوحة النجاح مكان النموذج (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٣): بارزة، فيها «تم تسجيل طلبك» ورقم الطلب،
                     ويُنقل إليها التركيز وتُعلَن (role="status"). يملؤها contact-form.js بعد الإرسال بلا إعادة تحميل، وبلا سكربت تأتي
                     بعد التحويل برقم الطلب من الجلسة --}}
                @php($sentReference = session('inquiry_reference'))
                <div data-contact-success role="status" aria-live="polite" tabindex="-1" @unless ($sentReference) hidden @endunless
                    class="contact-success mt-6">
                    <span class="contact-success-icon" aria-hidden="true">{{ \App\Support\Icon::svg('check-circle', 'size-9') }}</span>
                    <h3 class="text-2xl font-black">{{ __('contact.success.title') }}</h3>
                    <p data-contact-success-text class="text-lg leading-8">{{ $sentReference ? __('contact.success.registered', ['reference' => $sentReference]) : '' }}</p>
                    <p class="text-sm font-bold">{{ __('contact.success.reference') }}: <bdi dir="ltr" data-contact-reference class="contact-success-reference">{{ $sentReference }}</bdi></p>
                    <a href="{{ route('contact') }}" class="tone-pill mt-1 self-start" data-contact-another>{{ __('contact.success.another') }}</a>
                </div>

                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-5" novalidate data-async-form
                    data-sending="{{ __('contact.success.sending') }}" data-failed="{{ __('contact.success.failed') }}" data-expired="{{ __('contact.success.expired') }}" data-too-many="{{ __('contact.success.too_many') }}" @if ($sentReference) hidden @endif>
                    @csrf
                    <input type="hidden" name="form_token" value="{{ $token }}">

                    {{-- أخطاء الإرسال بلا إعادة تحميل (contact-form.js)، وبجانبها خطأ كل حقل تحته --}}
                    <div data-form-errors role="alert" tabindex="-1" hidden class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"></div>

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

                    {{-- غرض الجمع عند نقطة الجمع دائماً، والرابط متى نُشرت صفحة سياسة الخصوصية (كالتذييل) --}}
                    <p class="text-sm text-muted">
                        {{ __('contact.form.privacy') }}
                        @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
                            {{ __('contact.form.privacy_more') }}
                            <a href="{{ route('privacy') }}" wire:navigate class="text-primary underline decoration-accent underline-offset-4">{{ __('contact.form.privacy_link') }}</a>.
                        @endif
                    </p>

                    <x-button>{{ __('contact.form.submit') }}</x-button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
