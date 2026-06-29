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
        Schema::create('transcriptions', function (Blueprint $table): void {
            $table->id();
            $table->char('reference_key', 26)->unique();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('language_code', 10)->default('he')->index();
            $table->longText('transcript_markdown');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->json('speakers')->nullable();
            $table->json('parsed_segments')->nullable();
            $table->timestamps();

            $table->index(['content_item_id', 'status', 'published_at']);
            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcriptions');
    }
};
