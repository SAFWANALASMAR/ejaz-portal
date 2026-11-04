{{--
    رأس الموقع صفّان، وكله ثابت عند التمرير (sticky top:0) بالارتفاع والمقاسات نفسها، ومنفصل عن البطل بتدرج أغمق وحد سفلي #8EC5F0
    وظل (أمر التصور النهائي، النسخة 63 — R2 وR3 وG6، وترتيب صفوان في 2 نوفمبر 2026: docs/decisions/2026-11-02-header-final.md):
    - الصف الأول: رمز الشعار في البداية، ثم المخطوطة، ثم مربع الترخيص بسطريه (من «محتوى الرئيسية»)، ثم «المساعد الذكي» والبحث
      و«تسجيل الدخول» (أو «حسابي») في النهاية. ودون 1240 ثلاثة أعمدة والمخطوطة في منتصف الشريط. ومن 1240 ترتيب المدير التنفيذي
      المعتمد (3 نوفمبر 2026، البند ١٤ — docs/decisions/2026-11-03-director-home.md، وأُلغي التوسيط في البند ١٢، ويحل محل الفراغات
      المتساوية في طلب الدمج 154): الرمز والمخطوطة الممدودة والمربع كتلة واحدة في البداية بفراغات صغيرة، والأزرار في النهاية، والطرفان
      مزاحان قليلاً نحو الداخل، وما بينهما فارغ. ولا سطر إشراف.
    - الصف الثاني: أفتح قليلاً وبينهما فاصل #8EC5F0، فيه عناصر القائمة كبسولات بأيقونة صغيرة بجانب الكلمة (القائمة «ج»)،
      والنشط بحد #8EC5F0 وخلفية أفتح. والقائمة تُدار من اللوحة، وعناصرها السبعة من MainMenuSevenSeeder.
    - اللوحي العمودي: الترتيب نفسه بلا مربع الترخيص وبزر ☰ في أقصى النهاية. والجوال: الرمز والمخطوطة متجاوران في البداية وزر ☰ في النهاية.
    - صف القائمة ظاهر من 1024 (lg:) على الحاسوب واللوحي الأفقي بعناصره وأيقوناتها، ولا يحل محله زر ☰ (إضافة توجيه المدير، 3 نوفمبر
      2026، البند ٣): لا تتسع (عناصر أكثر أو أسماء أطول أو تكبير المتصفح) فيصغر الخط ثم ينكمش (app.js وconcept.css). $siteMenu من App\View\Composers\SiteChromeComposer،
    والأيقونة من مسار الرابط (App\Support\MenuIcon).
--}}
@php
    $menu = $siteMenu ?? collect();
    $header = fn (string $key): string => \App\Support\HomeContent::text('header', $key);
@endphp

<header data-site-header class="site-header sticky top-0 z-40 text-white">
    {{-- الصف الأول: دون 1024 المخطوطة في منتصف الشاشة، ومن 1024 الرمز والمخطوطة والمربع كتلة في البداية والأزرار في النهاية في حاوية صف القائمة (concept.css — .header-top) --}}
    <div class="header-top">
        {{-- الاسم المقروء في المخطوطة، فرابط الرمز خارج ترتيب لوحة المفاتيح وقارئ الشاشة: رابطان إلى الرئيسية لا يتكرران --}}
        <a href="{{ route('home') }}" wire:navigate class="header-mark" tabindex="-1" aria-hidden="true" aria-label="{{ __('site.name') }}">
            <img src="{{ \App\Support\HomeContent::brand('mark_white') }}" alt="" width="495" height="505" data-brand-mark class="masthead-mark w-auto">
        </a>

        <a href="{{ route('home') }}" wire:navigate class="header-calligraphy">
            <img src="{{ \App\Support\HomeContent::brand('calligraphy_white') }}" alt="{{ __('site.name') }}" data-logo-calligraphy
                width="1218" height="185" class="masthead-calligraphy w-auto object-contain">
        </a>

        <div class="header-end">
            {{-- مربع الترخيص: بخلفية الشريط نفسها وحد رفيع، سطران يُعدَّلان من «محتوى الرئيسية» --}}
            <p data-header-licence class="header-licence hidden lg:flex">
                <span class="font-extrabold">{{ $header('licensed_by') }}</span>
                @if (filled($licenseNumber = \App\Support\MissingText::shown($header('license_number'))))
                    <span class="text-brand-light">{{ $licenseNumber }}</span>
                @endif
            </p>

            <div class="header-actions">
                {{-- «المساعد الذكي» (البند ١٣ من إضافة توجيه المدير): يفتح المساعد القائم (partials/assistant — resources/js/assistant.js)، ويعمل
                     دليله بلا مزوّد. ويختفي بإطفاء المساعد من «إعدادات المساعد». وأيقونته Phosphor duotone بمربع الهوية (x-icon-tile).
                     ومن 1024 وحده، فرأس الجوال واللوحي العمودي كما كان، وهو لهما داخل قائمة ☰.
                     وهو مطفأ يبقى ظاهراً معطّلاً «قيد التفعيل» حتى يُشغَّل (قرار صفوان، 4 نوفمبر 2026) --}}
                @if ($assistantOn = \App\Models\AssistantSettings::current()->is_enabled)
                    <button type="button" data-assistant-toggle data-header-assistant aria-controls="assistant-panel" aria-expanded="false"
                        class="header-icon-button hidden lg:inline-flex" aria-label="{{ __('assistant.header.open') }}" title="{{ __('assistant.header.open') }}">
                        <x-icon-tile icon="sparkles" size="xs" />
                    </button>
                @else
                    <button type="button" data-header-assistant data-assistant-soon aria-disabled="true"
                        class="header-icon-button hidden cursor-not-allowed opacity-60 lg:inline-flex" aria-label="{{ __('assistant.header.soon') }}" title="{{ __('assistant.header.soon') }}">
                        <x-icon-tile icon="sparkles" size="xs" />
                    </button>
                @endif

                {{-- البحث السريع (partials/search-palette): نافذة بـ Ctrl+K أو بالضغط، وبجانبه الدخول --}}
                <button type="button" data-search-open
                    class="header-icon-button" aria-label="{{ __('ui.search.open') }}" aria-keyshortcuts="Control+K Meta+K" title="{{ __('ui.search.open') }} ({{ __('ui.search.shortcut') }})">
                    {{ \App\Support\Icon::svg('magnifying-glass', 'size-[19px]', ['aria-hidden' => 'true']) }}
                </button>

                {{-- على الجوال يكفي الشعار وزر القائمة، والدخول داخل القائمة --}}
                <div class="hidden sm:block">
                    @auth
                        <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill">
                            <span data-icon-circle class="inline-flex rounded-full">{{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}</span>
                            {{ __('site.my_account') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="login-pill">
                            <span data-icon-circle class="inline-flex rounded-full">{{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}</span>
                            {{ __('site.login') }}
                        </a>
                    @endauth
                </div>

                <button type="button" data-toggle="mobile-menu" aria-expanded="false" aria-controls="mobile-menu"
                    class="header-icon-button lg:hidden">
                    <span class="sr-only">{{ __('site.open_menu') }}</span>
                    {{ \App\Support\Icon::svg('bars-3', 'size-6', ['aria-hidden' => 'true']) }}
                </button>
            </div>
        </div>
    </div>

    {{-- الصف الثاني: القائمة «ج»، وشارة البث في آخره (مراجعة المشرف على طلب الدمج 130: لا تزيح المخطوطة عن منتصف الصف الأول) --}}
    <nav data-main-nav class="header-nav hidden lg:block" aria-label="{{ __('site.main_navigation') }}">
        <div class="header-nav-row mx-auto max-w-[82.5rem] px-4 sm:px-7">
        <ul class="nav-capsules">
            @foreach ($menu as $item)
                @php($current = $item->isCurrent() || $item->hasCurrentChild())
                <li class="relative min-w-0" @if ($item->children->isNotEmpty()) data-nav-dropdown @endif>
                    @if ($item->children->isNotEmpty())
                        {{-- تفتح بمرور الفأرة بمهلة قصيرة، وبالضغط واللمس ولوحة المفاتيح (app.js — ملاحظات المدير، 1 نوفمبر، البند ٣).
                             وأولها الصفحة الأم ثم أقسامها (MenuSections، البند ٤) --}}
                        <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}"
                            @if ($current) data-current @endif
                            class="nav-capsule">
                            <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />
                            <span class="truncate">{{ $item->label }}</span>
                            {{ \App\Support\Icon::svg('chevron-down', 'size-3 shrink-0 opacity-80') }}
                        </button>
                        <ul id="menu-{{ $item->id }}" hidden data-dropdown-menu class="nav-dropdown absolute start-0 top-full z-50 min-w-56 rounded-2xl border border-line/70 bg-pale p-2 text-ink shadow-xl">
                            @foreach ($item->children as $child)
                                <li>
                                    <a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate @endif
                                        @if ($child->isParent ? $child->isExactlyCurrent() : $child->isCurrent()) aria-current="page" @endif
                                        @class(['block rounded-xl px-4 py-2.5 text-ink hover:bg-sky-light/60 hover:text-primary-dark focus-visible:bg-sky-light/60 aria-[current=page]:font-bold aria-[current=page]:text-primary-dark', 'mb-1 border-b border-line/60 font-extrabold' => $child->isParent])>{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif
                            @if ($current) aria-current="page" @endif
                            class="nav-capsule">
                            <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />
                            <span class="truncate">{{ $item->label }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
        @include('partials.live-badge')
        </div>
    </nav>

    {{-- قائمة الجوال واللوحي: العناصر نفسها بأيقوناتها، وشارة البث أولها --}}
    <div id="mobile-menu" hidden class="header-mobile-menu lg:hidden">
        <nav class="container-page max-h-[75vh] overflow-y-auto py-3" aria-label="{{ __('site.main_navigation') }}">
            @include('partials.live-badge', ['class' => 'mb-3'])
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    @php($current = $item->isCurrent() || $item->hasCurrentChild())
                    <li>
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="mobile-menu-{{ $item->id }}"
                                @if ($current) data-current @endif
                                class="mobile-nav-link flex w-full items-center justify-between">
                                <span class="flex items-center gap-2.5"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />{{ $item->label }}</span>
                                {{ \App\Support\Icon::svg('chevron-down', 'size-4') }}
                            </button>
                            <ul id="mobile-menu-{{ $item->id }}" hidden class="ms-4 space-y-1 border-s-2 border-brand-light/50 ps-3">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate @endif
                                        @if ($child->isParent ? $child->isExactlyCurrent() : $child->isCurrent()) aria-current="page" @endif
                                        class="block rounded-xl px-4 py-2.5 text-white/90 hover:bg-white/10 aria-[current=page]:font-bold aria-[current=page]:text-brand-light">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif @if ($current) aria-current="page" @endif
                                class="mobile-nav-link flex items-center gap-2.5"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />{{ $item->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- «المساعد الذكي» للجوال واللوحي العمودي داخل القائمة (البند ١٣ من إضافة توجيه المدير) --}}
            @if ($assistantOn)
                <button type="button" data-assistant-toggle data-menu-assistant aria-controls="assistant-panel" aria-expanded="false"
                    class="mobile-nav-link mt-1 flex w-full items-center gap-2.5">
                    <x-icon-tile icon="sparkles" size="xs" />{{ __('assistant.header.open') }}
                </button>
            @else
                <button type="button" data-menu-assistant data-assistant-soon aria-disabled="true"
                    class="mobile-nav-link mt-1 flex w-full cursor-not-allowed items-center gap-2.5 opacity-60">
                    <x-icon-tile icon="sparkles" size="xs" />{{ __('assistant.header.soon') }}
                </button>
            @endif

            <div class="mt-3 border-t border-white/10 pt-3 sm:hidden">
                @auth
                    <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill w-full justify-center">
                        {{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}
                        {{ __('site.my_account') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="login-pill w-full justify-center">
                        {{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}
                        {{ __('site.login') }}
                    </a>
                @endauth
            </div>
        </nav>
    </div>
</header>
