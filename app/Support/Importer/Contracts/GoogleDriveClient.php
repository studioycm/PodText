<?php

namespace App\Support\Importer\Contracts;

use App\Models\ImportConnection;

interface GoogleDriveClient
{
    /**
     * @return array<int, string>
     */
    public function listSpreadsheetTabs(string $spreadsheetId): array;

    /**
     * @return array<int, array<int, mixed>>
     */
    public function readSheetRange(string $spreadsheetId, string $tab, string $range): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listFolderFiles(string $folderId, int $limit = 100): array;

    public function exportDocMarkdown(string $documentId): string;

    public function downloadFile(string $fileId): string;

    /**
     * @return array<string, mixed>|null
     */
    public function refreshAccessTokenIfNeeded(ImportConnection $connection): ?array;
}
