<?php

use App\Http\Controllers\ContentImagesExportDownloadController;
use App\Http\Controllers\ImporterGoogleOAuthController;
use App\Http\Controllers\MaintenanceFormSubmissionController;
use App\Http\Controllers\SettingsBackupSnapshotFileController;
use App\Http\Controllers\SettingsBackupSnapshotRetryController;
use App\Http\Controllers\SettingsBackupSnapshotsZipController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::post('/maintenance/form', MaintenanceFormSubmissionController::class)
    ->name('public.maintenance-form.submit');

Route::middleware(Authenticate::class)->group(function (): void {
    Route::get('/admin/importer/google/{importConnection}/redirect', [ImporterGoogleOAuthController::class, 'redirect'])
        ->name('admin.importer.google.redirect');

    Route::get('/admin/importer/google/callback', [ImporterGoogleOAuthController::class, 'callback'])
        ->name('admin.importer.google.callback');

    Route::get('/admin/settings-backup-snapshots/{settingsBackupSnapshot}/file', SettingsBackupSnapshotFileController::class)
        ->name('admin.settings-backup-snapshots.file');

    Route::post('/admin/settings-backup-snapshots/{settingsBackupSnapshot}/retry', SettingsBackupSnapshotRetryController::class)
        ->name('admin.settings-backup-snapshots.retry');

    Route::get('/admin/settings-backups/{settingsBackupVersion}/snapshots.zip', SettingsBackupSnapshotsZipController::class)
        ->name('admin.settings-backups.snapshots-zip');

    Route::get('/admin/content-images-exports/{token}.zip', ContentImagesExportDownloadController::class)
        ->name('admin.content-images-exports.download');
});
