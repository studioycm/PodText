<section
    class="min-w-0 space-y-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700"
    data-testid="owner-image-choice-state"
>
    <div class="flex min-w-0 items-start gap-3">
        @if (filled($presentation->shownNowPreviewUrl))
            <img
                src="{{ $presentation->shownNowPreviewUrl }}"
                alt="{{ __('admin.owner_image.preview_alt_generic') }}"
                width="80"
                height="80"
                loading="lazy"
                class="h-20 w-20 shrink-0 rounded-lg border border-gray-200 object-contain dark:border-gray-700"
            />
        @endif

        <div class="min-w-0 flex-1 space-y-1.5 text-sm">
            <p class="min-w-0 font-semibold text-gray-950 dark:text-white">
                {{ $presentation->ownerKindLabel }} · {{ $presentation->slotLabel }}
                @if (filled($presentation->ownerLabel))
                    <span class="font-normal text-gray-600 dark:text-gray-300">— <span dir="auto">{{ $presentation->ownerLabel }}</span></span>
                @endif
            </p>

            <p
                class="min-w-0 text-gray-600 dark:text-gray-300"
                data-testid="owner-image-direct-state"
            >
                <span class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.choice.direct_heading') }}:</span>
                {{ __("admin.owner_image.choice.direct_states.{$presentation->directState}") }}
                @if (is_array($presentation->directMedia))
                    <span dir="auto">{{ $presentation->directMedia['label'] }}</span>
                    <span class="break-all text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $presentation->directMedia['reference_key'] }}</span>
                @endif
            </p>

            <p
                class="min-w-0 text-gray-600 dark:text-gray-300"
                data-testid="owner-image-shown-now-state"
            >
                <span class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.choice.shown_now_heading') }}:</span>
                {{ __("admin.owner_image.sources.{$presentation->shownNowSource}") }}
                @if (is_array($presentation->shownNowMedia))
                    <span dir="auto">{{ $presentation->shownNowMedia['label'] }}</span>
                    <span class="break-all text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $presentation->shownNowMedia['reference_key'] }}</span>
                @endif
            </p>

            <p
                @class([
                    'min-w-0',
                    'text-gray-600 dark:text-gray-300' => $presentation->pendingKind === 'unchanged',
                    'text-warning-700 dark:text-warning-300' => $presentation->pendingKind !== 'unchanged',
                ])
                data-testid="owner-image-pending-state"
            >
                <span class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.choice.pending_heading') }}:</span>
                {{ __("admin.owner_image.choice.pending_states.{$presentation->pendingKind}") }}
                @if (is_array($presentation->pendingMedia))
                    <span dir="auto">{{ $presentation->pendingMedia['label'] }}</span>
                    <span class="break-all text-xs" dir="ltr">{{ $presentation->pendingMedia['reference_key'] }}</span>
                @endif
            </p>
        </div>
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

    <div
        class="space-y-1 border-t border-dashed border-gray-200 pt-2 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
        data-testid="owner-image-commit-boundary"
    >
        <p>
            {{ __('admin.owner_image.choice.commit_boundary.commit', ['action' => $presentation->commitBoundary['commit']]) }}
        </p>
        <p>
            {{ __('admin.owner_image.choice.commit_boundary.cancel', ['action' => $presentation->commitBoundary['cancel']]) }}
        </p>
    </div>

    @php
        $choiceDetailsUrl = null;

        if (is_array($presentation->pendingMedia)) {
            $choiceDetailsUrl = $presentation->directState !== 'broken'
                ? ($presentation->pendingMedia['details_url'] ?? null)
                : null;
        } elseif (is_array($presentation->directMedia)) {
            $choiceDetailsUrl = $presentation->directMedia['details_url'] ?? null;
        }
    @endphp

    @if (filled($choiceDetailsUrl) || $presentation->canClearPending || $presentation->canChooseAutomatic)
        <div class="flex flex-wrap items-center gap-2">
            @if ($presentation->canClearPending)
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="callSchemaComponentMethod('{{ $field->getKey() }}', 'restoreSavedOwnerSelection', [[]])"
                    data-testid="owner-image-restore-saved"
                >
                    {{ __('admin.actions.restore') }}
                </x-filament::button>
            @endif

            @if ($presentation->canChooseAutomatic)
                <x-filament::button
                    type="button"
                    color="gray"
                    outlined
                    wire:click="callSchemaComponentMethod('{{ $field->getKey() }}', 'chooseAutomaticOwnerImage', [[]])"
                    data-testid="owner-image-choose-automatic"
                >
                    {{ __('admin.owner_image.actions.use_automatic_image') }}
                </x-filament::button>
            @endif

            @if (filled($choiceDetailsUrl))
                <a
                    href="{{ $choiceDetailsUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                >
                    {{ __('admin.owner_image.actions.open_details') }}
                </a>
            @endif
        </div>
    @endif
</section>
