<?php

use App\Settings\PublicContentSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_backup_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default(PublicContentSettings::group())->index();
            $table->string('label')->nullable();
            $table->longText('payload_json');
            $table->string('checksum', 64);
            $table->string('payload_hash', 64)->index();
            $table->string('source')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_backup_versions');
    }
};
