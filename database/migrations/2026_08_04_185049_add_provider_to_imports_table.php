<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            // The operator's optional custom label for this import (modal
            // "name" field). Display rule everywhere: name ?: file_name.
            $table->string('name')->nullable()->after('file_name');
            // A PROCESS STAMP of intake channel, not provenance: the import
            // modal's listener stamps manual unconditionally (the modal IS
            // the manual process — operator override 2026-08-04, no user
            // select); future fetch processes write their own provider at
            // creation. Null = pre-column legacy rows, read as manual
            // (ImportConnectionProvider::fromImportValue()). WB's fetch-run
            // records supersede this as truth-at-origin (Q1 ruling
            // 2026-08-03).
            $table->string('provider')->nullable()->after('importer');
            // Reserved for WB fetch-run attribution; nothing writes it in
            // phase 3.
            $table->foreignId('import_connection_id')
                ->nullable()
                ->after('provider')
                ->constrained('import_connections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('import_connection_id');
            $table->dropColumn(['provider', 'name']);
        });
    }
};
