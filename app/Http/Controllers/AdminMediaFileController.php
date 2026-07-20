<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Media\MediaRecordScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMediaFileController extends Controller
{
    public function view(int $media, MediaRecordScope $scope): StreamedResponse
    {
        return $this->response($media, $scope, 'view', 'inline');
    }

    public function download(int $media, MediaRecordScope $scope): StreamedResponse
    {
        return $this->response($media, $scope, 'download', 'attachment');
    }

    private function response(
        int $mediaId,
        MediaRecordScope $scope,
        string $ability,
        string $disposition,
    ): StreamedResponse {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $media = $scope->findOrFail($mediaId);
        Gate::forUser($actor)->authorize($ability, $media);
        abort_unless(Storage::disk('public')->exists($media->path), 404);

        return Storage::disk('public')->response(
            $media->path,
            basename($media->path),
            [
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
                'Content-Type' => $media->type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition,
        );
    }
}
