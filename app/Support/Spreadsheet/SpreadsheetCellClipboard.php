<?php

namespace App\Support\Spreadsheet;

class SpreadsheetCellClipboard
{
    /**
     * @param  array<int, string|null>  $cells
     */
    public static function oneColumnPayload(array $cells): string
    {
        return collect($cells)
            ->map(fn (?string $cell): string => self::quoteCell($cell ?? ''))
            ->implode("\n");
    }

    public static function quoteCell(string $cell): string
    {
        $cell = str_replace(["\r\n", "\r"], "\n", $cell);

        return '"'.str_replace('"', '""', $cell).'"';
    }
}
