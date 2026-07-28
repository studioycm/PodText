<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->timestamp('trusted_at')->nullable();
            $table->foreignId('trusted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trusted_by_user_id');
            $table->dropColumn('trusted_at');
        });
    }
};
