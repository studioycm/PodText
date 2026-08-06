<?php

namespace App\Console\Commands;

use App\Models\Contracts\FoldsSearchColumns;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class BackfillSearchFolds extends Command
{
    protected $signature = 'search:backfill-folds
                            {--chunk=500 : Rows to load per pass}
                            {--model= : Backfill one model class instead of every one}';

    protected $description = 'Populate the *_search shadow columns for rows written before Hebrew search folding, and repair any that drifted.';

    public function handle(): int
    {
        $models = $this->targetModels();

        if ($models === []) {
            $this->components->error('No models declare folded search columns.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $backfilled = 0;

        foreach ($models as $model) {
            $backfilled += $this->backfill($model, $chunk);
        }

        $this->components->info(sprintf(
            'Backfilled %s across %s.',
            Str::plural('row', $backfilled, true),
            Str::plural('model', count($models), true),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model&FoldsSearchColumns>  $model
     */
    private function backfill(string $model, int $chunk): int
    {
        $backfilled = 0;

        /*
         * `withoutTimestamps` keeps a backfill from reading as an editorial
         * edit, and `eachById` walks the table by primary key in bounded
         * passes. Rows whose shadow already matches leave nothing dirty, so
         * `save()` issues no statement — that is what makes re-running free.
         */
        $model::withoutTimestamps(function () use ($model, $chunk, &$backfilled): void {
            $model::query()->eachById(function (Model $record) use (&$backfilled): void {
                // `$model` is a runtime string, so the contract is checked here
                // as well as in targetModels() rather than narrowed by hand.
                if (! $record instanceof FoldsSearchColumns) {
                    return;
                }

                $record->refreshFoldedSearchColumns();

                if (! $record->isDirty()) {
                    return;
                }

                $record->saveQuietly();

                $backfilled++;
            }, $chunk);
        });

        return $backfilled;
    }

    /**
     * @return array<int, class-string<Model&FoldsSearchColumns>>
     */
    private function targetModels(): array
    {
        $requested = $this->option('model');

        if (filled($requested)) {
            return $this->declaresFoldedColumns((string) $requested)
                ? [(string) $requested]
                : [];
        }

        return collect(Finder::create()->files()->in(app_path('Models'))->name('*.php'))
            ->map(fn (SplFileInfo $file): string => 'App\\Models\\'.Str::of($file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', ''])
                ->toString())
            ->filter(fn (string $class): bool => $this->declaresFoldedColumns($class))
            ->sort()
            ->values()
            ->all();
    }

    private function declaresFoldedColumns(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return ! $reflection->isAbstract()
            && $reflection->isSubclassOf(Model::class)
            && $reflection->implementsInterface(FoldsSearchColumns::class);
    }
}
