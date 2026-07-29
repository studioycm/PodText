<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media ownership truth is the media_attachments pivot; the legacy
     * owner path columns carried a duplicate second source whose
     * production census showed zero unique data before removal.
     */
    public function up(): void
    {
        Schema::table('content_groups', function (Blueprint $table): void {
            $table->dropColumn('cover_path');
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('content_groups', function (Blueprint $table): void {
            $table->string('cover_path')->nullable()->after('description_markdown');
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('external_thumbnail_url');
        });
    }
};
