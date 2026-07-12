<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Media\ContentImagesExportManager;
use Illuminate\Http\Request;

class ContentImagesExportDownloadController
{
    public function __invoke(Request $request, string $token, ContentImagesExportManager $manager)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $manager->downloadResponse((int) $user->getKey(), $token);
    }
}
