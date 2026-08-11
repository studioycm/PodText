<?php

use App\Models\ContentItem;
use App\Models\ContentTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;

/*
 * larastan infers a relation's *kind* from the return type but not the
 * *related model*. Without `@return HasMany<Transcription, $this>`,
 * `$item->transcriptions` is a collection of `Model` and every concrete
 * property or method reached through it is reported undefined.
 *
 * Annotating all 45 relations took `composer types:check` from 507 to 444 with
 * zero new errors. Nothing in the language enforces those docblocks, though:
 * a new relation added without one silently gives back a slice of that, and
 * the only symptom is a number in a report nobody is gated on.
 *
 * So the first test pins the whole pass — every relation must carry a generic.
 *
 * The second pins something narrower and more interesting. PHPStan validates
 * each `@return` against the method body, which is what makes a bulk
 * annotation pass safe: annotate `MorphTo<ContentGroup|ContentItem, $this>` on
 * a bare `morphTo()` and it rejects the claim. But where the related class is
 * a *dynamic string* it cannot check anything. `ContentItem::tags()` calls
 * `morphToMany(self::getTagClassName(), ...)`, which resolves through
 * `config('tags.tag_model')` at runtime, so PHPStan takes `ContentTag` on
 * trust across three relations. That is true today and nothing enforces it —
 * edit `config/tags.php` and three annotations quietly become false while
 * static analysis stays green. This test is what makes the claim checked.
 *
 * See docs/research/larastan-playbook.md 4d and open-findings-triage.md B5.
 */

/**
 * Every relationship method declared across `app/Models`, as
 * `[ClassName::method => [ReflectionMethod, short relation name]]`.
 *
 * @return array<string, array{ReflectionMethod, string}>
 */
function relationshipMethods(): array
{
    $found = [];

    foreach (File::files(app_path('Models')) as $file) {
        $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Only relations written in this file. Comparing declaring class is
            // not enough: PHP reports a trait's methods as declared by the
            // class that uses them, which pulled in Spatie's
            // `HasTags::tagsTranslated()`. Vendor traits are not ours to
            // annotate, so compare the source file instead.
            if ($method->getFileName() !== $reflection->getFileName()) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            if (! is_subclass_of($returnType->getName(), Relation::class)) {
                continue;
            }

            $found["{$reflection->getShortName()}::{$method->getName()}"] = [
                $method,
                class_basename($returnType->getName()),
            ];
        }
    }

    return $found;
}

it('finds the relationship methods it is supposed to be guarding', function (): void {
    // A cheap canary: if the discovery above silently stops matching (a
    // refactor moves models, or return types are dropped), every other
    // assertion in this file would vacuously pass.
    // 46 = 45 + SettingsBackupVersion::fullSnapshotSourceBackup() (full-set
    // dedup pointer, register 1.8 fix 3).
    expect(relationshipMethods())->toHaveCount(46);
});

it('declares a generic on every Eloquent relationship so larastan can resolve the related model', function (): void {
    $missing = [];

    foreach (relationshipMethods() as $name => [$method, $relation]) {
        $docblock = $method->getDocComment();

        if ($docblock === false || preg_match("/@return\s+\\\\?{$relation}</", $docblock) !== 1) {
            $missing[] = "{$name}(): {$relation}";
        }
    }

    expect($missing)->toBe([], sprintf(
        "These relations have no `@return %s<Related, \$this>` generic, so larastan resolves them to Model:\n  %s",
        '<Relation>',
        implode("\n  ", $missing),
    ));
});

it('keeps the tag class pointed at ContentTag, which three annotations assert but PHPStan cannot verify', function (): void {
    // `ContentItem::tags()`, `contentTags()` and `enabledContentTags()` all
    // annotate `MorphToMany<ContentTag, $this>`, built from a runtime config
    // lookup PHPStan cannot follow. This is the check it cannot make.
    expect(ContentItem::getTagClassName())->toBe(ContentTag::class);
    expect(config('tags.tag_model'))->toBe(ContentTag::class);
});
