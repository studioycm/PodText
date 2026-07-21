<?php

namespace App\Console\Commands;

use App\Support\Media\LegacyMediaTransitionPlanner;
use Illuminate\Console\Command;

class PreflightLegacyMediaTransition extends Command
{
    protected $signature = 'media:preflight-legacy-transition {--json : Print the canonical manifest JSON}';

    protected $description = 'Build a deterministic, read-only legacy media transition manifest.';

    public function handle(LegacyMediaTransitionPlanner $planner): int
    {
        $manifest = $planner->manifest();
        try {
            $planner->assertClosed($manifest);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Legacy transition manifest digest: '.$manifest->digest());
            $this->table(['media_id', 'disposition', 'reason'], collect($manifest->entries)->map(fn (array $entry): array => [$entry['media_id'], $entry['disposition'], $entry['reason']])->all());
        }

        return self::SUCCESS;
    }
}
