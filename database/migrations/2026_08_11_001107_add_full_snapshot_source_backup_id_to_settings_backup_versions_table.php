<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix 3 of register 1.8: a full-source backup whose payload-minus-locks
     * matches a sibling that owns a complete DONE full snapshot set borrows
     * that set instead of re-rendering it. This pointer records the owner;
     * deleting the owner degrades the borrower to thumbnails-only.
     */
    public function up(): void
    {
        Schema::table('settings_backup_versions', function (Blueprint $table): void {
            $table->foreignId('full_snapshot_source_backup_id')
                ->nullable()
                ->constrained('settings_backup_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settings_backup_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('full_snapshot_source_backup_id');
        });
    }
};
