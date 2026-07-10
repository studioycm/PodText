<?php

namespace App\Console\Commands;

use App\Enums\ImportConnectionProvider;
use App\Models\ImportConnection;
use App\Support\Importer\Google\GoogleDriveConnector;
use App\Support\Importer\TranscriptFormatProbeAnalyzer;
use App\Support\Importer\TranscriptFormatProbePaths;
use App\Support\Importer\TranscriptFormatProbeWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProbeImporterFormats extends Command
{
    protected $signature = 'importer:probe-formats {connection} {--ids=} {--file=} {--limit=20}';

    protected $description = 'Fetch Google Doc transcript samples and write structural format findings for the importer workbench.';

    public function handle(
        GoogleDriveConnector $connector,
        TranscriptFormatProbeAnalyzer $analyzer,
        TranscriptFormatProbePaths $paths,
        TranscriptFormatProbeWriter $writer,
    ): int {
        $connection = ImportConnection::query()->findOrFail($this->argument('connection'));

        if ($connection->provider !== ImportConnectionProvider::GoogleDrive) {
            $this->error('The selected connection must be a Google Drive connection.');

            return self::FAILURE;
        }

        $documentIds = $this->documentIds();

        if ($documentIds === []) {
            $this->error('Provide document IDs through --ids or --file.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($paths->sampleDirectory());

        $analyses = [];

        foreach ($documentIds as $documentId) {
            $samplePath = $paths->samplePath($documentId);
            $markdown = File::exists($samplePath)
                ? File::get($samplePath)
                : $connector->exportDocMarkdown($connection, $documentId);

            if (! File::exists($samplePath)) {
                File::put($samplePath, $markdown);
            }

            $analyses[] = $analyzer->analyze($documentId, $markdown);
            $this->line("Probed {$documentId}");
        }

        $writer->write($analyses);
        $this->info('Importer transcript format findings written.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function documentIds(): array
    {
        $ids = [
            ...$this->idsFromOption((string) $this->option('ids')),
            ...$this->idsFromFile((string) $this->option('file')),
        ];

        return collect($ids)
            ->map(fn (string $id): string => $this->extractDocumentId($id))
            ->filter()
            ->unique()
            ->take((int) $this->option('limit'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function idsFromOption(string $ids): array
    {
        if ($ids === '') {
            return [];
        }

        return preg_split('/[\s,]+/', $ids, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return array<int, string>
     */
    private function idsFromFile(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $content = File::get($path);
        $json = json_decode($content, true);

        if (is_array($json)) {
            return collect($json)
                ->map(fn (mixed $row): ?string => is_array($row)
                    ? (string) (data_get($row, 'doc_id') ?? data_get($row, 'document_id') ?? data_get($row, 'id') ?? data_get($row, 'raw_link'))
                    : (string) $row)
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/\R/u', $content) ?: [])
            ->flatMap(fn (string $line): array => str_getcsv($line))
            ->reject(fn (?string $value): bool => blank($value) || in_array(Str::lower((string) $value), ['id', 'doc_id', 'document_id', 'raw_link'], true))
            ->values()
            ->all();
    }

    private function extractDocumentId(string $value): string
    {
        if (preg_match('~/document/d/([A-Za-z0-9_-]+)~', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^[A-Za-z0-9_-]{10,}$/', $value) === 1) {
            return $value;
        }

        return '';
    }
}
