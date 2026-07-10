<?php

namespace App\Filament\Forms\Components;

use App\Support\Slugs\HebrewSlugger;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component as Livewire;

class SlugInput
{
    public static function source(
        string $name,
        string $slugField = 'slug',
        ?string $table = null,
        ?Closure $scopeUsing = null,
    ): TextInput {
        return TextInput::make($name)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?Model $record = null, ?Livewire $livewire = null) use ($slugField, $table, $scopeUsing): void {
                if (filled($get($slugField))) {
                    return;
                }

                if (blank($state)) {
                    return;
                }

                $set($slugField, self::slugFor($state, $table, $scopeUsing, $get, $record, $livewire));
            });
    }

    public static function slug(
        string $name = 'slug',
        string $source = 'name',
        ?string $table = null,
        ?Closure $scopeUsing = null,
        ?Closure $modifyRuleUsing = null,
    ): TextInput {
        $field = TextInput::make($name)
            ->helperText(__('admin.helpers.slug'))
            ->maxLength(255)
            ->hintAction(
                Action::make('regenerateSlug')
                    ->label(__('admin.actions.regenerate_slug'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Set $set, Get $get, ?Model $record = null, ?Livewire $livewire = null) use ($name, $source, $table, $scopeUsing): void {
                        $set($name, self::slugFor($get($source), $table, $scopeUsing, $get, $record, $livewire));
                    }),
            );

        if ($table === null) {
            return $field;
        }

        return $field->unique(table: $table, column: $name, modifyRuleUsing: $modifyRuleUsing);
    }

    private static function slugFor(
        mixed $source,
        ?string $table,
        ?Closure $scopeUsing,
        Get $get,
        ?Model $record,
        ?Livewire $livewire,
    ): string {
        if ($table === null) {
            return HebrewSlugger::slug((string) $source);
        }

        return HebrewSlugger::unique(
            (string) $source,
            function (string $slug) use ($table, $scopeUsing, $get, $record, $livewire): bool {
                $query = DB::table($table)->where('slug', $slug);

                if ($record?->exists) {
                    $query->where($record->getKeyName(), '!=', $record->getKey());
                }

                $query = self::applyScope($query, $scopeUsing, $get, $record, $livewire);

                return $query->exists();
            },
        );
    }

    private static function applyScope(
        Builder $query,
        ?Closure $scopeUsing,
        Get $get,
        ?Model $record,
        ?Livewire $livewire,
    ): Builder {
        if ($scopeUsing === null) {
            return $query;
        }

        return $scopeUsing($query, $get, $record, $livewire) ?? $query;
    }
}
