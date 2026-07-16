<?php

namespace App\Auth;

final class AbilityCatalog
{
    public const VERSION = 'AUTHZ1-2026-07-16';

    public const HASH = 'fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7';

    /**
     * @var array<string, string>
     */
    private const PROTECTED_DOMAINS = [
        'content.transcriptions.multiple-manage' => 'transcription-feature-mode',
        'media.library.delete' => 'media-reference-integrity',
        'settings.card-templates.protected-manage' => 'protected-card-template',
        'users.roles.assign' => 'protected-role-integrity',
        'users.roles.assign-delegable' => 'protected-role-integrity',
        'security.roles.manage' => 'protected-role-integrity',
        'security.direct-grants.manage' => 'direct-grant-audit',
        'security.catalog.sync' => 'catalog-integrity',
        'templates.protected.activate' => 'protected-template',
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE_VIEWS = [
        'system.horizon.view',
        'users.accounts.view',
        'security.roles.view',
        'templates.protected.view',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const GROUPS = [
        'panel-system' => [
            'panel.admin.access',
            'system.horizon.view',
            'public.maintenance.bypass',
            'dashboard.admin.view',
        ],
        'editorial-records' => [
            'content.authors.view',
            'content.authors.create',
            'content.authors.update',
            'content.authors.delete',
            'content.authors.import',
            'content.authors.export',
            'content.categories.view',
            'content.categories.create',
            'content.categories.update',
            'content.categories.delete',
            'content.categories.import',
            'content.categories.export',
            'content.groups.view',
            'content.groups.create',
            'content.groups.update',
            'content.groups.delete',
            'content.groups.import',
            'content.groups.export',
            'content.items.view',
            'content.items.create',
            'content.items.update',
            'content.items.delete',
            'content.items.import',
            'content.items.export',
            'content.transcriptions.view',
            'content.transcriptions.create',
            'content.transcriptions.update',
            'content.transcriptions.delete',
            'content.transcriptions.import',
            'content.transcriptions.export',
            'content.tags.view',
            'content.tags.create',
            'content.tags.update',
            'content.tags.delete',
            'homepage.sections.view',
            'homepage.sections.create',
            'homepage.sections.update',
            'homepage.sections.delete',
            'homepage.sections.reorder',
        ],
        'transcription-policy' => [
            'content.transcriptions.history-view',
            'content.transcriptions.multiple-manage',
            'content.transcriptions.featured-manage',
        ],
        'media' => [
            'media.library.view',
            'media.library.create',
            'media.library.update',
            'media.library.delete',
            'media.library.download',
        ],
        'public-form-submissions' => [
            'forms.submissions.view',
            'forms.submissions.status-update',
            'forms.submissions.pii-view',
            'forms.submissions.pii-export',
        ],
        'settings-subjects' => [
            'settings.subjects.view',
            'settings.subjects.update',
            'settings.security-policy.update',
            'settings.trusted-html.update',
        ],
        'current-template-form-settings' => [
            'settings.card-templates.view',
            'settings.card-templates.create',
            'settings.card-templates.update',
            'settings.card-templates.delete',
            'settings.card-templates.protected-manage',
            'settings.public-forms.view',
            'settings.public-forms.create',
            'settings.public-forms.update',
            'settings.public-forms.delete',
        ],
        'settings-lifecycle' => [
            'settings.packages.export',
            'settings.packages.import',
            'settings.packages.restore',
            'settings.backups.view',
            'settings.backups.create',
            'settings.backups.delete',
            'settings.backups.download',
            'settings.backups.compare',
            'settings.snapshots.view',
            'settings.snapshots.retry',
            'settings.snapshots.download',
            'settings.import-locks.manage',
        ],
        'workbench-tools' => [
            'workbench.connections.view',
            'workbench.connections.create',
            'workbench.connections.update',
            'workbench.connections.delete',
            'workbench.connections.credentials-manage',
            'workbench.connections.test',
            'workbench.connections.oauth',
            'workbench.spotify.fetch',
            'workbench.spotify.direct-import',
            'workbench.probes.run',
            'tools.admin.use',
        ],
        'users-security' => [
            'users.accounts.view',
            'users.accounts.update',
            'users.roles.assign',
            'users.roles.assign-delegable',
            'security.roles.view',
            'security.roles.manage',
            'security.direct-grants.manage',
            'security.catalog.sync',
        ],
        'template-lifecycle' => [
            'templates.parents.view',
            'templates.parents.create',
            'templates.parents.update',
            'templates.parents.archive',
            'templates.parents.restore',
            'templates.drafts.own-update',
            'templates.drafts.other-view',
            'templates.drafts.adopt',
            'templates.drafts.discard',
            'templates.revisions.checkpoint',
            'templates.revisions.view',
            'templates.revisions.compare',
            'templates.revisions.publish',
            'templates.defaults.manage',
            'templates.protected.view',
            'templates.protected.export',
            'templates.protected.activate',
            'templates.portability.import',
            'templates.portability.export',
        ],
        'form-lifecycle' => [
            'forms.definitions.view',
            'forms.definitions.create',
            'forms.definitions.update',
            'forms.definitions.archive',
            'forms.definitions.restore',
            'forms.drafts.own-update',
            'forms.drafts.other-view',
            'forms.drafts.adopt',
            'forms.drafts.discard',
            'forms.revisions.checkpoint',
            'forms.revisions.view',
            'forms.revisions.compare',
            'forms.revisions.publish',
            'forms.revisions.revoke',
            'forms.availability.manage',
            'forms.portability.import',
            'forms.portability.export',
        ],
    ];

    /**
     * @return list<AbilityDefinition>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::GROUPS as $groupIndex => $keys) {
            $groupOrder = array_search($groupIndex, array_keys(self::GROUPS), true) + 1;

            foreach ($keys as $entryIndex => $key) {
                $action = explode('.', $key)[2];
                $sensitive = $action !== 'view' || in_array($key, self::SENSITIVE_VIEWS, true);

                $definitions[] = new AbilityDefinition(
                    key: $key,
                    group: $groupIndex,
                    groupOrder: $groupOrder,
                    entryOrder: $entryIndex + 1,
                    labelKey: "authz.abilities.{$key}.label",
                    descriptionKey: "authz.abilities.{$key}.description",
                    sensitive: $sensitive,
                    delegable: ! $sensitive,
                    protectedDomain: self::PROTECTED_DOMAINS[$key] ?? null,
                    guard: 'web',
                );
            }
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            fn (AbilityDefinition $definition): string => $definition->key,
            self::definitions(),
        );
    }

    /**
     * @return array{version: string, entries: list<array<string, bool|int|string|null>>}
     */
    public static function canonicalPayload(): array
    {
        return [
            'version' => self::VERSION,
            'entries' => array_map(
                fn (AbilityDefinition $definition): array => $definition->toArray(),
                self::definitions(),
            ),
        ];
    }

    public static function canonicalJson(): string
    {
        return json_encode(
            self::canonicalPayload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public static function hash(): string
    {
        return hash('sha256', self::canonicalJson());
    }
}
