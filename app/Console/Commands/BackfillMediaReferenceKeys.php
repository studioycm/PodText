<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Media\LegacyMediaTransitionExecutor;
use App\Support\Media\LegacyMediaTransitionPlanner;
use Illuminate\Console\Command;
use RuntimeException;

class BackfillMediaReferenceKeys extends Command
{
    protected $signature = 'media:backfill-reference-keys
        {--apply : Persist one proof-backed reference key}
        {--media= : One exact Curator media ID for apply}
        {--actor= : Admin user ID for apply}
        {--digest= : Exact reviewed transition manifest digest for apply}';

    protected $description = 'Report key-only transition candidates or execute one exact-digest key-only transition.';

    public function handle(LegacyMediaTransitionPlanner $planner, LegacyMediaTransitionExecutor $executor): int
    {
        try {
            $manifest = $planner->manifest();
            $planner->assertClosed($manifest);
            $entries = collect($manifest->entries);
            $counts = [
                'eligible' => $entries->where('disposition', 'key_only')->count(),
                'transition_pending' => $entries->where('disposition', 'normalize_existing')->count(),
                'pending_svg_sanitation' => $entries->where('disposition', 'sanitize_svg')->count(),
                'detach_to_default' => $entries->where('disposition', 'detach_to_default')->count(),
                'blocked' => $entries->where('disposition', 'blocked')->count(),
            ];
            if (! $this->option('apply')) {
                $this->components->info('Dry run: '.collect($counts)->map(fn (int $count, string $name): string => "{$name}={$count}")->implode(', ').". Digest={$manifest->digest()}. No rows or files were changed.");

                return self::SUCCESS;
            }
            $id = $this->option('media');
            $actorId = $this->option('actor');
            if (! is_string($id) || ! ctype_digit($id) || ! is_string($actorId) || ! ctype_digit($actorId) || blank($this->option('digest'))) {
                throw new RuntimeException('Apply requires exactly one --media, --actor, and reviewed --digest.');
            }
            $entry = $entries->firstWhere('media_id', (int) $id);
            if (! is_array($entry) || ! in_array($entry['disposition'] ?? null, ['key_only', 'blocked'], true)) {
                throw new RuntimeException('The selected media is not a key-only transition candidate; run media:transition-legacy for its reviewed disposition.');
            }
            $actor = User::query()->find((int) $actorId);
            if (! $actor instanceof User) {
                throw new RuntimeException('The requested actor does not exist.');
            }
            $result = $executor->apply([(int) $id], (string) $this->option('digest'), $actor);
            $this->components->info(implode(', ', $result));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
