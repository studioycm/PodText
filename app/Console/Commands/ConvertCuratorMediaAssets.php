<?php

namespace App\Console\Commands;

use App\Support\Media\CuratorMediaAssetConversionReport;
use App\Support\Media\CuratorMediaAssetConverter;
use Illuminate\Console\Command;

class ConvertCuratorMediaAssets extends Command
{
    protected $signature = 'media-assets:convert-curator
        {--apply : Persist the database-only Curator identity conversion}
        {--json : Emit the report as JSON}';

    protected $description = 'Report or convert every Curator row to the minimal portable MediaAsset kernel';

    public function handle(CuratorMediaAssetConverter $converter): int
    {
        $apply = (bool) $this->option('apply');
        $report = $apply ? $converter->convert() : $converter->inspect();
        $mode = $apply ? 'applied' : 'report';

        if ($this->option('json')) {
            $this->line((string) json_encode(
                [
                    'mode' => $mode,
                    ...$report->toArray(),
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        $this->info($apply
            ? 'Curator media asset database conversion applied.'
            : 'Curator media asset conversion report only; no database values changed.');
        $this->table(
            ['metric', 'count'],
            collect($report->toArray()['counts'])
                ->map(fn (int $count, string $name): array => [$name, $count])
                ->values()
                ->all(),
        );
        $this->renderDetails($report);

        return self::SUCCESS;
    }

    private function renderDetails(CuratorMediaAssetConversionReport $report): void
    {
        foreach ([
            'missing_files',
            'duplicate_paths',
            'unresolved_owners',
            'unresolved_settings',
        ] as $name) {
            if ($report->details($name) === []) {
                continue;
            }

            $this->warn("{$name}: ".count($report->details($name)));
        }
    }
}
