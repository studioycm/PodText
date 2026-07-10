@php
    $rawHtmlOverride = $maintenance['raw_html_override'] ?? null;
@endphp

@if (filled($rawHtmlOverride))
    {!! $rawHtmlOverride !!}
@else
    @php
        $title = $maintenance['title'] ?? null;
        $richHtml = $maintenance['rich_html'] ?? null;
    @endphp

    <!doctype html>
    <html lang="he" dir="rtl">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, follow">
            <title>{{ $title ?: __('public.maintenance.title') }}</title>
            <style>
                :root {
                    color-scheme: light dark;
                    --maintenance-bg: #f7f7f4;
                    --maintenance-panel: #ffffff;
                    --maintenance-text: #1f2933;
                    --maintenance-muted: #5f6c72;
                    --maintenance-border: #d7d7ce;
                }

                @media (prefers-color-scheme: dark) {
                    :root {
                        --maintenance-bg: #101314;
                        --maintenance-panel: #181c1e;
                        --maintenance-text: #eef1ef;
                        --maintenance-muted: #b8c1c1;
                        --maintenance-border: #343a3d;
                    }
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    min-height: 100vh;
                    margin: 0;
                    display: grid;
                    place-items: center;
                    background: var(--maintenance-bg);
                    color: var(--maintenance-text);
                    font-family: "Varela Round", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    line-height: 1.7;
                }

                main {
                    width: min(92vw, 42rem);
                    padding: clamp(1.5rem, 5vw, 3rem);
                    border: 1px solid var(--maintenance-border);
                    border-radius: 0.5rem;
                    background: var(--maintenance-panel);
                    box-shadow: 0 18px 60px rgb(0 0 0 / 10%);
                    text-align: center;
                }

                h1 {
                    margin: 0 0 1rem;
                    font-size: clamp(1.75rem, 6vw, 3rem);
                    line-height: 1.15;
                    letter-spacing: 0;
                }

                .maintenance-content {
                    color: var(--maintenance-muted);
                    font-size: clamp(1rem, 2.5vw, 1.125rem);
                }

                .maintenance-content > :first-child {
                    margin-top: 0;
                }

                .maintenance-content > :last-child {
                    margin-bottom: 0;
                }
            </style>
        </head>
        <body>
            <main>
                @if (filled($title))
                    <h1>{{ $title }}</h1>
                @endif

                <div class="maintenance-content" data-maintenance-content>
                    @if (filled($richHtml))
                        {!! $richHtml !!}
                    @else
                        <p>{{ __('public.maintenance.body') }}</p>
                    @endif
                </div>
            </main>
        </body>
    </html>
@endif
