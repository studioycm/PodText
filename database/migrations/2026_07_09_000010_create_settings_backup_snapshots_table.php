<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_backup_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_id')->constrained('settings_backup_versions')->cascadeOnDelete();
            $table->string('screen_key');
            $table->string('theme');
            $table->string('viewport')->default('desktop-1440');
            $table->string('kind');
            $table->string('format');
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
