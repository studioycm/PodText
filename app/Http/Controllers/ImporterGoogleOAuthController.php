<?php

namespace App\Http\Controllers;

use App\Enums\ImportConnectionAuthType;
use App\Filament\Pages\ImporterSettings;
use App\Models\ImportConnection;
use App\Support\Importer\Google\GoogleDriveConnector;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class ImporterGoogleOAuthController extends Controller
{
    public function redirect(ImportConnection $importConnection): RedirectResponse
    {
        abort_unless($importConnection->auth_type === ImportConnectionAuthType::OAuth, 404);

        session(['importer_google_oauth_connection_id' => $importConnection->getKey()]);

        return Socialite::driver('google')
            ->redirectUrl(route('admin.importer.google.callback'))
            ->scopes(GoogleDriveConnector::scopes())
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $connectionId = session()->pull('importer_google_oauth_connection_id');
        $connection = ImportConnection::query()->findOrFail($connectionId);
        $googleUser = Socialite::driver('google')
            ->redirectUrl(route('admin.importer.google.callback'))
            ->user();

        $existingCredentials = $connection->credentials ?? [];
        $expiresIn = (int) ($googleUser->expiresIn ?? 3600);

        $connection->forceFill([
            'credentials' => [
                ...$existingCredentials,
                'access_token' => $googleUser->token,
                'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                'expires_in' => $expiresIn,
                'google_email' => $googleUser->getEmail(),
                'google_user_id' => $googleUser->getId(),
                'refresh_token' => $googleUser->refreshToken ?: data_get($existingCredentials, 'refresh_token'),
                'scope' => implode(' ', GoogleDriveConnector::scopes()),
                'token_type' => 'Bearer',
            ],
        ])->save();

        Notification::make()
            ->success()
            ->title(__('admin.importer.notifications.oauth_connected'))
            ->send();

        return redirect()->to(ImporterSettings::getUrl());
    }
}
