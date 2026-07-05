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
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="/" class="flex min-w-0 items-center gap-3" data-test="public-header-logo">
                <img
                    src="{{ asset('images/podtext-logo.jpg') }}"
                    alt="{{ __('app.name') }}"
                    class="h-11 w-auto shrink-0 rounded-sm object-contain"
                >
                <span class="sr-only">{{ __('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-2 lg:flex" aria-label="{{ __('public.menu.primary_navigation') }}">
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
                    @elseif(($item['type'] ?? null) === 'theme_selector' && ($themeSelector['enabled'] ?? true) === true)
                        <div class="flex items-center rounded-md border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900" data-test="public-theme-selector">
                            @foreach(['system', 'light', 'dark'] as $themeOption)
                                <button
                                    type="button"
                                    x-on:click="setTheme(@js($themeOption))"
                                    x-bind:class="theme === @js($themeOption) ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white'"
                                    class="rounded px-2 py-1 text-xs font-medium transition"
                                    data-test="public-theme-option"
                                    data-theme-option="{{ $themeOption }}"
                                >
                                    {{ __("public.theme.{$themeOption}") }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>

            <button
                type="button"
                x-on:click="mobileOpen = ! mobileOpen"
                class="inline-flex size-10 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900 lg:hidden"
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
                        <div class="flex items-center rounded-md border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900" data-test="public-mobile-theme-selector">
                            @foreach(['system', 'light', 'dark'] as $themeOption)
                                <button
                                    type="button"
                                    x-on:click="setTheme(@js($themeOption))"
                                    x-bind:class="theme === @js($themeOption) ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white'"
                                    class="flex-1 rounded px-2 py-1 text-xs font-medium transition"
                                    data-test="public-mobile-theme-option"
                                    data-theme-option="{{ $themeOption }}"
                                >
                                    {{ __("public.theme.{$themeOption}") }}
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
