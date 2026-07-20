<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('curator')->restrictOnDelete();
            $table->string('attachable_type', 32);
            $table->unsignedBigInteger('attachable_id');
            $table->string('role', 32);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(
                ['attachable_type', 'attachable_id', 'role'],
                'media_attachments_owner_role_unique',
            );
            $table->index(['media_id', 'role'], 'media_attachments_media_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
