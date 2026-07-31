<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->timestamp('audience_made_public_at')->nullable();
            $table->foreignId('audience_made_public_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('audience_made_public_by_user_id');
            $table->dropColumn('audience_made_public_at');
        });
    }
};
