<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->char('reference_key', 26)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->dropUnique(['reference_key']);
            $table->dropColumn('reference_key');
        });
    }
};
