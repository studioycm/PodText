<div class="space-y-4" data-testid="media-details-summary">
    @if ($summary['needs_attention'])
        <div
            class="border-warning-300 bg-warning-50 text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100 rounded-xl border p-4"
            role="status"
            data-testid="media-details-issue-summary"
        >
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="font-semibold">{{ __('admin.media_issue_review.details.needs_attention') }}</p>
                    <p class="mt-1 text-sm break-words">
                        {{ $summary['primary_issue'] }}
                        @if ($summary['additional_issue_count'] > 0)
                            · {{
                                trans_choice(
                                    'admin.media_library.additional_issue_count',
                                    $summary['additional_issue_count'],
                                    ['count' => $summary['additional_issue_count']],
                                )
                            }}
                        @endif
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    :href="$reviewUrl"
                    color="warning"
                    :icon="$reviewIcon"
                    data-testid="media-details-review-issues"
                >
                    {{ __('admin.media_issue_review.review_issues') }}
                </x-filament::button>
            </div>
        </div>
    @else
        <div
            class="border-success-300 bg-success-50 text-success-900 dark:border-success-700 dark:bg-success-950 dark:text-success-100 rounded-xl border p-4 text-sm font-medium"
            role="status"
            data-testid="media-details-ready"
        >
            {{ __('admin.media_issue_review.details.ready') }}
        </div>
    @endif

    <x-filament::section>
        <x-slot name="heading">{{ __('admin.media_issue_review.details.identity_heading') }}</x-slot>

        <x-slot name="description">{{ __('admin.media_issue_review.details.identity_description') }}</x-slot>

        <div class="grid min-w-0 gap-5 md:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
            @if (filled($summary['preview_url']))
                <img
                    src="{{ $summary['preview_url'] }}"
                    alt="{{ __('admin.media_issue_review.details.preview_alt', ['identity' => $summary['identity']]) }}"
                    class="h-52 w-full rounded-lg bg-gray-50 object-contain dark:bg-gray-900"
                    data-testid="media-details-preview"
                />
            @else
                <div
                    class="flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    role="img"
                    aria-label="{{ __('admin.media_issue_review.details.preview_unavailable') }}"
                    data-testid="media-details-preview-unavailable"
                >
                    {{ __('admin.media_issue_review.details.preview_unavailable') }}
                </div>
            @endif

            <div class="min-w-0">
                <h3 class="text-lg font-semibold break-words text-gray-950 dark:text-white">
                    {{ $summary['identity'] }}
                </h3>

                <dl class="mt-4 grid min-w-0 gap-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        'original_filename' => __('admin.owner_image.metadata.original_filename'),
                        'stored_filename' => __('admin.owner_image.metadata.stored_filename'),
                        'reference_key' => __('admin.owner_image.metadata.reference_key'),
                        'mime' => __('admin.owner_image.metadata.mime'),
                        'dimensions' => __('admin.owner_image.metadata.dimensions'),
                        'file_size' => __('admin.owner_image.metadata.file_size'),
                        'disk' => __('admin.owner_image.metadata.disk'),
                        'path' => __('admin.media_issue_review.facts.path'),
                    ] as $key => $label)
                        <div class="min-w-0">
                            <dt class="font-medium text-gray-700 dark:text-gray-200">{{ $label }}</dt>
                            <dd class="mt-1 break-all text-gray-600 dark:text-gray-300" dir="ltr">
                                {{ filled($summary[$key]) ? $summary[$key] : __('admin.media_issue_review.facts.unavailable') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </x-filament::section>
</div>
