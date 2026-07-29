<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Media\MediaRelocationBatch;
use Illuminate\Console\Command;

class RelocateRootMedia extends Command
{
    protected $signature = 'media:relocate-root
        {--apply : Execute the relocation; without it only the census report prints}
        {--chunk=50 : Rows relocated per chunk while applying}
        {--user= : Acting admin user id (defaults to the first admin)}';

    protected $description = 'Report or execute the managed relocation of root-level media files (census → chunked, resumable apply).';

    public function handle(MediaRelocationBatch $batch): int
    {
        $actor = $this->resolveActor();

        if (! $actor instanceof User) {
            $this->error('No admin user available to act as.');

            return self::FAILURE;
        }

        $census = $batch->census($actor);
        $this->info(sprintf(
            'Census: %d relocatable, %d skipped (acting as user %d).',
            $census['relocatable']->count(),
            count($census['skipped']),
            $actor->getKey(),
        ));

        if ($census['skipped'] !== []) {
            $this->table(
                ['id', 'path', 'reason'],
                collect($census['skipped'])->map(fn (array $row): array => [
                    $row['media']->getKey(),
                    (string) $row['media']->path,
                    $row['reason'],
                ])->all(),
            );
        }

        if (! $this->option('apply')) {
            $this->line('Report only. Re-run with --apply to relocate.');

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $moved = 0;
        $failed = [];

        do {
            $result = $batch->executeChunk($actor, $chunk, array_column($failed, 'id'));
            $moved += $result['moved'];
            $failed = array_merge($failed, $result['failed']);
            $this->line(sprintf('Chunk done: +%d moved, %d remaining.', $result['moved'], $result['remaining']));
        } while ($result['remaining'] > 0 && $result['moved'] > 0);

        if ($failed !== []) {
            $this->table(
                ['id', 'name', 'reason'],
                collect($failed)->map(fn (array $row): array => [$row['id'], $row['name'], $row['reason']])->all(),
            );
        }

        $after = $batch->census($actor);
        $this->info(sprintf(
            'Finished: %d moved, %d failed, %d still relocatable, %d skipped.',
            $moved,
            count($failed),
            $after['relocatable']->count(),
            count($after['skipped']),
        ));

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveActor(): ?User
    {
        $userId = $this->option('user');

        if (filled($userId)) {
            return User::query()->find((int) $userId);
        }

        return User::query()
            ->orderBy('id')
            ->get()
            ->first(fn (User $user): bool => $user->hasRoleAtLeast(UserRole::Admin));
    }
}
