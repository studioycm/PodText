<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('author_transcription', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['author_id', 'transcription_id']);
            $table->index('author_id');
            $table->index('transcription_id');
            $table->index(['transcription_id', 'sort_order']);
        });

        DB::table('transcriptions')
            ->select(['id', 'author_id'])
            ->whereNotNull('author_id')
            ->orderBy('id')
            ->chunkById(500, function ($transcriptions): void {
                $now = now();
                $rows = $transcriptions
                    ->map(fn (object $transcription): array => [
                        'author_id' => $transcription->author_id,
                        'transcription_id' => $transcription->id,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rows === []) {
                    return;
                }

                DB::table('author_transcription')->insertOrIgnore($rows);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('author_transcription');
    }
};
