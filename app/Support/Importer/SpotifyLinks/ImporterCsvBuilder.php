<?php

namespace App\Support\Importer\SpotifyLinks;

use Carbon\CarbonInterface;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Carbon;

class ImporterCsvBuilder
{
    /**
     * @param  class-string  $importerClass
     * @return array<int, string>
     */
    public function headersFor(string $importerClass): array
    {
        return collect($importerClass::getColumns())
            ->map(fn (ImportColumn $column): string => $column->getName())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function csv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, collect($headers)
                ->map(fn (string $header): string => $this->cell($row[$header] ?? ''))
                ->all());
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    public function importDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone('Asia/Jerusalem')->format('d/m/Y H:i');
        }

        if (blank($value)) {
            return '';
        }

        return Carbon::parse((string) $value, 'Asia/Jerusalem')->format('d/m/Y H:i');
    }

    private function cell(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            $value = $this->importDate($value);
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=\-+@\t\r]/', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }
}
