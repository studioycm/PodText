<?php

namespace App\Support\Importer\Contracts;

use App\Models\ImportConnection;

interface SpotifyClientFactory
{
    public function make(ImportConnection $connection): SpotifyClient;
}
