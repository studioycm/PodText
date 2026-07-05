<?php

use App\Enums\PublicFormSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('form_key')->index();
            $table->string('form_name_snapshot')->nullable();
            $table->json('payload');
            $table->string('status')->default(PublicFormSubmissionStatus::New->value)->index();
            $table->timestamp('submitted_at')->index();
            $table->text('source_url')->nullable();
            $table->string('submitter_ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_form_submissions');
    }
};
