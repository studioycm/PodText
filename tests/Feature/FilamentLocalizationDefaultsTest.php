<?php

use App\Support\UiFormats;
use App\Support\UiTimezone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

it('sets the app-wide Filament timezone to the UI timezone', function (): void {
    expect(FilamentTimezone::get())->toBe(UiTimezone::name());
});

it('defaults every table and picker to the UiFormats day-first shapes', function (): void {
    // InteractsWithTable does not itself implement the full HasTable
    // contract — it is missing makeFilamentTranslatableContentDriver(),
    // which every real Filament page/relation manager supplies from its own
    // InteractsWithSchemas usage. A bare stand-in has to add it back, or the
    // anonymous class fails to declare (fatal, uncatchable by a test) before
    // Table::make() ever runs.
    $table = Table::make(new class extends LivewireComponent implements HasTable
    {
        use InteractsWithTable;

        public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
        {
            return null;
        }
    });

    expect($table->getDefaultDateDisplayFormat())->toBe(UiFormats::date());
    expect($table->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
    expect($table->getDefaultTimeDisplayFormat())->toBe(UiFormats::time());

    $picker = DateTimePicker::make('probe');
    expect($picker->isNative())->toBeFalse();
    expect($picker->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
});

it('defaults every schema (forms and infolists) to the same day-first shapes', function (): void {
    // Infolist TextEntry::date()/dateTime()/time() resolve their default
    // format from $component->getContainer() — the owning Schema, a separate
    // HasDefaultDataFormattingSettings instance from Table's — so this is a
    // distinct code path from the table/picker assertions above.
    $schema = Schema::make();

    expect($schema->getDefaultDateDisplayFormat())->toBe(UiFormats::date());
    expect($schema->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
    expect($schema->getDefaultTimeDisplayFormat())->toBe(UiFormats::time());
});
