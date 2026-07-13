<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 32);
            $table->string('address');
            $table->string('code_hash');
            $table->string('form_key', 80);
            $table->string('guest_token_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['channel', 'address', 'form_key'], 'form_verification_codes_lookup_index');
            $table->index(['channel', 'address', 'form_key', 'guest_token_hash'], 'form_verification_codes_guest_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_verification_codes');
    }
};
