<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Author;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Validation\Rule;

class AuthorImporter extends Importer
{
    use ConfiguresContentImports;

    protected static ?string $model = Author::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key'))
                ->example('01JAUTHOR00000000000000001')
                ->rules(fn (?Author $record): array => [
                    'nullable',
                    'ulid',
                    'max:26',
                    Rule::unique('authors', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('name')
                ->label(__('admin.fields.author_name'))
                ->requiredMapping()
                ->example('דנה כהן')
                ->rules(fn (?Author $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'max:255',
                ])
                ->ignoreBlankState(fn (?Author $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->example('dana-cohen')
                ->rules(fn (?Author $record): array => [
                    'nullable',
                    'max:255',
                    Rule::unique('authors', 'slug')->ignore($record?->getKey()),
                ])
                ->ignoreBlankState(fn (?Author $record): bool => $record?->exists ?? false),
            ImportColumn::make('bio_markdown')
                ->label(__('admin.fields.bio_markdown'))
                ->examples([
                    'ביוגרפיה קצרה עם **Markdown**.',
                    "שורה ראשונה\n\nשורה שנייה.",
                ])
                ->ignoreBlankState(fn (?Author $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
        ];
    }

    public function resolveRecord(): Author
    {
        /** @var Author $author */
        $author = $this->resolveRecordByReferenceKey(Author::class);

        return $author;
    }
}
