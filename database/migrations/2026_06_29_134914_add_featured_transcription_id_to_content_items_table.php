<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table
                ->foreignId('featured_transcription_id')
                ->nullable()
                ->after('transcript_markdown')
                ->constrained('transcriptions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_transcription_id');
        });
    }
};
