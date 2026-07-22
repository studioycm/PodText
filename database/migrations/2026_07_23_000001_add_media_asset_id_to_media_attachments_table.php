<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_attachments', function (Blueprint $table): void {
            $table->foreignId('media_asset_id')
                ->nullable()
                ->after('media_id')
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->index(
                ['media_asset_id', 'role'],
                'media_attachments_asset_role_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('media_attachments', function (Blueprint $table): void {
            $table->dropForeign(['media_asset_id']);
            $table->dropIndex('media_attachments_asset_role_index');
            $table->dropColumn('media_asset_id');
        });
    }
};
