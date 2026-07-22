@php
    $summary = app(\App\Support\Settings\CardTemplates\CardTemplatePartSummaryFormatter::class)->summarize([
        'label' => $label ?? null,
        'source' => $source ?? null,
        'attribute' => $attribute ?? null,
        'text' => $text ?? null,
    ]);
@endphp

<div class="flex flex-wrap items-center gap-x-2" data-card-template-part-summary>
    <span>{{ $summary['title'] }}</span>

    @if ($summary['context'] !== null)
        <span aria-hidden="true">&middot;</span>
        <span>{{ $summary['context'] }}</span>
    @endif

    @if ($summary['text'] !== null)
        <span aria-hidden="true">&middot;</span>
        <span>{{ $summary['text'] }}</span>
    @endif
</div>
