<?php

use App\Auth\AbilityCatalog;
use App\Auth\AuthorizationFoundationValidator;
use App\Auth\CompatibilityGrantManifest;
use App\Auth\RoleCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

function authzFoundationFixture(): array
{
    return require base_path('tests/Fixtures/Authz/authorization-foundation.php');
}

function authzReplaceAbilityKey(array $entries, int $index, string $key): array
{
    $entries[$index]['key'] = $key;
    $entries[$index]['label_key'] = "authz.abilities.{$key}.label";
    $entries[$index]['description_key'] = "authz.abilities.{$key}.description";

    return $entries;
}

it('freezes the independent literal catalog vector and canonical full-entry hash', function (): void {
    $fixture = authzFoundationFixture();
    $payload = AbilityCatalog::canonicalPayload();

    expect(AbilityCatalog::VERSION)->toBe($fixture['version'])
        ->and(AbilityCatalog::keys())->toBe($fixture['keys'])
        ->and(count(AbilityCatalog::keys()))->toBe(135)
        ->and(hash('sha256', json_encode($fixture['keys'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)))->toBe($fixture['key_hash'])
        ->and($payload['entries'])->toBe($fixture['entries'])
        ->and(AbilityCatalog::hash())->toBe($fixture['hash'])
        ->and(AbilityCatalog::HASH)->toBe($fixture['hash']);

    AuthorizationFoundationValidator::assertFoundation();
});

it('freezes protected role metadata and compatibility grants independently', function (): void {
    $fixture = authzFoundationFixture();
    $roles = array_map(fn ($definition): array => $definition->toArray(), RoleCatalog::definitions());
    $grants = CompatibilityGrantManifest::grants();

    expect($roles)->toBe($fixture['roles'])
        ->and($grants)->toBe($fixture['grants'])
        ->and($grants['super-admin'])->toBe($fixture['keys'])
        ->and($grants['admin'])->toBe($fixture['admin_allowed'])
        ->and($grants['moderator'])->toBe([])
        ->and($grants['transcriber'])->toBe([])
        ->and($grants['user'])->toBe([])
        ->and($fixture['admin_allowed'])->toHaveCount(89)
        ->and($fixture['admin_denied'])->toHaveCount(46)
        ->and(array_intersect($fixture['admin_allowed'], $fixture['admin_denied']))->toBe([])
        ->and(array_values(array_unique([...$fixture['admin_allowed'], ...$fixture['admin_denied']])))->toHaveCount(135);
});

it('rejects every invalid catalog fixture without accepting a partial result', function (Closure $mutate): void {
    $entries = authzFoundationFixture()['entries'];

    expect(fn () => AuthorizationFoundationValidator::assertCatalog($mutate($entries)))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'uppercase' => fn (array $entries): array => authzReplaceAbilityKey($entries, 0, 'Panel.admin.access'),
    'wildcard' => fn (array $entries): array => authzReplaceAbilityKey($entries, 0, 'panel.*.access'),
    'brace shorthand' => fn (array $entries): array => authzReplaceAbilityKey($entries, 4, 'content.authors.{view,create}'),
    'underscore' => fn (array $entries): array => authzReplaceAbilityKey($entries, 0, 'panel_admin_access'),
    'empty segment' => fn (array $entries): array => authzReplaceAbilityKey($entries, 0, 'panel..access'),
    'duplicate literal' => function (array $entries): array {
        $entries[] = $entries[0];

        return $entries;
    },
    'normalized case pair' => function (array $entries): array {
        $entries[] = array_replace($entries[0], [
            'key' => 'Panel.Admin.Access',
            'label_key' => 'authz.abilities.Panel.Admin.Access.label',
            'description_key' => 'authz.abilities.Panel.Admin.Access.description',
            'entry_order' => 99,
        ]);

        return $entries;
    },
    'duplicate order' => function (array $entries): array {
        $entries[1]['group_order'] = $entries[0]['group_order'];
        $entries[1]['entry_order'] = $entries[0]['entry_order'];

        return $entries;
    },
    'wrong guard' => function (array $entries): array {
        $entries[0]['guard'] = 'api';

        return $entries;
    },
]);

it('rejects invalid grant and role metadata fixtures', function (): void {
    $fixture = authzFoundationFixture();

    $unknownGrant = $fixture['grants'];
    $unknownGrant['admin'][] = 'system.unknown.view';

    $unknownRole = $fixture['roles'];
    $unknownRole[4]['role'] = 'owner';

    $duplicateAdmin = $fixture['roles'];
    $duplicateAdmin[4] = $duplicateAdmin[1];

    $missingUser = $fixture['roles'];
    array_pop($missingUser);

    expect(fn () => AuthorizationFoundationValidator::assertGrantManifest($unknownGrant, $fixture['entries']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => AuthorizationFoundationValidator::assertRoleMetadata($unknownRole))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => AuthorizationFoundationValidator::assertRoleMetadata($duplicateAdmin))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => AuthorizationFoundationValidator::assertRoleMetadata($missingUser))
        ->toThrow(InvalidArgumentException::class);
});

it('provides exact nonempty Hebrew and English catalog translations without fallback borrowing', function (): void {
    $fixture = authzFoundationFixture();
    $localeFiles = [
        'en' => require lang_path('en/authz.php'),
        'he' => require lang_path('he/authz.php'),
    ];
    $expectedAbilityKeys = collect($fixture['entries'])
        ->flatMap(fn (array $entry): array => [$entry['label_key'], $entry['description_key']])
        ->map(fn (string $key): string => substr($key, strlen('authz.')))
        ->all();

    $expectedAbilityKeys = collect($expectedAbilityKeys)->sort()->values()->all();
    $englishAbilityKeys = collect(array_keys(Arr::dot($localeFiles['en']['abilities'], 'abilities.')))->sort()->values()->all();
    $hebrewAbilityKeys = collect(array_keys(Arr::dot($localeFiles['he']['abilities'], 'abilities.')))->sort()->values()->all();

    expect($englishAbilityKeys)->toBe($expectedAbilityKeys)
        ->and($hebrewAbilityKeys)->toBe($expectedAbilityKeys)
        ->and(array_keys($localeFiles['en']['groups']))->toBe(array_keys($fixture['groups']))
        ->and(array_keys($localeFiles['he']['groups']))->toBe(array_keys($fixture['groups']));

    foreach (['en', 'he'] as $locale) {
        foreach ($fixture['entries'] as $entry) {
            foreach ([$entry['label_key'], $entry['description_key']] as $translationKey) {
                $value = Lang::get($translationKey, [], $locale, false);

                expect(Lang::hasForLocale($translationKey, $locale))->toBeTrue()
                    ->and($value)->toBeString()
                    ->and(trim($value))->not->toBe('')
                    ->and($value)->not->toBe($translationKey);
            }
        }

        foreach (array_keys($fixture['groups']) as $group) {
            $translationKey = "authz.groups.{$group}";
            $value = Lang::get($translationKey, [], $locale, false);

            expect(Lang::hasForLocale($translationKey, $locale))->toBeTrue()
                ->and(trim($value))->not->toBe('')
                ->and($value)->not->toBe($translationKey);
        }
    }
});

it('has no duplicate literal keys within either authorization translation file', function (string $locale): void {
    $code = file_get_contents(lang_path("{$locale}/authz.php"));
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
    $arrays = (new NodeFinder)->findInstanceOf($statements, Array_::class);

    foreach ($arrays as $array) {
        $seen = [];

        foreach ($array->items as $item) {
            if ($item?->key instanceof String_ || $item?->key instanceof Int_) {
                $key = (string) $item->key->value;

                expect($seen)->not->toHaveKey($key);
                $seen[$key] = true;
            }
        }
    }
})->with(['en', 'he']);
