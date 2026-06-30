<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_groups', function (Blueprint $table): void {
            $table->integer('homepage_order')->nullable()->index()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_groups', function (Blueprint $table): void {
            $table->dropIndex(['homepage_order']);
            $table->dropColumn('homepage_order');
        });
    }
};
