<?php

namespace App\Auth;

use App\Enums\UserRole;
use InvalidArgumentException;

final class AuthorizationFoundationValidator
{
    /**
     * @param  list<AbilityDefinition|array<string, mixed>>  $entries
     */
    public static function assertCatalog(array $entries): void
    {
        $expectedFields = [
            'key',
            'group',
            'group_order',
            'entry_order',
            'label_key',
            'description_key',
            'sensitive',
            'delegable',
            'protected_domain',
            'guard',
        ];
        $keys = [];
        $normalizedKeys = [];
        $orders = [];
        $groupOrders = [];

        foreach ($entries as $entry) {
            $data = $entry instanceof AbilityDefinition ? $entry->toArray() : $entry;

            if (array_keys($data) !== $expectedFields) {
                throw new InvalidArgumentException('Ability entries must contain only the canonical fields in canonical order.');
            }

            $key = $data['key'];

            if (! is_string($key) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
                throw new InvalidArgumentException('Ability keys must use the literal lower-case three-segment grammar.');
            }

            $normalizedKey = strtolower($key);

            if (isset($keys[$key]) || isset($normalizedKeys[$normalizedKey])) {
                throw new InvalidArgumentException("Duplicate or normalized ability-key collision: {$key}.");
            }

            $keys[$key] = true;
            $normalizedKeys[$normalizedKey] = true;

            if (! is_string($data['group']) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['group']) !== 1) {
                throw new InvalidArgumentException("Invalid catalog group for {$key}.");
            }

            if (! is_int($data['group_order']) || $data['group_order'] < 1 || ! is_int($data['entry_order']) || $data['entry_order'] < 1) {
                throw new InvalidArgumentException("Catalog orders must be positive integers for {$key}.");
            }

            $orderKey = $data['group_order'].':'.$data['entry_order'];

            if (isset($orders[$orderKey])) {
                throw new InvalidArgumentException("Duplicate catalog order {$orderKey}.");
            }

            $orders[$orderKey] = true;

            if (isset($groupOrders[$data['group']]) && $groupOrders[$data['group']] !== $data['group_order']) {
                throw new InvalidArgumentException("Catalog group order changed within {$data['group']}.");
            }

            $groupOrders[$data['group']] = $data['group_order'];

            if ($data['label_key'] !== "authz.abilities.{$key}.label" || $data['description_key'] !== "authz.abilities.{$key}.description") {
                throw new InvalidArgumentException("Invalid translation keys for {$key}.");
            }

            if (! is_bool($data['sensitive']) || ! is_bool($data['delegable'])) {
                throw new InvalidArgumentException("Ability flags must be boolean for {$key}.");
            }

            if (! is_null($data['protected_domain']) && (! is_string($data['protected_domain']) || $data['protected_domain'] === '')) {
                throw new InvalidArgumentException("Invalid protected domain for {$key}.");
            }

            if ($data['guard'] !== 'web') {
                throw new InvalidArgumentException("Ability {$key} must use the web guard.");
            }
        }
    }

    /**
     * @param  list<RoleDefinition|array<string, mixed>>  $roles
     */
    public static function assertRoleMetadata(array $roles): void
    {
        $expectedFields = ['role', 'protected', 'reserved', 'delegable'];
        $knownRoles = UserRole::values();
        $seen = [];
        $normalized = [];

        foreach ($roles as $role) {
            $data = $role instanceof RoleDefinition ? $role->toArray() : $role;

            if (array_keys($data) !== $expectedFields) {
                throw new InvalidArgumentException('Role metadata must contain only the canonical fields in canonical order.');
            }

            $name = $data['role'];

            if (! is_string($name) || ! in_array($name, $knownRoles, true)) {
                throw new InvalidArgumentException('Role metadata contains an unknown role.');
            }

            if (isset($seen[$name]) || isset($normalized[strtolower($name)])) {
                throw new InvalidArgumentException("Duplicate role metadata: {$name}.");
            }

            if (! is_bool($data['protected']) || ! is_bool($data['reserved']) || ! is_bool($data['delegable'])) {
                throw new InvalidArgumentException("Role flags must be boolean for {$name}.");
            }

            $seen[$name] = true;
            $normalized[strtolower($name)] = true;
        }

        if (array_keys($seen) !== $knownRoles) {
            throw new InvalidArgumentException('Role metadata must contain all five roles in enum order.');
        }
    }

    /**
     * @param  array<string, list<string>>  $grants
     * @param  list<AbilityDefinition|array<string, mixed>>  $entries
     */
    public static function assertGrantManifest(array $grants, array $entries): void
    {
        self::assertCatalog($entries);

        $knownRoles = UserRole::values();

        if (array_keys($grants) !== $knownRoles) {
            throw new InvalidArgumentException('Grant manifest must contain all five known roles in enum order.');
        }

        $catalogKeys = [];

        foreach ($entries as $entry) {
            $data = $entry instanceof AbilityDefinition ? $entry->toArray() : $entry;
            $catalogKeys[$data['key']] = true;
        }

        foreach ($grants as $role => $roleGrants) {
            $seen = [];

            foreach ($roleGrants as $key) {
                if (! is_string($key) || ! isset($catalogKeys[$key])) {
                    throw new InvalidArgumentException("Grant manifest contains an unknown ability for {$role}.");
                }

                if (isset($seen[$key])) {
                    throw new InvalidArgumentException("Grant manifest contains a duplicate ability for {$role}.");
                }

                $seen[$key] = true;
            }
        }
    }

    public static function assertFoundation(): void
    {
        $definitions = AbilityCatalog::definitions();

        self::assertCatalog($definitions);
        self::assertRoleMetadata(RoleCatalog::definitions());
        self::assertGrantManifest(CompatibilityGrantManifest::grants(), $definitions);

        if (count($definitions) !== 135 || AbilityCatalog::hash() !== AbilityCatalog::HASH) {
            throw new InvalidArgumentException('Authorization catalog count or canonical hash does not match the frozen foundation.');
        }
    }
}
