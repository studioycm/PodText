@php
    $summary = app(\App\Support\Settings\CardTemplates\CardTemplatePartSummaryFormatter::class)->summarize([
        'label' => $label ?? null,
        'source' => $source ?? null,
        'attribute' => $attribute ?? null,
        'text' => $text ?? null,
    ]);
@endphp

<div data-sp3c-part-summary>
    <span>{{ $summary['title'] }}</span>

    @if ($summary['context'] !== null)
        <span>{{ $summary['context'] }}</span>
    @endif

    @if ($summary['text'] !== null)
        <span>{{ $summary['text'] }}</span>
    @endif
</div>
