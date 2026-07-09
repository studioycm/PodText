<?php

namespace App\Http\Controllers;

use App\Models\SettingsBackupSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsBackupSnapshotFileController
{
    public function __invoke(Request $request, SettingsBackupSnapshot $settingsBackupSnapshot): StreamedResponse
    {
        abort_if(blank($settingsBackupSnapshot->path), 404);
        abort_unless(Storage::disk('local')->exists((string) $settingsBackupSnapshot->path), 404);

        $headers = [
            'Content-Type' => $settingsBackupSnapshot->contentType(),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$settingsBackupSnapshot->downloadFilename().'"',
        ];

        return Storage::disk('local')->response((string) $settingsBackupSnapshot->path, null, $headers);
    }
}
