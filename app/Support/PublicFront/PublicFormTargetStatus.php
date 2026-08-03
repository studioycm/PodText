<?php

namespace App\Support\PublicFront;

use App\Models\HomepageSection;
use App\Support\PublicFront\Sections\PublicDisplaySectionRegistry;

/**
 * Admin-side observability for public-form CTA targets. Public renderers
 * silently skip CTAs whose form key has no enabled definition; this class
 * surfaces that state to editors without changing the public skip behavior.
 *
 * Scoped per request: definitions and counts are memoized for the request's
 * life, so the warnings widget's canView() + render pair computes once. A
 * same-request settings write must forgetInstance() to recount (the test
 * helpers do; production settings writes answer on their own request).
 */
class PublicFormTargetStatus
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    /** @var array{menu_items: int, about_blocks: int, homepage_buttons: int, total: int}|null */
    private ?array $misconfiguredCounts = null;

    public function __construct(
        private readonly PublicFrontConfigReader $configReader = new PublicFrontConfigReader,
    ) {}

    /**
     * Registry-default form keys plus configured definitions, labelled with
     * their public-rendering status so editors can spot dead targets while
     * picking one.
     *
     * @return array<string, string>
     */
    public function selectOptions(): array
    {
        $definitions = $this->definitions();

        $options = collect(PublicFrontConfigRegistry::defaultMenuItems())
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'public_form' && filled($item['form_key'] ?? null))
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['form_key'] => (string) ($item['label'] ?? $item['form_key']),
            ]);

        foreach ($definitions as $key => $definition) {
            $options->put($key, (string) ($definition['name'] ?? $key));
        }

        return $options
            ->map(function (string $label, string $key) use ($definitions): string {
                if (! isset($definitions[$key])) {
                    return $label.' '.__('admin.labels.public_form_target_missing_suffix');
                }

                if (($definitions[$key]['enabled'] ?? false) !== true) {
                    return $label.' '.__('admin.labels.public_form_target_disabled_suffix');
                }

                return $label;
            })
            ->all();
    }

    public function hasEnabledDefinition(?string $key): bool
    {
        if (blank($key)) {
            return false;
        }

        return ($this->definitions()[$key]['enabled'] ?? false) === true;
    }

    public function warningFor(?string $key): ?string
    {
        if (blank($key) || $this->hasEnabledDefinition($key)) {
            return null;
        }

        return isset($this->definitions()[$key])
            ? __('admin.labels.public_form_target_warning_disabled')
            : __('admin.labels.public_form_target_warning_missing');
    }

    /**
     * Counts the admin-configured CTAs the public side would silently skip:
     * visible public-form menu items, visible about-page form CTA blocks, and
     * visible homepage content-block buttons whose form key has no enabled
     * definition.
     *
     * @return array{menu_items: int, about_blocks: int, homepage_buttons: int, total: int}
     */
    public function misconfiguredCounts(): array
    {
        if ($this->misconfiguredCounts !== null) {
            return $this->misconfiguredCounts;
        }

        $menuItems = collect($this->configReader->group('menu_config')['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && ($item['type'] ?? null) === 'public_form'
                && ($item['visible'] ?? true) === true
                && ! $this->hasEnabledDefinition($this->stringOrNull($item['form_key'] ?? null)))
            ->count();

        $aboutBlocks = collect($this->configReader->group('about_page')['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block)
                && ($block['type'] ?? null) === 'form_cta'
                && ($block['visible'] ?? true) === true
                && ! $this->hasEnabledDefinition($this->stringOrNull($block['form_key'] ?? null)))
            ->count();

        $homepageButtons = HomepageSection::query()
            ->visible()
            ->get()
            ->filter(fn (HomepageSection $section): bool => ($section->sourceConfig()['source_type'] ?? null) === PublicDisplaySectionRegistry::CONTENT_BLOCK)
            ->filter(function (HomepageSection $section): bool {
                $formKey = $this->stringOrNull($section->displayConfig()['button_form_key'] ?? null);

                return filled($formKey) && ! $this->hasEnabledDefinition($formKey);
            })
            ->count();

        return $this->misconfiguredCounts = [
            'menu_items' => $menuItems,
            'about_blocks' => $aboutBlocks,
            'homepage_buttons' => $homepageButtons,
            'total' => $menuItems + $aboutBlocks + $homepageButtons,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return $this->definitions ??= collect($this->configReader->group('public_forms')['definitions'] ?? [])
            ->filter(fn (mixed $definition): bool => is_array($definition) && filled($definition['key'] ?? null))
            ->mapWithKeys(fn (array $definition): array => [(string) $definition['key'] => $definition])
            ->all();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
