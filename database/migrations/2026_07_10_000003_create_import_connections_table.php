<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider', 32);
            $table->string('auth_type', 32);
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 32)->default('untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('status');
        });
    }
};
