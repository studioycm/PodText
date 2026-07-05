<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PublicFormSubmissionStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __("admin.public_form_submission_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Reviewed => 'success',
            self::Archived => 'gray',
        };
    }
}
