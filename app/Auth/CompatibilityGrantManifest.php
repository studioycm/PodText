<?php

namespace App\Auth;

use App\Enums\UserRole;

final class CompatibilityGrantManifest
{
    /**
     * @return array<string, list<string>>
     */
    public static function grants(): array
    {
        return [
            UserRole::SuperAdmin->value => AbilityCatalog::keys(),
            UserRole::Admin->value => self::adminAbilities(),
            UserRole::Moderator->value => [],
            UserRole::Transcriber->value => [],
            UserRole::User->value => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function adminAbilities(): array
    {
        return [
            'panel.admin.access',
            'system.horizon.view',
            'public.maintenance.bypass',
            'dashboard.admin.view',
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
            'content.transcriptions.history-view',
            'content.transcriptions.multiple-manage',
            'content.transcriptions.featured-manage',
            'media.library.view',
            'media.library.create',
            'media.library.update',
            'media.library.delete',
            'media.library.download',
            'forms.submissions.view',
            'forms.submissions.status-update',
            'forms.submissions.pii-view',
            'settings.subjects.view',
            'settings.subjects.update',
            'settings.security-policy.update',
            'settings.trusted-html.update',
            'settings.card-templates.view',
            'settings.card-templates.create',
            'settings.card-templates.update',
            'settings.card-templates.delete',
            'settings.public-forms.view',
            'settings.public-forms.create',
            'settings.public-forms.update',
            'settings.public-forms.delete',
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
        ];
    }
}
