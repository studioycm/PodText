# Rule: URL Fields

## Severity
Medium

## Problem
URL fields like `cta_url`, `website`, or `portfolio_link` that are plain TextInputs with no validation or type hint. The admin can enter "google.com", "not a url", or a phone number and the form will accept it. This leads to broken links in the frontend.

## Detection
- TextInput for fields named `*_url`, `*_link`, `website`, `portfolio`, or similar
- No `->url()` validation rule on URL-like fields
- No prefix or suffix icon hinting that a URL is expected

## Recommendation
Add `->url()` and enforce any application scheme/host allowlist separately.
Use a link icon or translated placeholder when format guidance helps. A visual
`https://` prefix is not submitted as part of the value, so use it only when the
application deliberately stores the remainder and reconstructs the URL. Avoid
reachability checks unless the latency, DNS/network policy, and failure behavior
are explicitly acceptable.

## Example
```php
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

TextInput::make('cta_url')
    ->url()
    ->suffixIcon(Heroicon::Link)
    ->placeholder('https://example.com/page'),

TextInput::make('website')
    ->url()
    ->suffixIcon(Heroicon::Link),
```
