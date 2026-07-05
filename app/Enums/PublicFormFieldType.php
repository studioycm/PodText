<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicFormFieldType: string implements HasLabel
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Toggle = 'toggle';
    case Url = 'url';

    public function getLabel(): string
    {
        return __("admin.public_form_field_types.{$this->value}");
    }
}
