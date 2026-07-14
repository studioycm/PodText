@props([
    'item',
])

@php
    $transcriptions = $item->relationLoaded('transcriptions')
        ? $item->transcriptions
        : collect();
@endphp

@if($transcriptions->isNotEmpty())
    <div
        {{ $attributes->merge(['class' => 'mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300']) }}
        data-test="contributor-item-transcriptions"
    >
        <p class="font-medium text-gray-900 dark:text-gray-100">
            {{ \App\Support\Transcriptions\TranscriptionModeLabel::text('public.labels.contributor_transcriptions') }}
        </p>

        <ul class="mt-2 space-y-1">
            @foreach($transcriptions as $transcription)
                <li class="min-w-0 truncate" data-test="contributor-item-transcription-title">
                    {{ $transcription->title ?: __('public.labels.untitled_transcription') }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
