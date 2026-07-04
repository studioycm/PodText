<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->json('source_config')->nullable()->after('is_visible');
            $table->json('selection_config')->nullable()->after('source_config');
            $table->json('display_config')->nullable()->after('selection_config');
            $table->json('pagination_config')->nullable()->after('display_config');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->dropColumn([
                'source_config',
                'selection_config',
                'display_config',
                'pagination_config',
            ]);
        });
    }
};
