@props(['markdown'])

@inject('renderer', 'App\Support\Markdown\SafeMarkdownRenderer')

<div {{ $attributes->merge(['class' => 'space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-3xl dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_h2]:text-white dark:[&_h3]:text-white']) }}>
    {!! $renderer->toHtml($markdown) !!}
</div>
