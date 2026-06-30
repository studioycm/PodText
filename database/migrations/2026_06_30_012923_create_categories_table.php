<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description_markdown')->nullable();
            $table->boolean('is_visible')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('category_content_group', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'content_group_id']);
        });

        Schema::create('category_content_item', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'content_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_content_item');
        Schema::dropIfExists('category_content_group');
        Schema::dropIfExists('categories');
    }
};
