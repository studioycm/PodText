<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\PublicMediaDelivery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminMediaFileController extends Controller
{
    public function __construct(private readonly PublicMediaDelivery $publicMediaDelivery) {}

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
        $media = $scope->findInventoryOrFail($mediaId);
        Gate::forUser($actor)->authorize($ability, $media);
        $disk = (string) $media->disk;
        abort_unless(array_key_exists($disk, config('filesystems.disks', [])), 404);

        try {
            abort_unless(Storage::disk($disk)->exists($media->path), 404);
        } catch (Throwable) {
            abort(404);
        }

        if ($ability === 'view') {
            abort_unless($this->publicMediaDelivery->canRenderInline($media), 404);
        }

        return Storage::disk($disk)->response(
            $media->path,
            basename($media->path),
            [
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
                'Content-Type' => filled($media->type) ? $media->type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition,
        );
    }
}
