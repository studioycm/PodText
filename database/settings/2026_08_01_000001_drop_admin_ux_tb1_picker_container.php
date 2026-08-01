<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Retires `admin_ux.tb1_picker_container`.
 *
 * It chose whether the TB1 table image picker opened as a modal or a
 * slide-over. Mini-task 3A (`6da7fda`, 2026-07-27) replaced that action with
 * one canonical modal and declared the runtime choice obsolete, then stopped
 * reading the setting but kept the row as inert data.
 *
 * The row was still required: the property carried no default, so a missing
 * row threw `MissingSettings` and took down every screen that loads the
 * `admin_ux` group — a hard dependency on a value nothing read. The property
 * and its enum are gone, so the row goes too.
 *
 * Deleting a row whose property no longer exists is safe in the other
 * direction as well: `SettingsMapper::load()` fetches only reflected property
 * names, so an orphan row is never read.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('admin_ux.tb1_picker_container');
    }

    public function down(): void
    {
        if (! $this->migrator->exists('admin_ux.tb1_picker_container')) {
            $this->migrator->add('admin_ux.tb1_picker_container', 'modal');
        }
    }
};
