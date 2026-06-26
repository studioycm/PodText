<?php

namespace App\Filament\Imports\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

trait ConfiguresContentImports
{
    /**
     * @var array<int, string>
     */
    protected array $seenReferenceKeys = [];

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('mode')
                ->label(__('admin.import.options.mode'))
                ->options([
                    'upsert' => __('admin.import.options.modes.upsert'),
                    'create' => __('admin.import.options.modes.create'),
                    'update' => __('admin.import.options.modes.update'),
                ])
                ->default('upsert')
                ->required(),
            Select::make('blank_update_behavior')
                ->label(__('admin.import.options.blank_update_behavior'))
                ->options([
                    'preserve' => __('admin.import.options.blank_update_behaviors.preserve'),
                    'overwrite' => __('admin.import.options.blank_update_behaviors.overwrite'),
                ])
                ->default('preserve')
                ->required(),
        ];
    }

    public function getJobQueue(): ?string
    {
        return 'imports-exports';
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, int>
     */
    public function getJobBackoff(): array
    {
        return [30, 120, 300];
    }

    public function getJobBatchName(): ?string
    {
        return (string) str(class_basename(static::class))
            ->beforeLast('Importer')
            ->kebab()
            ->append('-import');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveRecordByReferenceKey(string $modelClass): Model
    {
        $referenceKey = filled($this->data['reference_key'] ?? null)
            ? (string) $this->data['reference_key']
            : null;

        if ($referenceKey !== null) {
            if (in_array($referenceKey, $this->seenReferenceKeys, true)) {
                throw new RowImportFailedException(__('admin.import.failures.duplicate_reference_key', [
                    'reference_key' => $referenceKey,
                ]));
            }

            $this->seenReferenceKeys[] = $referenceKey;
        }

        if ($referenceKey === null && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_requires_reference_key'));
        }

        $record = $referenceKey
            ? $modelClass::query()->where('reference_key', $referenceKey)->first()
            : null;

        if ($record && $this->importMode() === 'create') {
            throw new RowImportFailedException(__('admin.import.failures.create_found_existing_reference_key', [
                'reference_key' => $referenceKey,
            ]));
        }

        if (! $record && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_missing_reference_key', [
                'reference_key' => $referenceKey,
            ]));
        }

        return $record ?? new $modelClass(array_filter([
            'reference_key' => $referenceKey,
        ]));
    }

    protected static function shouldIgnoreBlankForUpdate(?Model $record, array $options): bool
    {
        return ($record?->exists ?? false)
            && (($options['blank_update_behavior'] ?? 'preserve') === 'preserve');
    }

    protected static function shouldRequireValue(?Model $record, array $options): bool
    {
        return ! ($record?->exists ?? false)
            || (($options['blank_update_behavior'] ?? 'preserve') === 'overwrite');
    }

    protected function importMode(): string
    {
        return $this->options['mode'] ?? 'upsert';
    }
}
