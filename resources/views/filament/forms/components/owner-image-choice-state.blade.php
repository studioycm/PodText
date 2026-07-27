<section
    class="min-w-0 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
    data-testid="owner-image-choice-state"
>
    <div class="min-w-0">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ $presentation->ownerKindLabel }} · {{ $presentation->slotLabel }}
        </p>

        @if (filled($presentation->ownerLabel))
            <p class="truncate text-sm text-gray-600 dark:text-gray-300" dir="auto">
                {{ $presentation->ownerLabel }}
            </p>
        @endif
    </div>

    <div class="grid min-w-0 gap-3 lg:grid-cols-3">
        <section
            class="min-w-0 space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800/50"
            data-testid="owner-image-direct-state"
        >
            <h3 class="font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.choice.direct_heading') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
                {{ __("admin.owner_image.choice.direct_states.{$presentation->directState}") }}
            </p>

            @if (is_array($presentation->directMedia))
                <p class="min-w-0 text-gray-700 dark:text-gray-200">
                    <span dir="auto">{{ $presentation->directMedia['label'] }}</span>
                    <span class="block break-all text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                        {{ $presentation->directMedia['reference_key'] }}
                    </span>
                </p>
            @endif
        </section>

        <section
            class="min-w-0 space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800/50"
            data-testid="owner-image-shown-now-state"
        >
            <h3 class="font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.choice.shown_now_heading') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
                {{ __("admin.owner_image.sources.{$presentation->shownNowSource}") }}
            </p>

            @if (is_array($presentation->shownNowMedia))
                <p class="min-w-0 text-gray-700 dark:text-gray-200">
                    <span dir="auto">{{ $presentation->shownNowMedia['label'] }}</span>
                    <span class="block break-all text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                        {{ $presentation->shownNowMedia['reference_key'] }}
                    </span>
                </p>
            @endif
        </section>

        <section
            class="min-w-0 space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800/50"
            data-testid="owner-image-pending-state"
        >
            <h3 class="font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.choice.pending_heading') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
                {{ __("admin.owner_image.choice.pending_states.{$presentation->pendingKind}") }}
            </p>

            @if (is_array($presentation->pendingMedia))
                <p class="min-w-0 text-gray-700 dark:text-gray-200">
                    <span dir="auto">{{ $presentation->pendingMedia['label'] }}</span>
                    <span class="block break-all text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                        {{ $presentation->pendingMedia['reference_key'] }}
                    </span>
                </p>
            @endif
        </section>
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

    @if (filled($presentation->shownNowPreviewUrl))
        <img
            src="{{ $presentation->shownNowPreviewUrl }}"
            alt="{{ __('admin.owner_image.preview_alt_generic') }}"
            width="160"
            height="160"
            loading="lazy"
            class="h-32 w-32 rounded-lg object-contain"
        />
    @endif

    @if (is_array($presentation->pendingMedia))
        @if ($presentation->directState !== 'broken' && filled($presentation->pendingMedia['details_url']))
            <a
                href="{{ $presentation->pendingMedia['details_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                {{ __('admin.owner_image.actions.open_details') }}
            </a>
        @endif
    @elseif (is_array($presentation->directMedia))
        @if (filled($presentation->directMedia['details_url']))
            <a
                href="{{ $presentation->directMedia['details_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                {{ __('admin.owner_image.actions.open_details') }}
            </a>
        @endif
    @endif

    <div
        class="space-y-1 rounded-lg border border-gray-200 p-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
        data-testid="owner-image-commit-boundary"
    >
        <p>
            {{ __('admin.owner_image.choice.commit_boundary.commit', ['action' => $presentation->commitBoundary['commit']]) }}
        </p>
        <p>
            {{ __('admin.owner_image.choice.commit_boundary.cancel', ['action' => $presentation->commitBoundary['cancel']]) }}
        </p>
        <p>
            {{ $presentation->commitBoundary['admission'] }}
        </p>
    </div>

    @if ($presentation->canClearPending || $presentation->canChooseAutomatic)
        <div class="flex flex-wrap gap-2">
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
        </div>
    @endif
</section>
