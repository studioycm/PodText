@php
    $path = request()->path();
    $isActive = function (array $item) use ($path): bool {
        if (($item['type'] ?? null) !== 'route') {
            return false;
        }

        $routeKey = $item['route_key'] ?? null;

        return match ($routeKey) {
            'home' => $path === '/',
            'search' => str_starts_with($path, 'search'),
            'podcasts' => str_starts_with($path, 'podcasts'),
            'contributors' => str_starts_with($path, 'contributors'),
            'about' => str_starts_with($path, 'about'),
            default => false,
        };
    };
    $itemsAlignment = in_array($itemsAlignment ?? null, ['start', 'center', 'end'], true) ? $itemsAlignment : 'center';
    $menuAlignmentClass = match ($itemsAlignment) {
        'start' => 'justify-start',
        'end' => 'justify-end',
        default => 'justify-center',
    };
    $logoSizeClass = match ($logo['size'] ?? 'medium') {
        'small' => 'h-8',
        'large' => 'h-14',
        default => 'h-11',
    };
    $logoDisplayMode = $logo['display_mode'] ?? 'image';
    $showLogoImage = in_array($logoDisplayMode, ['image', 'image_text'], true);
    $showLogoText = in_array($logoDisplayMode, ['image_text', 'text'], true);
    $themeMode = $themeSelector['mode'] ?? 'light_dark_system';
    $themeDisplayMode = $themeSelector['display_mode'] ?? 'text_icon';
    $themeOptions = $themeMode === 'light_dark' ? ['light', 'dark'] : ['system', 'light', 'dark'];
@endphp

@if($enabled)
    <header
        class="border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-950/95"
        dir="{{ __('public.meta.dir') }}"
        data-test="public-header"
        x-data="{
            mobileOpen: false,
            theme: 'system',
            init() {
                this.theme = localStorage.getItem('podtext-theme') || 'system';
                this.applyTheme();
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => this.applyTheme());
            },
            setTheme(value) {
                this.theme = value;
                localStorage.setItem('podtext-theme', value);
                this.applyTheme();
            },
            applyTheme() {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', this.theme === 'dark' || (this.theme === 'system' && prefersDark));
            },
        }"
    >
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a
                href="/"
                class="flex min-w-0 shrink-0 items-center gap-3"
                data-test="public-header-logo"
                data-logo-display-mode="{{ $logoDisplayMode }}"
                data-logo-size="{{ $logo['size'] ?? 'medium' }}"
                data-logo-fallback="{{ ($logo['fallback'] ?? false) ? 'true' : 'false' }}"
            >
                @if($showLogoImage)
                    <img
                        src="{{ $logo['light_url'] }}"
                        alt="{{ $logo['alt_text'] }}"
                        class="{{ $logoSizeClass }} w-auto shrink-0 object-contain dark:hidden"
                        loading="eager"
                        data-test="public-header-logo-light"
                    >
                    <img
                        src="{{ $logo['dark_url'] }}"
                        alt="{{ $logo['alt_text'] }}"
                        class="{{ $logoSizeClass }} hidden w-auto shrink-0 object-contain dark:block"
                        loading="eager"
                        data-test="public-header-logo-dark"
                    >
                @endif

                @if($showLogoText)
                    <span class="truncate text-base font-semibold text-gray-950 dark:text-white">
                        {{ $logo['alt_text'] }}
                    </span>
                @else
                    <span class="sr-only">{{ $logo['alt_text'] }}</span>
                @endif
            </a>

            <div
                class="hidden min-w-0 flex-1 items-center gap-3 lg:flex {{ $menuAlignmentClass }}"
                data-test="public-menu-layout"
                data-menu-alignment="{{ $itemsAlignment }}"
            >
                <nav class="flex min-w-0 items-center gap-2" aria-label="{{ __('public.menu.primary_navigation') }}">
                    @foreach($items as $item)
                        @if(in_array($item['type'] ?? null, ['route', 'external_url'], true))
                            <a
                                href="{{ $item['url'] }}"
                                @if(($item['open_in_new_tab'] ?? false) === true) target="_blank" rel="noopener noreferrer" @endif
                                @class([
                                    'rounded-md px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-primary-500',
                                    'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-100' => $isActive($item),
                                    'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-200 dark:hover:bg-gray-900 dark:hover:text-white' => ! $isActive($item),
                                ])
                                data-test="public-menu-item"
                                data-menu-key="{{ $item['key'] }}"
                                data-menu-type="{{ $item['type'] }}"
                            >
                                {{ $item['label'] }}
                            </a>
                        @elseif(($item['type'] ?? null) === 'public_form')
                            <button
                                type="button"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-public-form', { detail: { formKey: @js($item['form_key']) } }))"
                                class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                data-test="public-menu-form-action"
                                data-menu-key="{{ $item['key'] }}"
                                data-form-key="{{ $item['form_key'] }}"
                                data-display-mode="{{ $item['display_mode'] }}"
                            >
                                {{ $item['label'] }}
                            </button>

                        @endif
                    @endforeach
                </nav>

                @if(($search['enabled'] ?? false) && filled($search['url'] ?? null))
                    <form
                        action="{{ $search['url'] }}"
                        method="GET"
                        class="relative min-w-48 max-w-xs"
                        data-test="public-header-search"
                    >
                        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute inset-s-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            name="{{ $search['query_param'] ?? 'q' }}"
                            value="{{ request((string) ($search['query_param'] ?? 'q')) }}"
                            placeholder="{{ $search['placeholder'] }}"
                            class="w-full rounded-full border-gray-300 bg-white py-2 pe-3 ps-9 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            data-test="public-header-search-input"
                        >
                    </form>
                @endif

                @if(($themeSelector['enabled'] ?? true) === true)
                    @if($themeDisplayMode === 'trigger_icon_menu')
                        <div class="relative" x-data="{ themeMenuOpen: false }" data-test="public-theme-selector" data-theme-display-mode="trigger_icon_menu">
                            <button
                                type="button"
                                x-on:click="themeMenuOpen = ! themeMenuOpen"
                                class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                aria-label="{{ __('public.menu.theme') }}"
                                data-test="public-theme-trigger"
                            >
                                <x-heroicon-o-swatch class="size-5" />
                            </button>

                            <div
                                x-show="themeMenuOpen"
                                x-on:click.outside="themeMenuOpen = false"
                                class="absolute inset-e-0 z-20 mt-2 grid min-w-36 gap-1 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-800 dark:bg-gray-950"
                                data-test="public-theme-menu"
                            >
                                @foreach($themeOptions as $themeOption)
                                    <button
                                        type="button"
                                        x-on:click="setTheme(@js($themeOption)); themeMenuOpen = false"
                                        x-bind:class="theme === @js($themeOption) ? 'bg-gray-100 text-gray-950 dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white'"
                                        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition"
                                        data-test="public-theme-option"
                                        data-theme-option="{{ $themeOption }}"
                                    >
                                        @if($themeOption === 'system')
                                            <x-heroicon-o-computer-desktop class="size-4" />
                                        @elseif($themeOption === 'light')
                                            <x-heroicon-o-sun class="size-4" />
                                        @else
                                            <x-heroicon-o-moon class="size-4" />
                                        @endif
                                        <span>{{ __("public.theme.{$themeOption}") }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex items-center rounded-full border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900" data-test="public-theme-selector" data-theme-display-mode="{{ $themeDisplayMode }}">
                            @foreach($themeOptions as $themeOption)
                                <button
                                    type="button"
                                    x-on:click="setTheme(@js($themeOption))"
                                    x-bind:class="theme === @js($themeOption) ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white'"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium transition"
                                    data-test="public-theme-option"
                                    data-theme-option="{{ $themeOption }}"
                                >
                                    @if(in_array($themeDisplayMode, ['text_icon', 'icon'], true))
                                        @if($themeOption === 'system')
                                            <x-heroicon-o-computer-desktop class="size-4" />
                                        @elseif($themeOption === 'light')
                                            <x-heroicon-o-sun class="size-4" />
                                        @else
                                            <x-heroicon-o-moon class="size-4" />
                                        @endif
                                    @endif

                                    @if(in_array($themeDisplayMode, ['text', 'text_icon'], true))
                                        <span>{{ __("public.theme.{$themeOption}") }}</span>
                                    @else
                                        <span class="sr-only">{{ __("public.theme.{$themeOption}") }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            <button
                type="button"
                x-on:click="mobileOpen = ! mobileOpen"
                class="ms-auto inline-flex size-10 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900 lg:hidden"
                aria-label="{{ __('public.menu.toggle_navigation') }}"
                data-test="public-menu-mobile-toggle"
            >
                <x-heroicon-o-bars-3 class="size-5" />
            </button>
        </div>

        <div
            x-show="mobileOpen"
            x-on:click.outside="mobileOpen = false"
            x-on:keydown.escape.window="mobileOpen = false"
            class="border-t border-gray-200 px-4 py-3 dark:border-gray-800 lg:hidden"
            data-test="public-mobile-menu"
        >
            @if(($search['enabled'] ?? false) && filled($search['url'] ?? null))
                <form action="{{ $search['url'] }}" method="GET" class="mb-3" data-test="public-mobile-header-search">
                    <input
                        type="search"
                        name="{{ $search['query_param'] ?? 'q' }}"
                        placeholder="{{ $search['placeholder'] }}"
                        class="w-full rounded-full border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                </form>
            @endif

            <nav class="grid gap-2" aria-label="{{ __('public.menu.primary_navigation') }}">
                @foreach($items as $item)
                    @if(in_array($item['type'] ?? null, ['route', 'external_url'], true))
                        <a
                            href="{{ $item['url'] }}"
                            @if(($item['open_in_new_tab'] ?? false) === true) target="_blank" rel="noopener noreferrer" @endif
                            x-on:click="mobileOpen = false"
                            @class([
                                'rounded-md px-3 py-2 text-sm font-medium transition',
                                'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-100' => $isActive($item),
                                'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-900' => ! $isActive($item),
                            ])
                            data-test="public-mobile-menu-item"
                            data-menu-key="{{ $item['key'] }}"
                            data-menu-type="{{ $item['type'] }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @elseif(($item['type'] ?? null) === 'public_form')
                        <button
                            type="button"
                            x-on:click="mobileOpen = false; window.dispatchEvent(new CustomEvent('open-public-form', { detail: { formKey: @js($item['form_key']) } }))"
                            class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            data-test="public-mobile-menu-form-action"
                            data-menu-key="{{ $item['key'] }}"
                            data-form-key="{{ $item['form_key'] }}"
                        >
                            {{ $item['label'] }}
                        </button>
                    @elseif(($item['type'] ?? null) === 'theme_selector' && ($themeSelector['enabled'] ?? true) === true)
                        <div class="flex items-center rounded-full border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900" data-test="public-mobile-theme-selector" data-theme-display-mode="{{ $themeDisplayMode }}">
                            @foreach($themeOptions as $themeOption)
                                <button
                                    type="button"
                                    x-on:click="setTheme(@js($themeOption))"
                                    x-bind:class="theme === @js($themeOption) ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white'"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full px-2 py-1.5 text-xs font-medium transition"
                                    data-test="public-mobile-theme-option"
                                    data-theme-option="{{ $themeOption }}"
                                >
                                    @if($themeOption === 'system')
                                        <x-heroicon-o-computer-desktop class="size-4" />
                                    @elseif($themeOption === 'light')
                                        <x-heroicon-o-sun class="size-4" />
                                    @else
                                        <x-heroicon-o-moon class="size-4" />
                                    @endif
                                    <span>{{ __("public.theme.{$themeOption}") }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>

        @foreach($formMounts as $formMount)
            <livewire:public.public-form-modal
                :form-key="$formMount['form_key']"
                :display-mode="$formMount['display_mode']"
                :show-trigger="false"
                :key="'public-header-form-'.$formMount['form_key']"
            />
        @endforeach
    </header>
@endif
