<?php

it('describes live card template renderer behavior without future-renderer copy', function (string $locale, array $staleFragments): void {
    foreach ([
        'card_template_layout',
        'card_template_density',
        'card_template_image_size',
        'card_template_title_size',
        'card_template_part_layout',
        'card_template_part_visible',
    ] as $key) {
        $helper = trans("admin.helpers.{$key}", [], $locale);

        expect($helper)->toBeString()->not->toBe("admin.helpers.{$key}");

        foreach ($staleFragments as $fragment) {
            expect($helper)->not->toContain($fragment);
        }
    }
})->with([
    'english' => ['en', ['future', 'compatibility renderer']],
    'hebrew' => ['he', ['עתידיים', 'עתידי', 'מסלול התאימות']],
]);

it('has retired the dead card_template_part_order keys in both locales', function (string $locale): void {
    expect(trans('admin.fields', [], $locale))->not->toHaveKey('card_template_part_order')
        ->and(trans('admin.helpers', [], $locale))->not->toHaveKey('card_template_part_order');
})->with(['en', 'he']);
