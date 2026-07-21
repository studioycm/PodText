<?php

namespace App\Console\Commands;

use App\Support\Media\MediaIntegrityReporter;
use Illuminate\Console\Command;
use JsonException;

class ReportMediaIntegrity extends Command
{
    protected $signature = 'media:report-integrity {--json : Emit machine-readable JSON}';

    protected $description = 'Report allowed, disallowed, missing, duplicate, referenced, attached, and orphaned media state.';

    public function handle(MediaIntegrityReporter $reporter): int
    {
        $report = $reporter->report();

        if ($this->option('json')) {
            try {
                $json = json_encode(
                    $report,
                    JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_INVALID_UTF8_SUBSTITUTE
                        | JSON_THROW_ON_ERROR,
                );
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line($json);

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Reference key', 'Disk', 'Path', 'Root', 'MIME', 'Ext', 'File state', 'Allowed', 'Active refs', 'Attached', 'Next action'],
            collect($report['media'])->map(fn (array $row): array => [
                $row['media_id'],
                $row['reference_key'],
                $row['disk'],
                $row['path'],
                $row['root'],
                $row['mime_type'],
                $row['extension'],
                $row['file_state'],
                $row['allowed'] ? 'yes' : 'no',
                count($row['legacy_references']),
                count($row['attachment_references']),
                $row['recommended_disposition'],
            ])->all(),
        );
        if (($report['rowless_transition_candidates'] ?? []) !== []) {
            $this->newLine();
            $this->components->warn('Rowless transition candidates are not gallery media and cannot be browsed before commit.');
            $this->table(
                ['Path', 'Active refs', 'Next action', 'Reason'],
                collect($report['rowless_transition_candidates'])->map(fn (array $row): array => [
                    $row['path'] ?? '-', count($row['active_references'] ?? []), $row['disposition'] ?? '-', $row['reason'] ?? '-',
                ])->all(),
            );
        }
        $this->components->info(collect([
            'transition pending' => collect($report['media'])->filter(fn (array $row): bool => in_array($row['recommended_disposition'] ?? null, ['key_only', 'normalize_existing', 'sanitize_svg', 'import_exact_path'], true))->count()
                + collect($report['rowless_transition_candidates'] ?? [])->where('disposition', 'import_exact_path')->count(),
            'detach to default' => collect($report['media'])->where('recommended_disposition', 'detach_to_default')->count()
                + collect($report['rowless_transition_candidates'] ?? [])->where('disposition', 'detach_to_default')->count(),
            'blocked legacy rows' => collect($report['media'])->filter(fn (array $row): bool => str_starts_with((string) ($row['recommended_disposition'] ?? ''), 'blocked'))->count()
                + collect($report['rowless_transition_candidates'] ?? [])->where('disposition', 'blocked')->count(),
            'duplicate locations' => count($report['duplicate_locations']),
            'attachment issues' => count($report['attachment_issues']),
            'orphan attachments' => count($report['orphan_attachments']),
            'settings identity issues' => count($report['settings_identity_issues']),
            'incomplete mutations' => count($report['incomplete_mutations']),
        ])->map(fn (int $count, string $label): string => "{$label}: {$count}")->implode('; ').'.');

        return self::SUCCESS;
    }
}
