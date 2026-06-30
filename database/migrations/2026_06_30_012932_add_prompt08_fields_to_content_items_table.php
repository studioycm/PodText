<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->boolean('is_pinned')->default(false)->index()->after('featured_transcription_id');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->timestamp('pinned_until')->nullable()->after('pinned_at');
            $table->unsignedInteger('pin_order')->nullable()->after('pinned_until');
            $table->string('embed_provider', 50)->nullable()->index()->after('embed_url');
            $table->unsignedInteger('media_duration_seconds')->nullable()->after('duration_seconds');
            $table->string('external_id')->nullable()->after('embed_provider');
            $table->string('external_title')->nullable()->after('external_id');
            $table->text('external_description')->nullable()->after('external_title');
            $table->string('external_thumbnail_url', 2048)->nullable()->after('external_description');
            $table->timestamp('external_published_at')->nullable()->after('external_thumbnail_url');
            $table->json('media_metadata')->nullable()->after('external_published_at');
            $table->string('direct_media_url', 2048)->nullable()->after('media_metadata');

            $table->index(['embed_provider', 'external_id']);
            $table->index(['is_pinned', 'pin_order', 'pinned_at']);
            $table->index('pinned_until');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropIndex(['embed_provider', 'external_id']);
            $table->dropIndex(['is_pinned', 'pin_order', 'pinned_at']);
            $table->dropIndex(['pinned_until']);
            $table->dropColumn([
                'is_pinned',
                'pinned_at',
                'pinned_until',
                'pin_order',
                'embed_provider',
                'media_duration_seconds',
                'external_id',
                'external_title',
                'external_description',
                'external_thumbnail_url',
                'external_published_at',
                'media_metadata',
                'direct_media_url',
            ]);
        });
    }
};
