<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_mutation_operations', function (Blueprint $table): void {
            $table->id();
            $table->char('operation_key', 26)->unique();
            $table->foreignId('media_id')->nullable()->constrained('curator')->nullOnDelete();
            $table->unsignedBigInteger('media_id_snapshot')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('media_reference_key', 26)->nullable();
            $table->string('operation', 32);
            $table->string('status', 32);
            $table->string('purpose', 64)->nullable();
            $table->char('idempotency_key', 64)->nullable()->index();
            $table->string('source_disk')->nullable();
            $table->text('source_path')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->string('destination_disk')->nullable();
            $table->text('destination_path')->nullable();
            $table->char('destination_sha256', 64)->nullable();
            $table->string('staging_disk')->nullable();
            $table->text('staging_path')->nullable();
            $table->char('staging_sha256', 64)->nullable();
            $table->string('quarantine_disk')->nullable();
            $table->text('quarantine_path')->nullable();
            $table->char('quarantine_sha256', 64)->nullable();
            $table->json('context')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->char('lease_token', 26)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('cleanup_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at', 'id'], 'media_mutations_status_updated_index');
            $table->index(['media_id', 'status'], 'media_mutations_media_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_mutation_operations');
    }
};
