<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Rescues environments where a pre-fix deploy created this table before the
         * oversized composite index failed. Environments that recorded the migration
         * never re-run up(), so completed snapshot data is not dropped.
         */
        Schema::dropIfExists('settings_backup_snapshots');

        Schema::create('settings_backup_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_id')->constrained('settings_backup_versions')->cascadeOnDelete();
            $table->string('screen_key', 32);
            $table->string('theme', 16);
            $table->string('viewport', 32)->default('desktop-1440');
            $table->string('kind', 16);
            $table->string('format', 8);
            $table->string('resolved_url', 2048);
            $table->string('path')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['backup_id', 'screen_key', 'theme', 'viewport', 'kind', 'format'], 'backup_snapshot_unique_target');
            $table->index(['backup_id', 'status']);
            $table->index(['screen_key', 'theme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_backup_snapshots');
    }
};
