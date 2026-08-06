<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shadow columns holding the niqqud-stripped fold of every searched free-text
 * column, so search runs a plain portable LIKE that SQLite executes exactly as
 * MySQL does. See docs/phase-02/hebrew-search-folding-spec.md.
 *
 * Not covered, deliberately:
 *
 * - `transcript_markdown` on either table. Measured: no code path searches it —
 *   zero LIKE predicates, zero Filament `->searchable()` columns. Shadowing it
 *   would duplicate the largest column in the schema to serve no query.
 * - Slug columns. HebrewSlugger already folds its input, so a slug is its own
 *   fold and a folded term compares against it directly. Asserted in
 *   tests/Unit/HebrewSluggerTest.php rather than assumed.
 * - Reference keys, language codes, providers, URLs and e-mail. ASCII
 *   identifiers, nothing to fold.
 *
 * Indexes go on the varchar shadows only. MySQL cannot index a LONGTEXT column
 * without a key prefix length, and expressing one would mean the same
 * per-driver schema branching §1 of the spec disqualifies. Note that a B-tree
 * index cannot serve the leading-wildcard LIKE that ships either way; these
 * earn their keep on the backfill's `IS NULL` sweep and on equality or prefix
 * work later.
 */
return new class extends Migration
{
    /**
     * Source column => shadow column, keyed by table. Mirrors
     * HasFoldedSearchColumns::foldedSearchColumns() on each model.
     *
     * @var array<string, array<string, string>>
     */
    private const StringColumns = [
        'content_items' => ['title' => 'title_search'],
        'content_groups' => [
            'title' => 'title_search',
            'group_type_label_singular' => 'group_type_label_singular_search',
            'default_item_type_label_singular' => 'default_item_type_label_singular_search',
        ],
        'categories' => ['name' => 'name_search'],
        'authors' => ['name' => 'name_search'],
        'transcriptions' => ['title' => 'title_search'],
        'curator' => ['title' => 'title_search', 'name' => 'name_search'],
        'homepage_sections' => ['name' => 'name_search'],
        'import_connections' => ['name' => 'name_search'],
        'users' => ['name' => 'name_search'],
        'public_form_submissions' => ['form_name_snapshot' => 'form_name_snapshot_search'],
        'settings_backup_versions' => ['label' => 'label_search'],
    ];

    /**
     * Long-form prose, plus the flattened shadow of Spatie's translatable
     * `tags.name` JSON. No index — see the class docblock.
     *
     * @var array<string, array<int, string>>
     */
    private const TextColumns = [
        'content_items' => ['description_markdown_search'],
        'content_groups' => ['description_markdown_search'],
        'tags' => ['name_search'],
    ];

    public function up(): void
    {
        foreach (self::StringColumns as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $source => $shadow) {
                    $table->string($shadow)->nullable()->after($source);
                    $table->index($shadow);
                }
            });
        }

        foreach (self::TextColumns as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $shadow) {
                    $table->longText($shadow)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::StringColumns as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $shadow) {
                    $table->dropIndex([$shadow]);
                    $table->dropColumn($shadow);
                }
            });
        }

        foreach (self::TextColumns as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
