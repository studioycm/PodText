<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table): void {
            $table->string('verification_channel', 32)->nullable()->after('metadata')->index();
            $table->timestamp('verification_verified_at')->nullable()->after('verification_channel')->index();
        });
    }

    public function down(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table): void {
            $table->dropColumn(['verification_channel', 'verification_verified_at']);
        });
    }
};
