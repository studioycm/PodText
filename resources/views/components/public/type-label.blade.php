@props(['label'])

<span {{ $attributes->merge(['class' => 'inline-flex w-fit items-center rounded-md bg-primary-100 px-2.5 py-1 text-xs font-semibold text-primary-900 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-200 dark:ring-primary-400/30']) }}>
    {{ $label }}
</span>
