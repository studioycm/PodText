<div data-sp3c-canary-summary>
    <span>{{ __('admin.settings_sp3c.canary.part_summary', ['label' => $label ?? __('admin.settings_sp3c.canary.unlabelled')]) }}</span>

    @if (filled($source ?? null) || filled($attribute ?? null))
        <span>{{ __('admin.settings_sp3c.canary.source_summary', ['source' => $source ?? '—', 'attribute' => $attribute ?? '—']) }}</span>
    @endif

    @if (filled($text ?? null))
        <span>{{ $text }}</span>
    @endif
</div>
