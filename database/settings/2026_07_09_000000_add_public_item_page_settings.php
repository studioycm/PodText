<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['item_page'];

        if ($this->migrator->exists('public_content.item_page')) {
            $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($defaults): array {
                $itemPage = is_object($itemPage) ? (array) $itemPage : $itemPage;
                $itemPage = is_array($itemPage) ? $itemPage : [];

                return [
                    ...$defaults,
                    ...$itemPage,
                    'dates' => [
                        ...$defaults['dates'],
                        ...(is_array($itemPage['dates'] ?? null) ? $itemPage['dates'] : []),
                    ],
                    'badges' => [
                        ...$defaults['badges'],
                        ...(is_array($itemPage['badges'] ?? null) ? $itemPage['badges'] : []),
                    ],
                ];
            });

            return;
        }

        $this->migrator->add('public_content.item_page', $defaults);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.item_page');
    }
};
