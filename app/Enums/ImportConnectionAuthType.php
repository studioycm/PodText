<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportConnectionAuthType: string implements HasLabel
{
    case ServiceAccount = 'service_account';
    case OAuth = 'oauth';
    case ClientCredentials = 'client_credentials';
    case None = 'none';

    public function getLabel(): string
    {
        return __("admin.importer.auth_types.{$this->value}");
    }
}
