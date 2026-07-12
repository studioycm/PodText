<?php

namespace App\Support\Importer\SpotifyLinks;

use Illuminate\Http\UploadedFile;
use SplFileObject;

class SpotifyLinkParser
{
    /**
     * @return array{items: array<int, array{type: string, id: string, input: string, url: string}>, warnings: array<int, string>}
     */
    public function parse(string $input, SpotifyEntityMode $mode, int $cap = 25, ?UploadedFile $csv = null): array
    {
        $tokens = $this->tokensFromText($input);
        $warnings = [];

        if ($csv instanceof UploadedFile) {
            $tokens = [
                ...$tokens,
                ...$this->tokensFromCsv($csv, $warnings),
            ];
        }

        $items = [];
        $seen = [];
        $targetType = $mode->entityType();

        foreach ($tokens as $token) {
            $parsed = $this->parseToken($token, $targetType);

            if ($parsed === null) {
                $warnings[] = __('admin.spotify_fetcher.warnings.unrecognized', ['value' => $token]);

                continue;
            }

            if ($parsed['type'] !== $targetType) {
                $warnings[] = __('admin.spotify_fetcher.warnings.wrong_type', [
                    'type' => $parsed['type'],
                    'value' => $token,
                ]);

                continue;
            }

            $key = $parsed['type'].':'.$parsed['id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $parsed;
        }

        $cap = max(1, min(100, $cap));

        if (count($items) > $cap) {
            $warnings[] = __('admin.spotify_fetcher.warnings.cap_applied', [
                'cap' => $cap,
                'count' => count($items),
            ]);

            $items = array_slice($items, 0, $cap);
        }

        return [
            'items' => array_values($items),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tokensFromText(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        return collect(preg_split('/[\s,]+/u', $input) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    private function tokensFromCsv(UploadedFile $csv, array &$warnings): array
    {
        if (! $csv->isValid()) {
            $warnings[] = __('admin.spotify_fetcher.warnings.csv_unreadable');

            return [];
        }

        $file = new SplFileObject($csv->getRealPath() ?: $csv->path());
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $rows = [];

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $rows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);
        }

        if ($rows === []) {
            return [];
        }

        $first = array_map(fn (string $value): string => mb_strtolower($value), $rows[0]);
        $column = collect(['link', 'url', 'id'])
            ->map(fn (string $name): int|false => array_search($name, $first, true))
            ->first(fn (int|false $index): bool => $index !== false);

        $start = 0;

        if ($column === null) {
            $column = 0;
        } else {
            $start = 1;
        }

        return collect(array_slice($rows, $start))
            ->map(fn (array $row): string => $row[$column] ?? '')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, id: string, input: string, url: string}|null
     */
    private function parseToken(string $token, string $defaultType): ?array
    {
        $token = trim($token);

        if (preg_match('/^spotify:(episode|show):([A-Za-z0-9]+)$/', $token, $matches) === 1) {
            return $this->item($matches[1], $matches[2], $token);
        }

        if (preg_match('#open\.spotify\.com/(?:intl-[a-z]{2}/)?(episode|show)/([A-Za-z0-9]+)#i', $token, $matches) === 1) {
            return $this->item(mb_strtolower($matches[1]), $matches[2], $token);
        }

        if (preg_match('/^[A-Za-z0-9]{10,}$/', $token) === 1) {
            return $this->item($defaultType, $token, $token);
        }

        return null;
    }

    /**
     * @return array{type: string, id: string, input: string, url: string}
     */
    private function item(string $type, string $id, string $input): array
    {
        return [
            'id' => $id,
            'input' => $input,
            'type' => $type,
            'url' => "https://open.spotify.com/{$type}/{$id}",
        ];
    }
}
