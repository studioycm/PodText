@inject('renderer', 'App\Support\PublicFront\About\PublicAboutPageRenderer')

@php
    $settings = $aboutPage['settings'] ?? [];
    $teamRendered = false;
@endphp

<x-filament-panels::page>
    <div class="space-y-10" dir="{{ __('public.meta.dir') }}" data-test="about-page">
        <header class="space-y-3">
            @if(filled($aboutPage['kicker'] ?? null))
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400" data-test="about-kicker">
                    {{ $aboutPage['kicker'] }}
                </p>
            @endif

            <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white" data-test="about-title">
                {{ $aboutPage['title'] }}
            </h1>

            @if(filled($aboutPage['description'] ?? null))
                <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300" data-test="about-description">
                    {{ $aboutPage['description'] }}
                </p>
            @endif
        </header>

        <div class="space-y-8">
            @foreach($blocks as $block)
                @switch($block['type'])
                    @case('heading')
                        <section class="space-y-2" data-test="about-block" data-block-type="heading" data-block-key="{{ $block['key'] }}">
                            <h2 @class([
                                'font-semibold tracking-normal text-gray-950 dark:text-white',
                                'text-2xl' => ($block['style'] ?? 'default') !== 'muted',
                                'text-xl' => ($block['style'] ?? 'default') === 'muted',
                            ])>
                                {{ $block['heading'] }}
                            </h2>

                            @if(filled($block['body'] ?? null))
                                <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                                    {{ $block['body'] }}
                                </p>
                            @endif
                        </section>
                        @break

                    @case('markdown')
                        <section class="space-y-3" data-test="about-block" data-block-type="markdown" data-block-key="{{ $block['key'] }}">
                            @if(filled($block['heading'] ?? null))
                                <h2 class="text-2xl font-semibold tracking-normal text-gray-950 dark:text-white">
                                    {{ $block['heading'] }}
                                </h2>
                            @endif

                            <div class="space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-3xl dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_h2]:text-white dark:[&_h3]:text-white">
                                {!! $block['html'] ?? '' !!}
                            </div>
                        </section>
                        @break

                    @case('rich_content')
                        <section class="space-y-3" data-test="about-block" data-block-type="rich_content" data-block-key="{{ $block['key'] }}">
                            @if(filled($block['heading'] ?? null))
                                <h2 class="text-2xl font-semibold tracking-normal text-gray-950 dark:text-white">
                                    {{ $block['heading'] }}
                                </h2>
                            @endif

                            <div class="space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-3xl dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_h2]:text-white dark:[&_h3]:text-white">
                                {!! $block['html'] ?? '' !!}
                            </div>
                        </section>
                        @break

                    @case('image')
                        @php
                            $imageUrl = $renderer->imageUrl($block['image_path'] ?? null);
                        @endphp

                        @if($imageUrl)
                            <figure class="space-y-3" data-test="about-block" data-block-type="image" data-block-key="{{ $block['key'] }}">
                                <img
                                    src="{{ $imageUrl }}"
                                    alt="{{ $block['image_alt'] ?? '' }}"
                                    class="w-full rounded-lg object-cover"
                                    loading="lazy"
                                    data-test="about-image"
                                >

                                @if(filled($block['body'] ?? null))
                                    <figcaption class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                                        {{ $block['body'] }}
                                    </figcaption>
                                @endif
                            </figure>
                        @endif
                        @break

                    @case('callout')
                        <section
                            @class([
                                'space-y-2 rounded-lg border p-5',
                                'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900' => ($block['style'] ?? 'default') !== 'accent',
                                'border-primary-200 bg-primary-50 dark:border-primary-900 dark:bg-primary-950' => ($block['style'] ?? 'default') === 'accent',
                            ])
                            data-test="about-block"
                            data-block-type="callout"
                            data-block-key="{{ $block['key'] }}"
                        >
                            @if(filled($block['heading'] ?? null))
                                <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                                    {{ $block['heading'] }}
                                </h2>
                            @endif

                            @if(filled($block['html'] ?? null))
                                <div class="space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 dark:text-gray-300 dark:[&_a]:text-primary-300">
                                    {!! $block['html'] !!}
                                </div>
                            @endif
                        </section>
                        @break

                    @case('form_cta')
                        @if($renderer->hasEnabledForm($block['form_key'] ?? null))
                            <section class="space-y-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" data-test="about-block" data-block-type="form_cta" data-block-key="{{ $block['key'] }}">
                                @if(filled($block['heading'] ?? null))
                                    <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                                        {{ $block['heading'] }}
                                    </h2>
                                @endif

                                @if(filled($block['body'] ?? null))
                                    <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                                        {{ $block['body'] }}
                                    </p>
                                @endif

                                <button
                                    type="button"
                                    x-data="{}"
                                    x-on:click="window.dispatchEvent(new CustomEvent('open-public-form', { detail: { formKey: @js($block['form_key']) } }))"
                                    class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    data-test="about-form-cta"
                                    data-form-key="{{ $block['form_key'] }}"
                                >
                                    {{ $block['button_label'] }}
                                </button>
                            </section>
                        @endif
                        @break

                    @case('team_section')
                        @if($teamProfiles !== [])
                            <x-public.about.team-section
                                :profiles="$teamProfiles"
                                :settings="$settings"
                                :block="$block"
                            />

                            @php
                                $teamRendered = true;
                            @endphp
                        @endif
                        @break
                @endswitch
            @endforeach

            @if(! $teamRendered && $teamProfiles !== [])
                <x-public.about.team-section
                    :profiles="$teamProfiles"
                    :settings="$settings"
                />
            @endif
        </div>

        @foreach($formCtas as $formCta)
            <livewire:public.public-form-modal
                :form-key="$formCta['form_key']"
                :display-mode="$formCta['display_mode']"
                :show-trigger="false"
                :key="'about-form-modal-'.$formCta['form_key']"
            />
        @endforeach
    </div>
</x-filament-panels::page>
