<x-filament-panels::page>
    <div class="space-y-6" data-testid="media-issue-review">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('admin.media_issue_review.details.identity_heading') }}
            </x-slot>

            <x-slot name="description">
                {{ __('admin.media_issue_review.details.identity_description') }}
            </x-slot>

            <div class="grid min-w-0 gap-5 md:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
                @if (filled($review['preview_url']))
                    <img
                        src="{{ $review['preview_url'] }}"
                        alt="{{ __('admin.media_issue_review.details.preview_alt', ['identity' => $review['identity']]) }}"
                        class="h-52 w-full rounded-lg bg-gray-50 object-contain dark:bg-gray-900"
                        data-testid="media-issue-preview"
                    >
                @else
                    <div
                        class="flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        role="img"
                        aria-label="{{ __('admin.media_issue_review.details.preview_unavailable') }}"
                    >
                        {{ __('admin.media_issue_review.details.preview_unavailable') }}
                    </div>
                @endif

                <div class="min-w-0">
                    <h2 class="break-words text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $review['identity'] }}
                    </h2>

                    <dl class="mt-4 grid min-w-0 gap-3 text-sm sm:grid-cols-2">
                        @foreach ([
                            'original_filename' => __('admin.owner_image.metadata.original_filename'),
                            'stored_filename' => __('admin.owner_image.metadata.stored_filename'),
                            'reference_key' => __('admin.owner_image.metadata.reference_key'),
                            'disk' => __('admin.owner_image.metadata.disk'),
                            'path' => __('admin.media_issue_review.facts.path'),
                        ] as $key => $label)
                            <div class="min-w-0">
                                <dt class="font-medium text-gray-700 dark:text-gray-200">{{ $label }}</dt>
                                <dd class="mt-1 break-all text-gray-600 dark:text-gray-300" dir="ltr">
                                    {{ filled($review[$key]) ? $review[$key] : __('admin.media_issue_review.facts.unavailable') }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </x-filament::section>

        @if ($this->sanitizeResult !== null)
            <div
                class="rounded-xl border border-success-300 bg-success-50 p-4 text-success-900 dark:border-success-700 dark:bg-success-950 dark:text-success-100"
                role="status"
                data-testid="media-sanitize-result"
            >
                <div class="flex flex-wrap gap-2 text-sm font-medium">
                    @foreach ($this->sanitizeResult['closed'] as $label)
                        <span class="rounded-full border border-success-500 px-2.5 py-0.5">
                            {{ __('admin.media_issue_review.resolution.closed', ['label' => $label]) }}
                        </span>
                    @endforeach
                    @foreach ($this->sanitizeResult['remaining'] as $label)
                        <span class="rounded-full border border-gray-400 px-2.5 py-0.5 text-gray-700 dark:text-gray-200">
                            {{ __('admin.media_issue_review.resolution.remaining', ['label' => $label]) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->sanitizeRefused)
            <div
                class="rounded-xl border border-warning-300 bg-warning-50 p-5 text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100"
                role="alert"
                data-testid="media-sanitize-refusal"
            >
                <h2 class="font-semibold">{{ __('admin.media_issue_review.sanitize.refusal_title') }}</h2>
                <p class="mt-2 text-sm">{{ __('admin.media_issue_review.sanitize.refusal_body') }}</p>
                <p class="mt-2 text-sm">{{ __('admin.media_issue_review.sanitize.refusal_routes') }}</p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <x-filament::button
                        color="warning"
                        icon="heroicon-o-arrows-right-left"
                        wire:click="mountAction('swapFile')"
                        data-testid="media-sanitize-refusal-swap"
                    >
                        {{ __('admin.media_library.swap') }}
                    </x-filament::button>
                    <x-filament::button
                        color="danger"
                        icon="heroicon-o-trash"
                        wire:click="mountAction('deleteFile')"
                        data-testid="media-sanitize-refusal-delete"
                    >
                        {{ __('admin.media_library.delete_permanently') }}
                    </x-filament::button>
                </div>
            </div>
        @endif

        @if ($review['issues'] !== [])
            <section aria-labelledby="media-issue-summary-heading" class="space-y-4">
                <div
                    class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100"
                    role="status"
                >
                    <h2 id="media-issue-summary-heading" class="font-semibold">
                        {{ __('admin.media_issue_review.issues_heading') }}
                    </h2>
                    <p class="mt-1 text-sm">
                        {{ trans_choice(
                            'admin.media_issue_review.issue_count',
                            count($review['issues']),
                            ['count' => count($review['issues'])],
                        ) }}
                    </p>
                </div>

                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    @foreach ($review['issues'] as $issue)
                        <article
                            class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                            data-testid="media-issue-reason-{{ $issue['value'] }}"
                        >
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $issue['label'] }}
                            </h3>

                            <dl class="mt-4 space-y-4 text-sm">
                                <div>
                                    <dt class="font-semibold text-gray-800 dark:text-gray-100">
                                        {{ __('admin.media_issue_review.cause') }}
                                    </dt>
                                    <dd class="mt-1 text-gray-600 dark:text-gray-300">{{ $issue['cause'] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-800 dark:text-gray-100">
                                        {{ __('admin.media_issue_review.consequence') }}
                                    </dt>
                                    <dd class="mt-1 text-gray-600 dark:text-gray-300">{{ $issue['consequence'] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-800 dark:text-gray-100">
                                        {{ __('admin.media_issue_review.evidence_limit') }}
                                    </dt>
                                    <dd class="mt-1 text-gray-600 dark:text-gray-300">{{ $issue['evidence_limit'] }}</dd>
                                </div>
                            </dl>

                            <h4 class="mt-5 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                {{ __('admin.media_issue_review.technical_facts') }}
                            </h4>
                            <dl class="mt-2 grid min-w-0 gap-2 text-sm sm:grid-cols-2">
                                @foreach ($issue['facts'] as $fact)
                                    <div class="min-w-0 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                        <dt class="font-medium text-gray-700 dark:text-gray-200">{{ $fact['label'] }}</dt>
                                        <dd class="mt-1 break-all text-gray-600 dark:text-gray-300" dir="ltr">
                                            {{ $fact['value'] }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($issue['resolution']['kind'] === 'action')
                                <div
                                    class="mt-5 border-t border-dashed border-gray-200 pt-4 dark:border-gray-700"
                                    data-testid="media-issue-resolution-action-{{ $issue['value'] }}"
                                >
                                    <x-filament::button
                                        color="warning"
                                        icon="heroicon-o-sparkles"
                                        wire:click="mountAction('sanitizeFile')"
                                        data-testid="media-sanitize-trigger"
                                    >
                                        {{ $issue['resolution']['label'] }}
                                    </x-filament::button>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $issue['resolution']['description'] }}
                                    </p>
                                </div>
                            @elseif ($issue['resolution']['kind'] === 'blocked')
                                <p
                                    class="mt-5 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200"
                                    data-testid="media-issue-resolution-blocked-{{ $issue['value'] }}"
                                >
                                    {{ $issue['resolution']['reason'] }}
                                </p>
                            @elseif ($issue['resolution']['kind'] === 'separate_phase')
                                <p
                                    class="mt-5 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300"
                                    data-testid="media-issue-resolution-separate-{{ $issue['value'] }}"
                                >
                                    {{ $issue['resolution']['description'] }}
                                </p>
                            @elseif ($review['issues_have_resolution_content'])
                                <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('admin.media_issue_review.resolution.no_action') }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @else
            <div
                class="rounded-xl border border-success-300 bg-success-50 p-4 text-success-900 dark:border-success-700 dark:bg-success-950 dark:text-success-100"
                role="status"
            >
                {{ __('admin.media_issue_review.no_current_issues') }}
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ __('admin.media_issue_review.impact.heading') }}
            </x-slot>

            <x-slot name="description">
                {{ __('admin.media_issue_review.impact.description') }}
            </x-slot>

            <div class="grid min-w-0 gap-6 lg:grid-cols-2">
                <section aria-labelledby="canonical-owners-heading" class="min-w-0">
                    <h3 id="canonical-owners-heading" class="font-semibold text-gray-950 dark:text-white">
                        {{ __('admin.media_issue_review.impact.canonical_owners') }}
                    </h3>

                    @if ($review['owners'] !== [])
                        <ul class="mt-3 space-y-3">
                            @foreach ($review['owners'] as $owner)
                                <li class="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <p class="break-words text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $owner['label'] }}
                                    </p>

                                    @if (filled($owner['action_url']))
                                        <x-filament::button
                                            tag="a"
                                            :href="$owner['action_url']"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            size="sm"
                                            color="gray"
                                            class="mt-3"
                                            data-testid="media-issue-owner-route"
                                        >
                                            {{ $owner['action_label'] }}
                                        </x-filament::button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-3 text-sm text-warning-800 dark:text-warning-200">
                            {{ __('admin.media_issue_review.owners.presentation_only') }}
                        </p>
                    @else
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('admin.media_issue_review.impact.no_canonical_owners') }}
                        </p>
                    @endif

                    @if ($review['unresolved_attachment_count'] > 0)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ trans_choice(
                                'admin.media_issue_review.impact.unresolved_attachments',
                                $review['unresolved_attachment_count'],
                                ['count' => $review['unresolved_attachment_count']],
                            ) }}
                        </p>
                    @endif
                </section>

                <section aria-labelledby="other-evidence-heading" class="min-w-0">
                    <h3 id="other-evidence-heading" class="font-semibold text-gray-950 dark:text-white">
                        {{ __('admin.media_issue_review.impact.other_references') }}
                    </h3>

                    @if ($review['non_attachment_references'] !== [])
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($review['non_attachment_references'] as $reference)
                                <li class="break-words">{{ $reference }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('admin.media_issue_review.impact.no_other_references') }}
                        </p>
                    @endif
                </section>
            </div>

            <p class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
                {{ __('admin.media_issue_review.impact.evidence_limits') }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                {{ __('admin.media_issue_review.files.heading') }}
            </x-slot>

            <div class="flex flex-wrap gap-3">
                @if (filled($review['view_url']))
                    <x-filament::button
                        tag="a"
                        :href="$review['view_url']"
                        target="_blank"
                        rel="noopener noreferrer"
                        color="gray"
                        :icon="$icons['view']"
                    >
                        {{ __('admin.media_issue_review.files.view') }}
                    </x-filament::button>
                @endif

                @if (filled($review['download_url']))
                    <x-filament::button
                        tag="a"
                        :href="$review['download_url']"
                        color="gray"
                        :icon="$icons['download']"
                    >
                        {{ __('admin.media_issue_review.files.download') }}
                    </x-filament::button>
                @endif
            </div>

            @if (! filled($review['view_url']) && ! filled($review['download_url']))
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin.media_issue_review.files.none') }}
                </p>
            @endif

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                {{ __('admin.media_issue_review.files.non_repair') }}
            </p>
        </x-filament::section>

        @if ($review['issues'] !== [] && ! $review['issues_have_resolution_content'])
            <div
                class="rounded-xl border border-danger-300 bg-danger-50 p-5 text-danger-950 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100"
                role="alert"
                data-testid="media-issue-blocker"
            >
                <h2 class="font-semibold">{{ __('admin.media_issue_review.blocker.heading') }}</h2>
                <p class="mt-2 text-sm">{{ __('admin.media_issue_review.blocker.body') }}</p>
            </div>
        @endif

        <nav
            aria-label="{{ __('admin.media_issue_review.actions.navigation') }}"
            class="flex flex-wrap items-center gap-3"
            data-testid="media-issue-navigation"
        >
            <x-filament::button
                tag="a"
                :href="$detailsUrl"
                color="gray"
                data-testid="media-issue-close"
            >
                {{ __('admin.media_issue_review.actions.close') }}
            </x-filament::button>

            @if (filled($nextUrl))
                <x-filament::button
                    tag="a"
                    :href="$nextUrl"
                    :icon="$icons['next']"
                    data-testid="media-issue-next"
                >
                    {{ __('admin.media_issue_review.actions.next') }}
                </x-filament::button>
            @else
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin.media_issue_review.end_of_cohort') }}
                </span>
            @endif

            <x-filament::button
                tag="a"
                :href="$returnUrl"
                color="gray"
                :icon="$icons['return']"
                data-testid="media-issue-return"
            >
                {{ __('admin.media_issue_review.actions.return') }}
            </x-filament::button>
        </nav>
    </div>
</x-filament-panels::page>
