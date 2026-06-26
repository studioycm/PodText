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
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->char('reference_key', 26)->unique();
            $table->foreignId('content_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('type_label_singular_override')->nullable();
            $table->longText('description_markdown')->nullable();
            $table->string('media_url', 2048);
            $table->string('embed_url', 2048)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcript_markdown')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('original_published_at')->nullable();
            $table->timestamps();

            $table->unique(['content_group_id', 'slug']);
            $table->index(['content_group_id', 'status', 'published_at']);
            $table->index('original_published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
