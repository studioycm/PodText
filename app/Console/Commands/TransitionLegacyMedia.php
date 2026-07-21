<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Media\LegacyMediaTransitionExecutor;
use App\Support\Media\LegacyMediaTransitionPlanner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;

class TransitionLegacyMedia extends Command
{
    protected $signature = 'media:transition-legacy {media?* : One exact Curator media ID} {--path= : One exact reviewed rowless path} {--actor= : Admin user ID} {--digest= : Exact reviewed manifest digest} {--apply : Persist the reviewed transition}';

    protected $description = 'Dry-run or apply exact-digest legacy media transitions.';

    public function handle(LegacyMediaTransitionPlanner $planner, LegacyMediaTransitionExecutor $executor): int
    {
        try {
            $manifest = $planner->manifest();
            $planner->assertClosed($manifest);
            if (! $this->option('apply')) {
                $this->components->info('Dry run only. Digest: '.$manifest->digest());

                return self::SUCCESS;
            }
            if (blank($this->option('digest')) || blank($this->option('actor'))) {
                $this->components->error('--apply requires --actor and --digest.');

                return self::FAILURE;
            }
            $actor = User::query()->find((int) $this->option('actor'));
            if (! $actor instanceof User) {
                $this->components->error('The requested actor does not exist.');

                return self::FAILURE;
            }
            $ids = array_values(array_filter(array_map('intval', (array) $this->argument('media'))));
            $path = $this->option('path');
            if ((count($ids) === 1) === (is_string($path) && filled($path))) {
                $this->components->error('Apply requires exactly one media ID or one exact --path, never both.');

                return self::FAILURE;
            }
            $result = is_string($path) && filled($path)
                ? [$executor->applyPath($path, (string) $this->option('digest'), $actor)]
                : $executor->apply($ids, (string) $this->option('digest'), $actor);
            $this->components->info(implode(', ', $result));

            if (in_array('cleanup_pending', $result, true)) {
                $this->components->error('Transition committed but cleanup is pending; run the reviewed repair workflow.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (AuthorizationException|\InvalidArgumentException|\RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
