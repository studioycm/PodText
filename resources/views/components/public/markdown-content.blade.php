@props(['markdown'])

@inject('renderer', 'App\Support\Markdown\SafeMarkdownRenderer')

<div {{ $attributes->merge(['class' => $renderer->publicContentClasses()]) }} data-test="public-markdown-content">
    {!! $renderer->toHtml($markdown) !!}
</div>
