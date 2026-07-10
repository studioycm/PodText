<?php

namespace App\Filament\Actions;

use App\Support\SettingsLifecycle\PublicSettingsPackage;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ExportPublicSettingsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'exportPublicSettings';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('admin.actions.export_public_settings'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn () => response()->streamDownload(
                function (): void {
                    echo PublicSettingsPackage::fromCurrentSettings()->toJson();
                },
                $this->downloadFilename(),
                ['Content-Type' => 'application/json; charset=UTF-8'],
            ));
    }

    private function downloadFilename(): string
    {
        $app = Str::slug((string) config('app.name', 'podtext'));
        $timestamp = now('Asia/Jerusalem')->format('Ymd-His');

        return "{$app}-public-settings-{$timestamp}.json";
    }
}
