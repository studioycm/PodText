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
        Schema::create('content_groups', function (Blueprint $table) {
            $table->id();
            $table->char('reference_key', 26)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('group_type_label_singular')->default('Podcast');
            $table->string('group_type_label_plural')->default('Podcasts');
            $table->string('default_item_type_label_singular')->default('Episode');
            $table->string('default_item_type_label_plural')->default('Episodes');
            $table->longText('description_markdown')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('original_language_code', 16)->default('he')->index();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_groups');
    }
};
