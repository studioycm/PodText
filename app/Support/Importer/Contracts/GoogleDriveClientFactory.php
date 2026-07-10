<?php

namespace App\Support\Importer\Contracts;

use App\Models\ImportConnection;

interface GoogleDriveClientFactory
{
    public function make(ImportConnection $connection): GoogleDriveClient;
}
