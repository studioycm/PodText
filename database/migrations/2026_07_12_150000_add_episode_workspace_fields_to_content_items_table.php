<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('title_prefix')->nullable()->after('title');
            $table->text('embed_html')->nullable()->after('embed_url');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropColumn(['title_prefix', 'embed_html']);
        });
    }
};
