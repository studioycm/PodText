<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->char('reference_key', 26)->unique();
            $table->timestamps();
        });

        Schema::create('media_provider_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_record_key', 191);
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_record_key'],
                'media_provider_record_unique',
            );
            $table->unique(
                ['media_asset_id', 'provider'],
                'media_asset_provider_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_provider_bindings');
        Schema::dropIfExists('media_assets');
    }
};
