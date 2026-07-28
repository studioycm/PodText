@php
    $cardStateText = static fn (string $text): string => rtrim($text, '.');
    $choiceCards = [
        [
            'testid' => 'owner-image-shown-now-state',
            'label' => __('admin.owner_image.choice.shown_now_heading'),
            'state' => $cardStateText(__("admin.owner_image.sources.{$presentation->shownNowSource}")),
            'media' => $presentation->shownNowMedia,
            'thumb' => $presentation->shownNowPreviewUrl ?? ($presentation->shownNowMedia['preview_url'] ?? null),
            'pending' => false,
            'state_suffix' => $presentation->pendingFallbackSource !== null
                ? __('admin.owner_image.choice.shown_after_save', [
                    'source' => $cardStateText(__("admin.owner_image.sources.{$presentation->pendingFallbackSource}")),
                ])
                : null,
            'state_suffix_class' => 'text-danger-600 dark:text-danger-400',
            'details_url' => null,
            'restore' => false,
            'automatic' => false,
        ],
        [
            'testid' => 'owner-image-direct-state',
            'label' => __('admin.owner_image.choice.direct_heading'),
            'state' => $cardStateText(__("admin.owner_image.choice.direct_states.{$presentation->directState}")),
            'media' => $presentation->directMedia,
            'thumb' => $presentation->directMedia['preview_url'] ?? null,
            'pending' => false,
            'state_suffix' => $presentation->pendingKind === 'automatic_fallback'
                ? __('admin.owner_image.choice.direct_removal_pending')
                : null,
            'state_suffix_class' => 'text-danger-600 dark:text-danger-400',
            'details_url' => $presentation->directMedia['details_url'] ?? null,
            'restore' => false,
            'automatic' => $presentation->canChooseAutomatic,
        ],
        [
            'testid' => 'owner-image-pending-state',
            'label' => __('admin.owner_image.choice.pending_heading'),
            'state' => $cardStateText(__("admin.owner_image.choice.pending_states.{$presentation->pendingKind}")),
            'media' => $presentation->pendingMedia,
            'thumb' => $presentation->directState !== 'broken'
                ? ($presentation->pendingMedia['preview_url'] ?? null)
                : null,
            'pending' => $presentation->pendingKind !== 'unchanged',
            'state_suffix' => null,
            'state_suffix_class' => null,
            'thumb_fallback' => $presentation->directState !== 'broken'
                ? $presentation->pendingFallbackPreviewUrl
                : null,
            'details_url' => $presentation->directState !== 'broken'
                ? ($presentation->pendingMedia['details_url'] ?? null)
                : null,
            'restore' => $presentation->canClearPending,
            'automatic' => false,
        ],
    ];
@endphp

<section
    class="min-w-0 space-y-1.5 rounded-xl border border-gray-200 p-2 dark:border-gray-700"
    data-testid="owner-image-choice-state"
>
    @if (filled($presentation->ownerLabel))
        <p
            class="truncate text-sm font-semibold text-gray-950 dark:text-white"
            dir="auto"
            title="{{ $presentation->ownerLabel }}"
        >
            {{ $presentation->ownerLabel }}
        </p>
    @endif

    <div class="flex flex-wrap items-stretch gap-2">
        @foreach ($choiceCards as $choiceCard)
            <div
                @class([
                    'flex min-w-0 items-center gap-2 rounded-lg border px-2.5 py-1.5',
                    'border-gray-200 dark:border-gray-700' => ! $choiceCard['pending'],
                    'border-warning-400 dark:border-warning-500' => $choiceCard['pending'],
                ])
                data-testid="{{ $choiceCard['testid'] }}"
            >
                @if (is_array($choiceCard['media']))
                    <button
                        type="button"
                        class="flex min-w-0 items-center gap-2 text-start"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard?.writeText(@js($choiceCard['media']['label'])); copied = true; setTimeout(() => copied = false, 1500)"
                        title="{{ $choiceCard['media']['label'] }} — {{ __('admin.owner_image.actions.copy_filename') }}"
                        aria-label="{{ __('admin.owner_image.actions.copy_filename') }}: {{ $choiceCard['media']['label'] }}"
                    >
                        @php $cardThumb = $choiceCard['thumb'] ?? ($choiceCard['thumb_fallback'] ?? null); @endphp
                        @if (filled($cardThumb))
                            <img
                                src="{{ $cardThumb }}"
                                alt=""
                                width="56"
                                height="56"
                                loading="lazy"
                                class="h-14 w-14 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700"
                            />
                        @else
                            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-md border border-gray-200 text-gray-400 dark:border-gray-700">—</span>
                        @endif
                        <span class="grid min-w-0 gap-0.5">
                            <span @class([
                                'text-[10px] leading-none',
                                'text-gray-500 dark:text-gray-400' => ! $choiceCard['pending'],
                                'text-warning-700 dark:text-warning-300' => $choiceCard['pending'],
                            ])>{{ $choiceCard['label'] }}</span>
                            <span
                                @class([
                                    'text-xs',
                                    'truncate' => ! $choiceCard['pending'],
                                    'max-w-72 whitespace-normal' => $choiceCard['pending'],
                                    'text-gray-700 dark:text-gray-200' => ! $choiceCard['pending'],
                                    'text-warning-700 dark:text-warning-300' => $choiceCard['pending'],
                                ])
                            >
                                <span x-show="! copied">{{ $choiceCard['state'] }}@if (filled($choiceCard['state_suffix'] ?? null)) <span class="{{ $choiceCard['state_suffix_class'] ?? 'text-danger-600 dark:text-danger-400' }}">· {{ $choiceCard['state_suffix'] }}</span>@endif</span>
                                <span x-cloak x-show="copied">{{ __('admin.owner_image.copy_success') }}</span>
                            </span>
                        </span>
                    </button>
                @else
                    <span class="flex min-w-0 items-center gap-2">
                        @php $cardThumb = $choiceCard['thumb'] ?? ($choiceCard['thumb_fallback'] ?? null); @endphp
                        @if (filled($cardThumb))
                            <img
                                src="{{ $cardThumb }}"
                                alt=""
                                width="56"
                                height="56"
                                loading="lazy"
                                class="h-14 w-14 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700"
                            />
                        @else
                            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-md border border-gray-200 text-gray-400 dark:border-gray-700">—</span>
                        @endif
                        <span class="grid min-w-0 gap-0.5">
                            <span @class([
                                'text-[10px] leading-none',
                                'text-gray-500 dark:text-gray-400' => ! $choiceCard['pending'],
                                'text-warning-700 dark:text-warning-300' => $choiceCard['pending'],
                            ])>{{ $choiceCard['label'] }}</span>
                            <span @class([
                                'text-xs',
                                'truncate' => ! $choiceCard['pending'],
                                'max-w-72 whitespace-normal' => $choiceCard['pending'],
                                'text-gray-700 dark:text-gray-200' => ! $choiceCard['pending'],
                                'text-warning-700 dark:text-warning-300' => $choiceCard['pending'],
                            ])>{{ $choiceCard['state'] }}@if (filled($choiceCard['state_suffix'] ?? null)) <span class="{{ $choiceCard['state_suffix_class'] ?? 'text-danger-600 dark:text-danger-400' }}">· {{ $choiceCard['state_suffix'] }}</span>@endif</span>
                        </span>
                    </span>
                @endif

                @if (filled($choiceCard['details_url']) || $choiceCard['restore'] || $choiceCard['automatic'])
                    <span class="flex items-center gap-1">
                        @if (filled($choiceCard['details_url']) && is_array($choiceCard['media']))
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-md border border-gray-300 text-gray-500 hover:text-primary-600 dark:border-gray-600 dark:text-gray-400 dark:hover:text-primary-400"
                                x-on:click="$dispatch('open-media-details', { id: @js($choiceCard['media']['id']) })"
                                data-media-details-id="{{ $choiceCard['media']['id'] }}"
                                title="{{ __('admin.owner_image.actions.open_details') }}"
                                aria-label="{{ __('admin.owner_image.actions.open_details') }}"
                            >
                                <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" />
                            </button>
                        @endif

                        @if ($choiceCard['restore'])
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-md border border-danger-400 text-danger-600 hover:text-danger-500 dark:border-danger-500 dark:text-danger-400"
                                wire:click="callSchemaComponentMethod('{{ $field->getKey() }}', 'restoreSavedOwnerSelection', [[]])"
                                data-testid="owner-image-restore-saved"
                                title="{{ __('admin.owner_image.actions.restore_saved') }}"
                                aria-label="{{ __('admin.owner_image.actions.restore_saved') }}"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                            </button>
                        @endif

                        @if ($choiceCard['automatic'])
                            {{ $field->getAction('removeDirectOwnerImage') }}
                        @endif
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    @if ($presentation->directState === 'broken')
        <div
            role="status"
            class="space-y-2 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100"
            data-testid="owner-image-broken-state"
        >
            <p class="font-semibold">
                {{ __('admin.owner_image.broken_configured_heading') }}
            </p>

            <p>
                {{ __('admin.owner_image.broken_configured_body') }}
            </p>

            @if (filled($presentation->savedReferenceKey))
                <p class="min-w-0">
                    <span class="font-medium">{{ __('admin.owner_image.broken_configured_reference') }}:</span>
                    <span class="break-all" dir="ltr">{{ $presentation->savedReferenceKey }}</span>
                </p>
            @endif

            @if (filled($presentation->expectedLegacyPath))
                <p class="min-w-0">
                    <span class="font-medium">{{ __('admin.owner_image.broken_configured_path') }}:</span>
                    <span class="break-all" dir="ltr">{{ $presentation->expectedLegacyPath }}</span>
                </p>
            @endif

            @if (blank($presentation->savedReferenceKey) && blank($presentation->expectedLegacyPath))
                <p>
                    {{ __('admin.owner_image.broken_configured_evidence_hidden') }}
                </p>
            @endif
        </div>
    @endif

    <p
        class="text-[11px] leading-snug text-gray-600 dark:text-gray-300"
        data-testid="owner-image-commit-boundary"
    >
        <span>{{ __('admin.owner_image.choice.commit_boundary.commit', ['action' => $presentation->commitBoundary['commit']]) }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ __('admin.owner_image.choice.commit_boundary.cancel', ['action' => $presentation->commitBoundary['cancel']]) }}</span>
    </p>
</section>
