<livewire:admin.media-picker-panel
    :purpose="$purpose"
    :selected-ids="$selectedIds"
    :is-multiple="$isMultiple"
    :max-items="$maxItems"
    @insertMedia="callSchemaComponentMethod('{{ $componentKey }}', 'updateState', $event.detail); close()"
/>
